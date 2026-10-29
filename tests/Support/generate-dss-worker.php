<?php

use App\Models\ForecastResult;
use App\Support\EnergyDss;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.'.config('database.default').'.database') !== 'testing') {
    throw new RuntimeException('Concurrent DSS checks require the isolated testing database.');
}
$deadline = microtime(true) + 15;
while (! file_exists($argv[2])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent DSS worker timed out.');
    }
    usleep(10000);
}
echo EnergyDss::generateForForecast(ForecastResult::findOrFail($argv[1]))->id;
