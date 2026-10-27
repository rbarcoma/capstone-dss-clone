import json
import math
import pickle
import sys
from pathlib import Path

from predict_next_month import predict_row, read_rows


def main():
    if len(sys.argv) < 3:
        raise ValueError("Explicit uploaded dataset and model paths are required.")
    dataset_path = Path(sys.argv[1])
    model_path = Path(sys.argv[2])

    rows = read_rows(dataset_path)
    if len(rows) < 6:
        raise ValueError("At least 6 consecutive complete months are required for historical predictions.")

    with open(model_path, "rb") as model_file:
        model = pickle.load(model_file)

    predictions = []
    for row in rows:
        actual = float(row["consumption_kwh"])
        estimate = float(predict_row(model, row))
        if not math.isfinite(estimate):
            raise ValueError("Historical prediction must be finite.")
        predicted = round(max(0, estimate), 2)
        accuracy = None

        if actual > 0:
            accuracy = round(max(0, 100 - (abs(actual - predicted) / actual * 100)), 2)

        year = int(row["year"])
        month = int(row["month"])
        predictions.append({
            "period": f"{year}-{month:02d}",
            "year": year,
            "month": month,
            "predicted": predicted,
            "accuracy": accuracy,
        })

    print(json.dumps(predictions))


if __name__ == "__main__":
    main()
