<?php

namespace App\Support;

use App\Models\DssResult;
use App\Models\ForecastResult;
use App\Models\ProcessedRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EnergyDss
{
    public static function classifyDemand(float $predicted, ?float $average = null): string
    {
        $baseline = max($average ?: $predicted, 1);
        $ratio = $predicted / $baseline;

        return match (true) {
            $ratio >= 1.15 => 'High Demand',
            $ratio >= 0.90 => 'Moderate Demand',
            default => 'Low Demand',
        };
    }

    public static function readiness(float $solarIrradiance, float $peakDemand, float $predicted): string
    {
        $score = 0;
        $score += $solarIrradiance >= 5 ? 2 : ($solarIrradiance >= 3.5 ? 1 : 0);
        $score += $peakDemand <= ($predicted * 0.20) ? 2 : 1;

        return match (true) {
            $score >= 4 => 'High Readiness',
            $score >= 2 => 'Moderate Readiness',
            default => 'Low Readiness',
        };
    }

    public static function recommendations(ForecastResult $forecast, ?ProcessedRecord $latest, string $demand, string $readiness): array
    {
        $items = [];
        $actions = [];
        $solar = (float) ($latest?->solar_irradiance ?? 0);
        $peak = (float) ($latest?->peak_demand_kw ?? 0);
        $predicted = (float) $forecast->predicted_consumption_kwh;

        if ($demand === 'High Demand' && $solar >= 4) {

            $items[] = 'The system detected a high electricity demand together with good solar energy potential. Because of this, the area may benefit from starting renewable energy transition planning, especially for government buildings, public facilities, and other high-consumption establishments.';

            $actions[] = 'The administration should begin conducting solar photovoltaic feasibility studies, evaluate available rooftop or open-space areas for solar panel installation, and identify facilities with the highest electricity consumption for possible renewable energy integration.';
        }

        if ($peak > ($predicted * 0.18)) {

            $items[] = 'The system identified that the peak demand is relatively high compared to the predicted electricity consumption. This may indicate heavy electricity usage during certain hours, which can increase operational costs and pressure on the energy supply system.';

            $actions[] = 'The administration is encouraged to implement energy efficiency programs, monitor peak-hour electricity usage, replace inefficient equipment, and apply load management strategies to reduce excessive demand during high-consumption periods.';
        }

        if (($forecast->change_percent ?? 0) > 5) {

            $items[] = 'The forecasted electricity consumption shows a noticeable increase compared to the previous period. This may indicate growing energy demand, population activity, or operational expansion that could require additional renewable energy planning and energy management preparation.';

            $actions[] = 'The administration should review facilities or locations with increasing electricity consumption, monitor areas with rapid energy growth, and prioritize renewable energy projects that can help offset future electricity demand.';
        }

        if ($readiness === 'Low Readiness') {

            $items[] = 'The system assessed that the current renewable energy readiness level is still low. This may be caused by limited solar energy potential, insufficient monitoring data, or high peak electricity demand that may affect renewable energy transition planning.';

            $actions[] = 'The administration should first improve electricity and environmental data collection, strengthen energy monitoring practices, and establish a more consistent monthly reporting process before implementing large-scale renewable energy investments.';
        }

        if ($items === []) {

            $items[] = 'The system assessment shows that the current electricity demand and renewable energy readiness are within manageable conditions. At this stage, continuous monitoring and gradual renewable energy preparation are recommended to maintain stable energy planning.';

            $actions[] = 'The administration should continue updating electricity forecasts regularly, compare actual and predicted consumption results, maintain energy monitoring activities, and gradually prepare long-term renewable energy transition strategies.';
        }

        return [$items, $actions];
    }

    public static function generateForForecast(ForecastResult $forecast, ?int $userId = null, ?CarbonInterface $generatedAt = null, bool $refreshTimestamp = false): DssResult
    {
        $history = ProcessedRecord::query()->whereRaw('(year * 12 + month) < ?', [$forecast->year * 12 + $forecast->month]);
        $records = $history->orderBy('year')->orderBy('month')->get();
        $latest = $records->last();
        $average = (float) $records->avg('consumption_kwh');

        $demand = self::classifyDemand(
            (float) $forecast->predicted_consumption_kwh,
            $average
        );

        $readiness = self::readiness(
            (float) ($latest?->solar_irradiance ?? 0),
            (float) ($latest?->peak_demand_kw ?? 0),
            (float) $forecast->predicted_consumption_kwh
        );

        [$recommendations, $actions] = self::recommendations(
            $forecast,
            $latest,
            $demand,
            $readiness
        );

        $inputHash = hash('sha256', json_encode([
            'dss-v1',
            $forecast->dataset_hash,
            (int) $forecast->year,
            (int) $forecast->month,
            (float) $forecast->predicted_consumption_kwh,
            (float) $forecast->change_percent,
            $records->map(fn ($row) => array_map(
                fn ($field) => (float) $row->{$field}, ForecastDataset::FIELDS
            ))->all(),
        ], JSON_THROW_ON_ERROR));
        $identity = [
            'forecast_year' => $forecast->year,
            'forecast_month' => $forecast->month,
        ];
        $generatedAt ??= now();
        $values = [
            'forecast_result_id' => $forecast->id,
            'input_hash' => $inputHash,
            'user_id' => $userId,
            'demand_status' => $demand,
            'readiness_level' => $readiness,
            'recommendations' => $recommendations,
            'priority_actions' => $actions,
            'basis' => [
                'predicted_consumption_kwh' => (float) $forecast->predicted_consumption_kwh,
                'average_consumption_kwh' => $average,
                'peak_demand_kw' => (float) ($latest?->peak_demand_kw ?? 0),
                'solar_irradiance' => (float) ($latest?->solar_irradiance ?? 0),
            ],
            'updated_at' => $generatedAt,
        ];

        return DB::transaction(function () use ($identity, $values, $generatedAt, $refreshTimestamp) {
            // Uniqueness protects simultaneous first requests; the row lock
            // serializes updates to the same month across forecast versions.
            $candidate = DssResult::query()->createOrFirst($identity, $values);
            $dss = DssResult::query()->lockForUpdate()->findOrFail($candidate->id);
            $action = 'Reuse DSS Result';
            if ($candidate->wasRecentlyCreated) {
                $action = 'Generate DSS Result';
            } elseif ($dss->input_hash !== $values['input_hash'] || $dss->forecast_result_id !== $values['forecast_result_id']) {
                DB::table('dss_result_revisions')->insert([
                    'dss_result_id' => $dss->id,
                    'snapshot' => json_encode($dss->getAttributes(), JSON_THROW_ON_ERROR),
                    'recorded_at' => $generatedAt,
                ]);
                $dss->forceFill($values)->save();
                $action = 'Update DSS Result';
            } elseif ($refreshTimestamp) {
                $dss->forceFill(['updated_at' => $generatedAt])->save();
                $action = 'Refresh DSS Timestamp';
            }

            AuditLogger::log(
                'Decision Support',
                $action,
                $action.' #'.$dss->id.' for '.
                    $identity['forecast_year'].'-'.str_pad((string) $identity['forecast_month'], 2, '0', STR_PAD_LEFT).
                    ': '.$dss->demand_status.' and '.$dss->readiness_level.'.'
            );

            return $dss->refresh();
        }, 3);
    }
}
