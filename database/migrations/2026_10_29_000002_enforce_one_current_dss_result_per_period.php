<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dss_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('forecast_year')->default(0);
            $table->unsignedTinyInteger('forecast_month')->default(0);
            $table->string('input_hash', 64)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable()->index();
        });

        DB::transaction(function () {
            $keepers = [];
            $archivedAt = now();
            DB::table('dss_results')->orderByDesc('updated_at')->orderByDesc('id')
                ->get()->each(function ($result) use (&$keepers, $archivedAt) {
                    $forecast = DB::table('forecast_results')->find($result->forecast_result_id);
                    if (! $forecast) {
                        DB::table('dss_results')->where('id', $result->id)->update(['archived_at' => $archivedAt]);

                        return;
                    }
                    $key = $forecast->year.'-'.$forecast->month;
                    $values = ['forecast_year' => $forecast->year, 'forecast_month' => $forecast->month];
                    if (isset($keepers[$key])) {
                        $values['archived_at'] = $archivedAt;
                        $values['superseded_by_id'] = $keepers[$key];
                    } else {
                        $keepers[$key] = $result->id;
                    }
                    // Original IDs, result values and generation timestamps stay intact.
                    DB::table('dss_results')->where('id', $result->id)->update($values);
                });
        });

        Schema::table('dss_results', function (Blueprint $table) {
            // Archived rows use NULL, allowing their original records to remain.
            $table->unsignedTinyInteger('current_slot')
                ->virtualAs('CASE WHEN archived_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['forecast_year', 'forecast_month', 'current_slot'], 'dss_current_period_unique');
        });

        // A previous rollback may have retained this table to preserve history.
        if (! Schema::hasTable('dss_result_revisions')) {
            Schema::create('dss_result_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dss_result_id')->constrained()->restrictOnDelete();
                $table->json('snapshot');
                $table->timestamp('recorded_at');
            });
        }
    }

    public function down(): void
    {
        // Retain revision snapshots even if the schema changes are rolled back.
        Schema::table('dss_results', function (Blueprint $table) {
            $table->dropUnique('dss_current_period_unique');
            $table->dropColumn('current_slot');
            $table->dropIndex(['superseded_by_id']);
            $table->dropColumn(['forecast_year', 'forecast_month', 'input_hash', 'archived_at', 'superseded_by_id']);
        });
    }
};
