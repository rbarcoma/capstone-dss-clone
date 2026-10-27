<?php

namespace App\Support;

use App\Models\ClimateRecord;
use App\Models\ProcessedRecord;
use RuntimeException;

class EnergyPreprocessor
{
    public static function run(): int
    {
        $consumption = ForecastDataset::uploadedActuals();

        $climate = ClimateRecord::query()
            ->get()
            ->keyBy(fn ($record) => "{$record->year}-{$record->month}");

        ProcessedRecord::query()->delete();

        if ($consumption->count() < 3) {
            throw new RuntimeException('At least 3 monthly consumption records are required.');
        }

        $rows = [];

        foreach ($consumption as $index => $record) {
            $climateRecord = $climate->get("{$record->year}-{$record->month}");

            if (! $climateRecord || $climateRecord->dataset_id !== $record->dataset_id
                || ! MonthlyDataQuality::validValues(array_merge($climateRecord->toArray(), $record->toArray()))) {
                throw new RuntimeException('Valid uploaded consumption and climate values are required for preprocessing.');
            }

            $rows[] = [
                'year' => $record->year,
                'month' => $record->month,
                'consumption_kwh' => (float) $record->consumption_kwh,
                'temperature' => (float) $climateRecord->temperature,
                'humidity' => (float) $climateRecord->humidity,
                'rainfall' => (float) $climateRecord->rainfall,
                'solar_irradiance' => (float) $climateRecord->solar_irradiance,
                'peak_demand_kw' => (float) $record->peak_demand_kw,
                'lag_1' => (float) ($consumption[$index - 1]->consumption_kwh ?? $record->consumption_kwh),
                'lag_2' => (float) ($consumption[$index - 2]->consumption_kwh ?? $record->consumption_kwh),
                'trend' => $index + 1,
                'month_sin' => sin(2 * pi() * $record->month / 12),
                'month_cos' => cos(2 * pi() * $record->month / 12),
            ];
        }

        foreach ($rows as $row) {
            ProcessedRecord::create($row);
        }

        return count($rows);
    }
}
