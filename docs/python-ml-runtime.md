# Sail Python ML runtime

Sail builds `docker/8.5/Dockerfile`, a project-owned copy of the PHP 8.5 Sail
runtime. Its Python 3.12 virtual environment lives at `/opt/venv`, outside the
project bind mount. Dependencies are installed during the image build from
`Python/requirements.txt`, so recreating or rebuilding the container restores
the same pinned ML packages. The vendor runtime is not edited.

The import audit found NumPy, scikit-learn and XGBoost in `Python/algo/`;
the main training, prediction and historical prediction scripts use the Python
standard library. No requirements file existed before this change. Saved model
metadata identifies scikit-learn 1.8.0 and XGBoost 3.2.0. These versions are pinned
along with NumPy, SciPy, joblib and threadpoolctl. Linux amd64 uses the CPU-only
XGBoost distribution; ARM64 uses the standard XGBoost wheel. Both expose the
same `xgboost` imports used by this project. The published Linux ARM64 wheel
declares an NCCL dependency even for CPU inference; that dependency is also
pinned (2.28.9).

Package references: [scikit-learn 1.8.0](https://pypi.org/project/scikit-learn/1.8.0/),
[XGBoost 3.2.0](https://pypi.org/project/xgboost/3.2.0/),
[XGBoost CPU platform support](https://pypi.org/project/xgboost-cpu/3.2.0/).

`config/ml.php` reads `ML_PYTHON`; the Docker image sets it to
`/opt/venv/bin/python`. Training and next-month prediction use this setting. Analytics reads validated
persisted results and does not execute Python when a page is loaded. Outside Sail, set `ML_PYTHON` to a local virtual
environment's executable; the default is the existing Windows venv when present,
otherwise `python3`.

## Rebuild and activate

From the repository root:

```sh
docker compose build laravel.test
docker compose up -d --no-deps laravel.test
docker compose exec -T laravel.test php artisan config:clear
```

Only the application container is recreated. MySQL and its named volume are
retained. These commands do not run migrations, training, or forecast persistence.

## Read-only verification

```sh
docker compose exec -T --user sail laravel.test /opt/venv/bin/python -m pip check
docker compose exec -T --user sail laravel.test /opt/venv/bin/python -B -c 'import numpy, scipy, sklearn, xgboost, joblib, threadpoolctl; print(numpy.__version__, scipy.__version__, sklearn.__version__, xgboost.__version__)'
docker compose exec -T --user sail laravel.test /opt/venv/bin/python -B Python/predict_next_month.py Python/sample-data/monthly_dataset_2015_2026_checked.csv Python/qc_energy_model_final.pkl Python/generated_metrics.json
```

The smoke test reads the bundled dataset, trained model and metrics and prints
JSON to stdout. The sample ends in May 2026, so the target is June 2026 regardless
of the current date. It does not use the Laravel workflow that saves forecasts
and reports. No model fitting or dataset export is performed.
