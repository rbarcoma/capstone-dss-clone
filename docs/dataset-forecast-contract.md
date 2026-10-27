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
