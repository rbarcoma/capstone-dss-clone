<?php

namespace App\Support;

class MonthlyDataQuality
{
    public static function validPeriod(array $row): bool
    {
        return self::integer($row['year'] ?? null)
            && self::integer($row['month'] ?? null)
            && checkdate((int) $row['month'], 1, (int) $row['year']);
    }

    /** A monthly total, or every distinct calendar day, with valid input values. */
    public static function isComplete(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        $days = [];
        $daily = array_key_exists('day', $rows[0]);
        foreach ($rows as $row) {
            if (! self::validValues($row)
                || (int) $row['year'] !== (int) $rows[0]['year']
                || (int) $row['month'] !== (int) $rows[0]['month']) {
                return false;
            }
            if (isset($row['is_complete']) && ! filter_var($row['is_complete'], FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }
            if ($daily) {
                $day = (int) ($row['day'] ?? 0);
                if (! self::integer($row['day'] ?? null) || ! checkdate((int) $row['month'], $day, (int) $row['year']) || isset($days[$day])) {
                    return false;
                }
                $days[$day] = true;
            }
        }

        return $daily
            ? count($days) === cal_days_in_month(CAL_GREGORIAN, (int) $rows[0]['month'], (int) $rows[0]['year'])
            : count($rows) === 1;
    }

    public static function validValues(array $row): bool
    {
        if (! self::validPeriod($row)) {
            return false;
        }
        foreach (['consumption_kwh', 'peak_demand_kw', 'temperature', 'humidity', 'rainfall', 'solar_irradiance'] as $field) {
            $value = $row[$field] ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value)
                || ($field !== 'temperature' && (float) $value < 0)) {
                return false;
            }
        }

        return ! array_key_exists('day', $row)
            || (self::integer($row['day']) && checkdate((int) $row['month'], (int) $row['day'], (int) $row['year']));
    }

    private static function integer(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && floor((float) $value) === (float) $value;
    }
}
