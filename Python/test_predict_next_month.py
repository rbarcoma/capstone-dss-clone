import csv
import importlib.util
import json
import pickle
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).with_name('predict_next_month.py')
spec = importlib.util.spec_from_file_location('predictor', SCRIPT)
predictor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(predictor)


class DatasetForecastTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.dataset = Path(self.temp.name) / 'data.csv'
        self.model_path = Path(self.temp.name) / 'model.pkl'
        self.model = {'features': ['lag_1'], 'means': {'lag_1': 0},
                      'stds': {'lag_1': 1}, 'coefficients': [0, 1.1]}
        self.model_path.write_bytes(pickle.dumps(self.model))

    def rows(self, months):
        return [dict(year=2026, month=month, consumption_kwh=1000,
                     temperature=25, humidity=60, rainfall=10,
                     solar_irradiance=5, peak_demand_kw=100) for month in months]

    def write_rows(self, rows):
        with self.dataset.open('w') as file:
            writer = csv.DictWriter(file, fieldnames=rows[0].keys())
            writer.writeheader()
            writer.writerows(rows)

    def run_script(self):
        return subprocess.run([sys.executable, str(SCRIPT), str(self.dataset),
                               str(self.model_path), str(Path(self.temp.name) / 'metrics.json')],
                              capture_output=True, text=True)

    def test_dataset_target_and_existing_model_prediction(self):
        self.write_rows(self.rows(range(4, 10)))
        original = self.model_path.read_bytes()
        result = self.run_script()
        self.assertEqual(result.returncode, 0, result.stderr)
        output = json.loads(result.stdout)
        self.assertEqual((output['year'], output['month']), (2026, 10))
        self.assertEqual(output['predicted_consumption_kwh'], 1100)
        self.assertEqual(self.model_path.read_bytes(), original)

    def test_missing_month_does_not_jump_to_later_actuals(self):
        self.write_rows(self.rows([7, 8, 9, 11]))
        self.assertEqual(json.loads(self.run_script().stdout)['month'], 10)

    def test_incomplete_month_is_not_coerced_to_zero(self):
        rows = self.rows([7, 8, 9, 10])
        rows[-1]['consumption_kwh'] = ''
        self.write_rows(rows)
        self.assertEqual(json.loads(self.run_script().stdout)['month'], 10)

    def test_nonfinite_month_is_not_used(self):
        rows = self.rows([7, 8, 9, 10])
        rows[-1]['consumption_kwh'] = 'NaN'
        self.write_rows(rows)
        self.assertEqual(json.loads(self.run_script().stdout)['month'], 10)

    def test_complete_october_advances_to_november(self):
        self.write_rows(self.rows([7, 8, 9, 10]))
        self.assertEqual(json.loads(self.run_script().stdout)['month'], 11)

    def test_year_rollover(self):
        self.write_rows(self.rows([10, 11, 12]))
        output = json.loads(self.run_script().stdout)
        self.assertEqual((output['year'], output['month']), (2027, 1))

    def test_no_recursive_forecasts(self):
        with self.assertRaises(ValueError):
            predictor.forecast_until_period(self.model, self.rows([7, 8, 9]), 2026, 11)

    def test_missing_model_fails_without_training(self):
        self.write_rows(self.rows([7, 8, 9]))
        self.model_path.unlink()
        self.assertNotEqual(self.run_script().returncode, 0)
        self.assertFalse(self.model_path.exists())

    def test_runtime_scripts_require_explicit_paths_without_sample_fallbacks(self):
        for script in [SCRIPT, SCRIPT.with_name('train_model.py')]:
            result = subprocess.run([sys.executable, str(script)], capture_output=True, text=True)
            self.assertNotEqual(result.returncode, 0)
            self.assertIn('Explicit uploaded dataset, model, and metrics paths are required', result.stderr)


if __name__ == '__main__':
    unittest.main()
