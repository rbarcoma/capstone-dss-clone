<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumption_records', function (Blueprint $table) {
            // Null means legacy data: inspect the original upload before using it.
            $table->boolean('is_complete')->nullable();
        });
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->string('dataset_hash', 64)->nullable();
            $table->dropUnique(['year', 'month']);
            $table->unique(['dataset_hash', 'year', 'month']);
        });
    }

    public function down(): void
    {
        // Historical versions cannot safely be collapsed to one row per period.
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->dropUnique(['dataset_hash', 'year', 'month']);
            $table->dropColumn('dataset_hash');
        });
        Schema::table('consumption_records', fn (Blueprint $table) => $table->dropColumn('is_complete'));
    }
};
