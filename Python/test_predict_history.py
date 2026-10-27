import csv
import json
import pickle
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


SCRIPT = Path(__file__).with_name('predict_history.py')


class HistoricalPredictionTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.dataset = Path(self.temp.name) / 'uploaded.csv'
        self.model = Path(self.temp.name) / 'uploaded.pkl'
        self.model.write_bytes(pickle.dumps({
            'features': ['lag_1'], 'means': {'lag_1': 0},
            'stds': {'lag_1': 1}, 'coefficients': [0, 1.1],
        }))

    def write_rows(self, consumption=1000, months=range(4, 10)):
        with self.dataset.open('w') as file:
            writer = csv.DictWriter(file, fieldnames=[
                'year', 'month', 'consumption_kwh', 'temperature', 'humidity',
                'rainfall', 'solar_irradiance', 'peak_demand_kw',
            ])
            writer.writeheader()
            writer.writerows(dict(
                year=2026, month=month, consumption_kwh=consumption,
                temperature=25, humidity=60, rainfall=10,
                solar_irradiance=5, peak_demand_kw=100,
            ) for month in months)

    def run_script(self):
        return subprocess.run([
            sys.executable, str(SCRIPT), str(self.dataset), str(self.model),
        ], capture_output=True, text=True)

    def test_pairs_estimates_with_each_historical_month(self):
        self.write_rows()
        result = self.run_script()
        self.assertEqual(result.returncode, 0, result.stderr)
        predictions = json.loads(result.stdout)
        self.assertEqual([row['period'] for row in predictions],
                         [f'2026-{month:02d}' for month in range(4, 10)])
        self.assertTrue(all(row['predicted'] == 1100 for row in predictions))

    def test_genuine_zero_is_preserved(self):
        self.write_rows(consumption=0)
        result = self.run_script()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(all(row['predicted'] == 0 for row in json.loads(result.stdout)))

    def test_missing_invalid_empty_or_insufficient_dataset_fails(self):
        self.assertNotEqual(self.run_script().returncode, 0)
        for consumption, months in [('', range(4, 10)), ('NaN', range(4, 10)),
                                    (1000, []), (1000, range(4, 9))]:
            with self.subTest(consumption=consumption, months=months):
                self.write_rows(consumption, months)
                self.assertNotEqual(self.run_script().returncode, 0)

    def test_missing_model_fails_without_fallback(self):
        self.write_rows()
        self.model.unlink()
        self.assertNotEqual(self.run_script().returncode, 0)

    def test_explicit_paths_are_required(self):
        result = subprocess.run([sys.executable, str(SCRIPT)], capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Explicit uploaded dataset and model paths are required', result.stderr)


if __name__ == '__main__':
    unittest.main()
