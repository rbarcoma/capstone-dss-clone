<?php

use App\Support\MonthlyDataQuality;

function monthlyInput(array $overrides = []): array
{
    return array_merge([
        'year' => 2026, 'month' => 10, 'consumption_kwh' => 1000,
        'peak_demand_kw' => 100, 'temperature' => 25, 'humidity' => 60,
        'rainfall' => 10, 'solar_irradiance' => 5,
    ], $overrides);
}

it('requires all distinct days for daily uploads', function () {
    $days = array_map(fn ($day) => monthlyInput(['day' => $day]), range(1, 31));
    expect(MonthlyDataQuality::isComplete($days))->toBeTrue();
    array_pop($days);
    expect(MonthlyDataQuality::isComplete($days))->toBeFalse();
    $days[] = $days[0];
    expect(MonthlyDataQuality::isComplete($days))->toBeFalse();
});

it('handles leap year completeness', function () {
    $days = array_map(fn ($day) => monthlyInput(['year' => 2024, 'month' => 2, 'day' => $day]), range(1, 29));
    expect(MonthlyDataQuality::isComplete($days))->toBeTrue();
    array_pop($days);
    expect(MonthlyDataQuality::isComplete($days))->toBeFalse();
});

it('accepts a valid monthly total including zero consumption', function () {
    expect(MonthlyDataQuality::isComplete([monthlyInput(['consumption_kwh' => 0])]))->toBeTrue();
    expect(MonthlyDataQuality::isComplete([monthlyInput(['month' => '09'])]))->toBeTrue();
});

it('rejects incomplete and invalid monthly values', function (array $overrides) {
    expect(MonthlyDataQuality::isComplete([monthlyInput($overrides)]))->toBeFalse();
})->with([
    [['consumption_kwh' => null]], [['consumption_kwh' => '']], [['consumption_kwh' => -1]],
    [['consumption_kwh' => 'NaN']], [['temperature' => null]], [['month' => 13]],
    [['is_complete' => false]], [['month' => 1.5]],
]);
