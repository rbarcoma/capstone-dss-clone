import csv
import json
import math
import os
import pickle
import sys
from pathlib import Path




def read_rows(dataset_path):
    with open(dataset_path, newline="", encoding="utf-8") as file:
        rows = sorted(csv.DictReader(file), key=lambda row: (float(row["year"]), float(row["month"])))

    valid_rows = []
    expected = None
    for row in rows:
        try:
            parsed = {key: float(value) for key, value in row.items()}
            year, month = parsed["year"], parsed["month"]
            required = ["consumption_kwh", "temperature", "humidity", "rainfall", "solar_irradiance", "peak_demand_kw"]
            if (not year.is_integer() or not month.is_integer() or not 1 <= year <= 9999
                    or not 1 <= month <= 12 or not all(math.isfinite(v) for v in parsed.values())
                    or any(parsed[key] < 0 for key in required if key != "temperature")
                    or parsed.get("is_complete", 1) != 1):
                break
        except (ValueError, TypeError, KeyError):
            break
        if expected and (int(year), int(month)) != expected:
            break
        valid_rows.append(parsed)
        expected = next_period(int(year), int(month))

    return add_derived_features(valid_rows)


def add_derived_features(rows):
    for index, row in enumerate(rows):
        month = int(row["month"])
        previous_row = rows[index - 1] if index > 0 else row
        second_previous_row = rows[index - 2] if index > 1 else row


        row.setdefault("lag_1", previous_row["consumption_kwh"])
        row.setdefault("lag_2", second_previous_row["consumption_kwh"])
        row.setdefault("trend", float(index + 1))
        row.setdefault("month_sin", math.sin(2 * math.pi * month / 12))
        row.setdefault("month_cos", math.cos(2 * math.pi * month / 12))


    return rows




def predict_row(model, row):
    values = [1.0]
    for feature in model["features"]:
        values.append((row[feature] - model["means"][feature]) / model["stds"][feature])


    return sum(coefficient * value for coefficient, value in zip(model["coefficients"], values))




def next_period(year, month):
    if month == 12:
        return year + 1, 1


    return year, month + 1




def trend_for_period(rows, target_year, target_month):
    first_row = rows[0]
    first_year = int(first_row["year"])
    first_month = int(first_row["month"])
    target_index = ((target_year - first_year) * 12) + (target_month - first_month)


    return float(target_index + 1)




def average_context_for_month(rows, target_month):
    context_features = [
        "temperature",
        "humidity",
        "rainfall",
        "solar_irradiance",
        "peak_demand_kw",
    ]
    same_month_rows = [row for row in rows if int(row["month"]) == target_month]
    source_rows = same_month_rows or rows[-12:] or rows


    return {
        feature: sum(row[feature] for row in source_rows) / len(source_rows)
        for feature in context_features
    }




def build_forecast_input(rows, target_year, target_month):
    context = average_context_for_month(rows, target_month)
    last_row = rows[-1]
    second_last_row = rows[-2]


    return {
        "year": float(target_year),
        "month": float(target_month),
        "temperature": context["temperature"],
        "humidity": context["humidity"],
        "rainfall": context["rainfall"],
        "solar_irradiance": context["solar_irradiance"],
        "peak_demand_kw": context["peak_demand_kw"],
        "lag_1": last_row["consumption_kwh"],
        "lag_2": second_last_row["consumption_kwh"],
        "trend": trend_for_period(rows, target_year, target_month),
        "month_sin": math.sin(2 * math.pi * target_month / 12),
        "month_cos": math.cos(2 * math.pi * target_month / 12),
    }




def forecast_until_period(model, rows, target_year, target_month):
    # Keep the entry point, but never synthesize intervening actual months.
    expected = next_period(int(rows[-1]["year"]), int(rows[-1]["month"]))
    if (target_year, target_month) != expected:
        raise ValueError("Only the immediate next dataset month can be forecast.")
    forecast_input = build_forecast_input(rows, target_year, target_month)
    return {**forecast_input, "consumption_kwh": float(predict_row(model, forecast_input))}


def main():
    if len(sys.argv) != 4:
        raise ValueError("Explicit uploaded dataset, model, and metrics paths are required.")
    dataset_path, model_path, metrics_path = map(Path, sys.argv[1:4])


    rows = read_rows(dataset_path)
    if len(rows) < 3:
        raise ValueError("At least 3 processed rows are required to predict the next month.")


    with open(model_path, "rb") as model_file:
        model = pickle.load(model_file)

    next_year, next_month = next_period(int(rows[-1]["year"]), int(rows[-1]["month"]))
    forecast_row = forecast_until_period(model, rows, next_year, next_month)
    prediction = round(float(forecast_row["consumption_kwh"]), 2)
    if not math.isfinite(prediction) or prediction < 0:
        raise ValueError("The model did not produce a valid nonnegative consumption forecast.")
    previous = round(float(forecast_row["lag_1"]), 2)
    change_percent = round(((prediction - previous) / previous) * 100, 2) if previous else 0
    metrics = {}


    if os.path.exists(metrics_path):
        with open(metrics_path, "r", encoding="utf-8") as metrics_file:
            metrics = json.load(metrics_file)


    print(json.dumps({
        "year": next_year,
        "month": next_month,
        "predicted_consumption_kwh": prediction,
        "previous_consumption_kwh": previous,
        "change_percent": change_percent,
        "mae": metrics.get("mae"),
        "rmse": metrics.get("rmse"),
        "r2_score": metrics.get("r2_score"),
        "model_type": metrics.get("model_type", model.get("model_type", "Linear Regression")),
    }))


if __name__ == "__main__":
    main()
