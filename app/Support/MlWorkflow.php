<?php

namespace App\Support;

use App\Models\ForecastResult;
use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MlWorkflow
{
    private const DATASET_PATH = 'ml/processed_dataset.csv';

    private const STATE_PATH = 'ml/model_state.json';

    public function trainIfDatasetChanged(?int $userId = null, bool $force = false): array
    {
        $snapshot = ForecastDataset::snapshot();

        return $this->trainSnapshot($snapshot, $userId, $force);
    }

    /** Fitted historical estimates, kept separate from saved next-month forecasts. */
    public function historicalPredictions(): Collection
    {
        // Validate the upload even when predictions have already been cached.
        $snapshot = ForecastDataset::snapshot();
        if ($snapshot['records']->count() < 6) {
            return collect();
        }

        return Cache::lock('dataset-next-month-forecast', 180)->block(5, function () use ($snapshot) {
            if (ForecastDataset::snapshot()['dataset_hash'] !== $snapshot['dataset_hash']) {
                throw new RuntimeException('The uploaded dataset changed during historical prediction.');
            }
            $path = 'ml/history/'.$snapshot['dataset_hash'].'.json';
            if (Storage::exists($path)) {
                $cached = json_decode(Storage::get($path), true);
                if (is_array($cached)) {
                    return collect($cached)->keyBy('period');
                }
            }

            // Existing uploads may predate historical estimates. Train only from this
            // validated snapshot, never from a bundled or stale model/export.
            $this->trainSnapshot($snapshot, null);
            $result = $this->runPython(
                'predict_history.py',
                $this->modelPathForHash($snapshot['dataset_hash']),
                $this->metricsPathForHash($snapshot['dataset_hash']),
                'ml/datasets/'.$snapshot['dataset_hash'].'.csv',
            );
            if ($result->failed()) {
                throw new RuntimeException($result->errorOutput() ?: $result->output());
            }

            $predictions = json_decode(trim($result->output()), true);
            $periods = $snapshot['records']->map(fn ($row) => sprintf('%04d-%02d', $row->year, $row->month))->all();
            if (! is_array($predictions) || ! array_is_list($predictions)
                || array_column($predictions, 'period') !== $periods) {
                throw new RuntimeException('Historical predictions do not match the uploaded dataset periods.');
            }
            foreach ($predictions as $prediction) {
                if (! is_numeric($prediction['predicted'] ?? null)
                    || ! is_finite((float) $prediction['predicted']) || (float) $prediction['predicted'] < 0) {
                    throw new RuntimeException('Historical prediction contains an invalid consumption value.');
                }
            }
            if (ForecastDataset::snapshot()['dataset_hash'] !== $snapshot['dataset_hash']) {
                throw new RuntimeException('The uploaded dataset changed during historical prediction.');
            }

            Storage::put($path, json_encode($predictions, JSON_THROW_ON_ERROR));

            return collect($predictions)->keyBy('period');
        });
    }

    private function trainSnapshot(array $snapshot, ?int $userId, bool $force = false): array
    {
        if ($snapshot['records']->count() < 6) {
            throw new RuntimeException('At least 6 consecutive complete processed months are required for forecasting.');
        }
        $hash = $snapshot['dataset_hash'];
        $datasetPath = 'ml/datasets/'.$hash.'.csv';
        Storage::put($datasetPath, ForecastDataset::csv($snapshot['records']));
        $state = $this->modelState();
        $modelPath = $this->modelPathForHash($hash);
        $metricsPath = $this->metricsPathForHash($hash);
        $modelExists = Storage::exists($modelPath) && Storage::exists($metricsPath);
        $changed = ($state['dataset_hash'] ?? null) !== $hash;

        if (! $force && ! $changed && $modelExists) {
            return [
                'trained' => false,
                'dataset_hash' => $hash,
                'reason' => 'No new or updated processed dataset detected.',
            ];
        }

        Storage::makeDirectory(dirname($modelPath));
        $result = $this->runPython('train_model.py', $modelPath, $metricsPath, $datasetPath);

        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput() ?: $result->output());
        }

        if (! Storage::exists($modelPath) || ! Storage::exists($metricsPath)) {
            throw new RuntimeException('Training did not produce a model and metrics for the uploaded dataset.');
        }

        Storage::put(self::STATE_PATH, json_encode([
            'dataset_hash' => $hash,
            'model_path' => $modelPath,
            'metrics_path' => $metricsPath,
            'trained_at' => now()->toDateTimeString(),
            'trained_by' => $userId,
        ]));

        AuditLogger::log(
            'ML Forecasting',
            $force ? 'Train Model' : 'Auto Train Model',
            'Trained the machine learning model using the latest processed dataset.'
        );

        return [
            'trained' => true,
            'dataset_hash' => $hash,
            'output' => $result->output(),
        ];
    }

    public function predictAndGenerate(
        ?int $userId = null,
        string $source = 'manual',
        bool $generateReport = true,
        bool $refreshForecastTimestamp = false,
        bool $refreshDssTimestamp = false,
    ): array {
        return Cache::lock('dataset-next-month-forecast', 180)->block(5, function () use ($userId, $source, $generateReport, $refreshForecastTimestamp, $refreshDssTimestamp) {
            return $this->generatePrediction($userId, $source, $generateReport, $refreshForecastTimestamp, $refreshDssTimestamp);
        });
    }

    private function generatePrediction(?int $userId, string $source, bool $generateReport, bool $refreshForecastTimestamp, bool $refreshDssTimestamp): array
    {
        $snapshot = ForecastDataset::snapshot();
        if ($snapshot['records']->count() < 6) {
            throw new RuntimeException('At least 6 consecutive complete processed months are required for forecasting.');
        }
        // Export only the validated uploaded snapshot.
        Storage::put(self::DATASET_PATH, ForecastDataset::csv($snapshot['records']));
        $identity = array_intersect_key($snapshot, array_flip(['dataset_hash', 'year', 'month']));
        $existing = ForecastResult::where($identity)->first();
        if ($existing) {
            return DB::transaction(function () use ($existing, $userId, $source, $generateReport, $refreshForecastTimestamp, $refreshDssTimestamp) {
                $generatedAt = now();
                if ($refreshForecastTimestamp) {
                    $existing->forceFill(['predicted_at' => $generatedAt])->save();
                    AuditLogger::log(
                        'Forecasting',
                        'Refresh Prediction Timestamp',
                        'Refreshed prediction timestamp for existing forecast #'.$existing->id.'.'
                    );
                }

                return [
                    'forecast' => $existing,
                    'dss' => EnergyDss::generateForForecast($existing, $userId, $generatedAt, $refreshDssTimestamp),
                    'report' => $generateReport
                        ? (Report::where('summary->latest_forecast->id', $existing->id)->latest('id')->first()
                            ?? ReportGenerator::create($userId, $source, $existing))
                        : null,
                ];
            });
        }

        // Use an immutable snapshot so simultaneous exports cannot change prediction input.
        $datasetPath = 'ml/datasets/'.$snapshot['dataset_hash'].'.csv';
        Storage::put($datasetPath, ForecastDataset::csv($snapshot['records']));
        $this->trainSnapshot($snapshot, $userId);
        $modelPath = $this->modelPathForHash($snapshot['dataset_hash']);
        $metricsPath = $this->metricsPathForHash($snapshot['dataset_hash']);
        $result = $this->runPython('predict_next_month.py', $modelPath, $metricsPath, $datasetPath);

        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput() ?: $result->output());
        }

        $data = json_decode(trim($result->output()), true);

        if (! is_array($data) || $data === []) {
            throw new RuntimeException('Invalid JSON output from Python prediction script.');
        }

        if (($data['year'] ?? null) !== $snapshot['year'] || ($data['month'] ?? null) !== $snapshot['month']
            || ! is_numeric($data['predicted_consumption_kwh'] ?? null)
            || ! is_finite((float) $data['predicted_consumption_kwh'])
            || (float) $data['predicted_consumption_kwh'] < 0) {
            throw new RuntimeException('Prediction does not match the immediate next dataset month or contains an invalid value.');
        }

        $generatedAt = now();

        if (ForecastDataset::snapshot()['dataset_hash'] !== $snapshot['dataset_hash']) {
            throw new RuntimeException('The uploaded dataset changed during forecasting. Generate the forecast again.');
        }

        return DB::transaction(function () use ($identity, $data, $userId, $generatedAt, $generateReport, $source, $refreshDssTimestamp) {
            $forecast = ForecastResult::firstOrCreate($identity, [
                'user_id' => $userId,
                'predicted_at' => $generatedAt,
                'predicted_consumption_kwh' => $data['predicted_consumption_kwh'],
                'previous_consumption_kwh' => $data['previous_consumption_kwh'] ?? null,
                'change_percent' => $data['change_percent'] ?? null,
                'mae' => $data['mae'] ?? null,
                'rmse' => $data['rmse'] ?? null,
                'r2_score' => $data['r2_score'] ?? null,
                'model_type' => $data['model_type'] ?? 'Linear Regression',
            ]);

            $dss = EnergyDss::generateForForecast($forecast, $userId, $generatedAt, $refreshDssTimestamp);
            $report = $generateReport ? ReportGenerator::create($userId, $source, $forecast) : null;

            AuditLogger::log(
                'Forecasting',
                $source === 'manual' ? 'Generate Prediction' : 'Auto Generate Prediction',
                'Generated next-month prediction for '.
                    $forecast->year.'-'.
                    str_pad((string) $forecast->month, 2, '0', STR_PAD_LEFT).
                    ': '.$forecast->predicted_consumption_kwh.' kWh.'
            );

            if ($report instanceof Report) {
                AuditLogger::log(
                    'Reports',
                    $source === 'manual' ? 'Generate Report' : 'Auto Generate Report',
                    'Generated report: '.$report->title.'.'
                );
            }

            return [
                'forecast' => $forecast,
                'dss' => $dss,
                'report' => $report,
            ];
        });
    }

    public function runAfterDatasetUpload(?int $userId = null): array
    {
        $result = $this->predictAndGenerate($userId, 'dataset_upload');

        return [
            'trained' => false,
            'predicted' => true,
            'forecast' => $result['forecast'],
            'dss' => $result['dss'],
            'report' => $result['report'],
        ];
    }

    public function runScheduledMonthlyPrediction(): array
    {
        return $this->predictAndGenerate(null, 'monthly_cron');
    }

    private function runPython(string $script, string $modelPath, string $metricsPath, ?string $datasetPath = null)
    {
        return Process::path(base_path('Python'))->run([
            config('ml.python'),
            $script,
            Storage::path($datasetPath ?? self::DATASET_PATH),
            str_starts_with($modelPath, base_path()) ? $modelPath : Storage::path($modelPath),
            str_starts_with($metricsPath, base_path()) ? $metricsPath : Storage::path($metricsPath),
        ]);
    }

    private function modelState(): array
    {
        if (! Storage::exists(self::STATE_PATH)) {
            return [];
        }

        return json_decode(Storage::get(self::STATE_PATH), true) ?: [];
    }

    private function modelPathForHash(string $hash): string
    {
        return 'ml/models/qc_energy_model_'.substr($hash, 0, 16).'.pkl';
    }

    private function metricsPathForHash(string $hash): string
    {
        return 'ml/models/metrics_'.substr($hash, 0, 16).'.json';
    }
}
