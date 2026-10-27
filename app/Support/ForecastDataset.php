<?php

namespace App\Support;

use App\Models\ClimateRecord;
use App\Models\ConsumptionRecord;
use App\Models\Dataset;
use App\Models\ForecastResult;
use App\Models\ProcessedRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ForecastDataset
{
    public const FIELDS = ['year', 'month', 'consumption_kwh', 'temperature', 'humidity', 'rainfall', 'solar_irradiance', 'peak_demand_kw', 'lag_1', 'lag_2', 'trend', 'month_sin', 'month_cos'];

    public static function snapshot(): array
    {
        $actuals = self::uploadedActuals();
        $processed = ProcessedRecord::orderBy('year')->orderBy('month')->get();
        $byPeriod = $processed->keyBy(fn ($row) => "$row->year-$row->month");
        $climate = ClimateRecord::all()->keyBy(fn ($row) => "$row->year-$row->month");
        $sourceRows = [];
        $eligible = collect();
        $expected = null;

        foreach ($actuals as $actual) {
            if (! MonthlyDataQuality::validPeriod($actual->toArray())) {
                break;
            }
            $period = Carbon::create((int) $actual->year, (int) $actual->month, 1)->startOfDay();
            // Stop at the first gap; later actuals cannot bridge a missing month.
            if ($expected && ! $period->equalTo($expected)) {
                break;
            }
            $row = $byPeriod->get("$actual->year-$actual->month");
            $sourceRows[$actual->dataset_id] ??= self::sourceRows($actual->dataset_id);
            $complete = $actual->is_complete !== false
                && MonthlyDataQuality::isComplete($sourceRows[$actual->dataset_id]["$actual->year-$actual->month"] ?? []);
            if ($complete === false || ! is_numeric($actual->consumption_kwh)
                || ! is_finite((float) $actual->consumption_kwh) || (float) $actual->consumption_kwh < 0) {
                break;
            }
            if (! self::matchesActual($row, $actual, $climate->get("$actual->year-$actual->month"))) {
                throw new RuntimeException('Run preprocessing for the latest complete actual dataset before forecasting.');
            }
            if (! MonthlyDataQuality::isComplete([$row->toArray()])) {
                break;
            }
            foreach (self::FIELDS as $field) {
                if (! is_numeric($row->{$field}) || ! is_finite((float) $row->{$field})) {
                    break 2;
                }
            }
            $eligible->push($row);
            $expected = $period->copy()->addMonth();
        }

        if ($eligible->isEmpty()) {
            throw new RuntimeException('No complete, valid monthly actual data is ready for forecasting. Check the dataset and run preprocessing.');
        }

        return [
            'records' => $eligible,
            'year' => $expected->year,
            'month' => $expected->month,
            'dataset_hash' => hash('sha256', json_encode([
                'uploaded-dataset-v1',
                $actuals->map->only(['dataset_id', 'year', 'month', 'consumption_kwh', 'peak_demand_kw', 'is_complete'])->all(),
                $sourceRows,
                $processed->map->only(self::FIELDS)->all(),
                $eligible->map->only(['year', 'month'])->all(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    public static function target(): ?array
    {
        try {
            $snapshot = self::snapshot();

            return $snapshot['records']->count() >= 6
                ? ['year' => $snapshot['year'], 'month' => $snapshot['month']]
                : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /** Only forecasts generated from the currently available uploaded data may be displayed. */
    public static function currentForecasts(): Builder
    {
        $query = ForecastResult::query()->where('predicted_consumption_kwh', '>=', 0)->whereNotNull('predicted_at');
        try {
            $snapshot = self::snapshot();
            if ($snapshot['records']->count() >= 6) {
                return $query->where('dataset_hash', $snapshot['dataset_hash'])
                    ->where('year', $snapshot['year'])->where('month', $snapshot['month']);
            }
        } catch (RuntimeException) {
            // Missing uploads or stale preprocessing must not expose saved predictions.
        }

        return $query->whereRaw('1 = 0');
    }

    public static function uploadedActuals(): Collection
    {
        $ids = Dataset::where('status', 'valid')->get()
            ->filter(fn ($dataset) => Storage::exists($dataset->path))->modelKeys();

        return ConsumptionRecord::whereIn('dataset_id', $ids)->orderBy('year')->orderBy('month')->get();
    }

    /** Valid actuals for charts, independent of the consecutive-month training requirement. */
    public static function actualRecords(): Collection
    {
        $processed = ProcessedRecord::orderBy('year')->orderBy('month')->get()
            ->keyBy(fn ($row) => "$row->year-$row->month");
        $sources = [];
        $climate = ClimateRecord::all()->keyBy(fn ($row) => "$row->year-$row->month");
        $records = collect();
        foreach (self::uploadedActuals() as $actual) {
            $sources[$actual->dataset_id] ??= self::sourceRows($actual->dataset_id);
            $period = "$actual->year-$actual->month";
            $row = $processed->get($period);
            if ($actual->is_complete === false || ! MonthlyDataQuality::isComplete($sources[$actual->dataset_id][$period] ?? [])
                || ! $row || ! MonthlyDataQuality::isComplete([$row->toArray()])
                || ! self::matchesActual($row, $actual, $climate->get($period))) {
                continue;
            }
            $records->push($row);
        }

        return $records;
    }

    private static function matchesActual(?ProcessedRecord $row, ConsumptionRecord $actual, ?ClimateRecord $climate): bool
    {
        if (! $row || ! $climate || $climate->dataset_id !== $actual->dataset_id) {
            return false;
        }
        foreach (['consumption_kwh', 'peak_demand_kw'] as $field) {
            if (! is_numeric($actual->{$field}) || (float) $row->{$field} !== (float) $actual->{$field}) {
                return false;
            }
        }
        foreach (['temperature', 'humidity', 'rainfall', 'solar_irradiance'] as $field) {
            if (! is_numeric($climate->{$field}) || (float) $row->{$field} !== (float) $climate->{$field}) {
                return false;
            }
        }

        return true;
    }

    public static function csv(Collection $records): string
    {
        return implode(PHP_EOL, [implode(',', self::FIELDS), ...$records->map(
            fn ($row) => implode(',', array_map(fn ($field) => $row->{$field}, self::FIELDS))
        )->all()]);
    }

    private static function sourceRows(int $datasetId): array
    {
        $dataset = Dataset::find($datasetId);
        if (! $dataset || $dataset->status !== 'valid' || ! Storage::exists($dataset->path)) {
            return [];
        }
        $file = fopen(Storage::path($dataset->path), 'r');
        $headers = array_map(fn ($value) => strtolower(trim($value)), fgetcsv($file, escape: '') ?: []);
        $groups = [];
        while (($values = fgetcsv($file, escape: '')) !== false) {
            if (count($values) !== count($headers)) {
                continue;
            }
            $row = array_combine($headers, $values);
            if (MonthlyDataQuality::validPeriod($row)) {
                $groups[(int) $row['year'].'-'.(int) $row['month']][] = $row;
            }
        }
        fclose($file);

        return $groups;
    }
}
