# Uploaded-data forecasting and analytics

Analytics and Forecasting share `ForecastDataset` eligibility rules for admin and viewer pages. A valid dataset entry and its stored upload must exist. Orphaned records, incomplete months, invalid values, and processed rows that disagree with current consumption or climate data cannot supply chart observations or training inputs.

Forecasting requires at least six consecutive complete processed months. It trains a model from that uploaded snapshot and predicts the immediate following month in kWh. Uploads retain their automatic preprocessing and forecasting workflow; manual prediction and scheduled prediction use the same service. Training failures return an error without using a bundled model. The runtime Python scripts require explicit dataset, model, and metrics paths and have no implicit sample fallback.

Saved next-month results are identified by the uploaded source data and processed snapshot hash. Analytics and Forecasting display only saved forecasts matching the current snapshot and target. Legacy results without verified provenance and results from previous dataset versions remain stored but are hidden from these pages. Changing, replacing, invalidating, or deleting an upload therefore prevents its old predictions from appearing as current results. Deleting a dataset also removes its actual records and rebuilds preprocessing from remaining uploads.

Analytics combines valid actual observations, historical fitted model estimates, and the explicitly saved current next-month forecast. Both admin and viewer pages use the same service. For at least six consecutive complete processed months, Analytics reuses a model trained on that exact uploaded snapshot, training one if needed for existing uploads. It runs `predict_history.py` against the immutable validated snapshot and caches the estimates by the full dataset hash. Every request validates the upload before using that cache. Historical estimates are training-data fits, not out-of-sample forecasts or evidence of future forecast accuracy; generating them does not create forecast, DSS, or report records. Invalid or missing data never invokes Python or falls back to a sample/bundled model. Training or prediction failures leave actuals visible with null predictions. Missing observations and summaries are null/empty, while genuine zero consumption remains zero. Existing cards, charts, tables, and layout are retained.

Regression checks:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact
python3 -m unittest discover -s Python -p 'test_*.py'
npm run types:check
```

The feature suite includes a real Python training/prediction test using isolated uploaded fixtures, in addition to mocked failure and provenance cases. Tests use fake storage and an isolated SQLite database; they do not use the running application's data.

## One current DSS result per forecast month

DSS identity is `(forecast_year, forecast_month)`, independent of forecast version, upload ID, filename, or user. A generated `current_slot` and a database unique index enforce one current result per year and month, including concurrent requests. Normal `DssResult` queries exclude archived rows.

The dataset fingerprint uses numeric monthly actuals, climate values, completeness, and processed features. Identical uploads, renamed files, reordered records, and equivalent number formatting reuse the same forecast. Uploads, manual DSS generation, and scheduled requests share the forecasting workflow and its lock. Cached forecasts still reconcile the monthly DSS, including missing results and reversions to previously used data.

Identical reuploads and scheduled requests reuse the saved result without changing its author or generation timestamp. Explicit **Predict Next Month** clicks refresh `predicted_at` on the existing forecast; explicit **Generate DSS Result** clicks refresh `updated_at` on the existing DSS result. These timestamp-only updates preserve IDs, authors, calculation values, original creation dates, and existing report snapshots; they add audit events without adding data revisions. Inertia redirects return fresh table props and reset pagination to the first page after success. Prediction timestamps include seconds so successive clicks within a minute are visible. Changed data updates the same monthly DSS ID and saves a complete prior-row snapshot in `dss_result_revisions`. Forecast versions and saved report snapshots remain unchanged. Audit entries distinguish generation, update, and reuse. Legacy dataset fingerprints may require one forecast under the new content fingerprint; their monthly DSS identity is retained.

Migration `2026_10_29_000002_enforce_one_current_dss_result_per_period` retains the most recently updated DSS for each year/month, with the highest ID breaking ties. Other results retain their original IDs, values, forecast links and timestamps, with `archived_at` and `superseded_by_id` added. Orphaned legacy results are archived. No original DSS, forecast, report, audit entry, or revision is deleted. Inspect retained results with `DssResult::withoutGlobalScope('current')` or the raw table. Revision snapshots remain stored after rolling back this migration.

The MySQL checks use the isolated `testing` database:

```sh
docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Unit/DssPeriodMigrationTest.php tests/Unit/DssPeriodConcurrencyTest.php tests/Feature/DatasetForecastTest.php
```

The concurrency test starts six independent PHP workers to generate a result, then six more against a changed forecast for the same month. It verifies one DSS ID and exactly one prior revision. SQLite skips this MySQL-specific check; both engines run migration-preservation and repeat-upload regressions.
