import { StatCard } from '@/components/dss/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { useMemo, useState } from 'react';

const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function formatNumber(value: number | null | undefined) {
    if (value === null || value === undefined) {
        return '-';
    }

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
    }).format(value);
}

function formatChartData(data: any[]) {
    return data.map((item) => ({
        label:
            item.label ??
            item.period ??
            item.month ??
            item.year ??
            `${item.year ?? ''}-${String(item.month ?? '').padStart(2, '0')}`,
        value:
            item.value ??
            item.consumption_kwh ??
            item.average_consumption ??
            item.total_consumption ??
            item.predicted_consumption_kwh ??
            0,
    }));
}

function formatCompactNumber(value: number) {
    return new Intl.NumberFormat('en-US', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

function ActualPredictedTooltip({ active, payload }: any) {
    if (!active || !payload?.length) {
        return null;
    }

    const item = payload[0]?.payload;

    return (
        <div className="rounded-lg border bg-popover p-3 text-sm text-popover-foreground shadow-md">
            <div className="mb-2 font-medium">
                {monthNames[(item?.month ?? 1) - 1]} {item?.year}
            </div>
            <div className="space-y-1 text-muted-foreground">
                <div>
                    Actual Value:{' '}
                    <span className="font-medium text-emerald-700">
                        {formatNumber(item?.actual)} kWh
                    </span>
                </div>
                <div>
                    Predicted Value:{' '}
                    <span className="font-medium text-blue-700">
                        {formatNumber(item?.predicted)} kWh
                    </span>
                </div>
                <div>
                    Accuracy:{' '}
                    <span className="font-medium text-foreground">
                        {item?.accuracy === null || item?.accuracy === undefined
                            ? '-'
                            : `${formatNumber(item.accuracy)}%`}
                    </span>
                </div>
            </div>
        </div>
    );
}

function ActualPredictedChart({
    data,
    showActual,
    showPredicted,
}: {
    data: any[];
    showActual: boolean;
    showPredicted: boolean;
}) {
    const hasVisibleData = data.some(
        (item) =>
            (showActual && item.actual !== null) ||
            (showPredicted && item.predicted !== null),
    );

    if (!hasVisibleData) {
        return (
            <div className="flex h-[320px] items-center justify-center rounded-lg border border-dashed bg-muted/20 px-6 text-center">
                <div>
                    <p className="font-medium">No chart data available</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Try another month or year, or generate a forecast first
                        to show predicted data.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="h-[320px] w-full">
            <ResponsiveContainer width="100%" height="100%">
                <LineChart
                    data={data}
                    margin={{ top: 12, right: 18, left: 4, bottom: 8 }}
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeDasharray="3 3"
                    />
                    <XAxis
                        dataKey="label"
                        axisLine={{ stroke: 'var(--border)' }}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                        tickLine={false}
                        tickMargin={10}
                    />
                    <YAxis
                        axisLine={{ stroke: 'var(--border)' }}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                        tickFormatter={(value) =>
                            formatCompactNumber(Number(value))
                        }
                        tickLine={false}
                    />
                    <Tooltip content={<ActualPredictedTooltip />} />
                    <Legend />
                    {showActual && (
                        <Line
                            type="monotone"
                            dataKey="actual"
                            name="Actual Data"
                            stroke="#059669"
                            strokeWidth={3}
                            dot={{ r: 3, strokeWidth: 2 }}
                            activeDot={{ r: 6 }}
                            connectNulls={false}
                            animationDuration={700}
                        />
                    )}
                    {showPredicted && (
                        <Line
                            type="monotone"
                            dataKey="predicted"
                            name="Predicted Data"
                            stroke="#2563eb"
                            strokeWidth={3}
                            dot={{ r: 3, strokeWidth: 2 }}
                            activeDot={{ r: 6 }}
                            connectNulls={false}
                            animationDuration={700}
                        />
                    )}
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}

function AnalyticsChart({
    data,
    gradientId,
    valueLabel = 'Consumption',
}: {
    data: any[];
    gradientId: string;
    valueLabel?: string;
}) {
    const chartData = formatChartData(data);

    return (
        <div className="h-[300px] w-full">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart
                    data={chartData}
                    margin={{ top: 12, right: 18, left: 4, bottom: 8 }}
                >
                    <defs>
                        <linearGradient
                            id={gradientId}
                            x1="0"
                            x2="0"
                            y1="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor="#059669"
                                stopOpacity={0.28}
                            />
                            <stop
                                offset="95%"
                                stopColor="#059669"
                                stopOpacity={0.02}
                            />
                        </linearGradient>
                    </defs>
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeDasharray="3 3"
                    />
                    <XAxis
                        dataKey="label"
                        axisLine={{ stroke: 'var(--border)' }}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                        tickLine={false}
                        tickMargin={10}
                    />
                    <YAxis
                        axisLine={{ stroke: 'var(--border)' }}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                        tickFormatter={(value) =>
                            formatCompactNumber(Number(value))
                        }
                        tickLine={false}
                    />
                    <Tooltip
                        content={({ active, payload, label }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            return (
                                <div className="rounded-lg border bg-popover p-3 text-sm text-popover-foreground shadow-md">
                                    <div className="mb-1 font-medium">
                                        {label}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {valueLabel}:{' '}
                                        <span className="font-medium text-emerald-700">
                                            {formatNumber(
                                                Number(payload[0]?.value ?? 0),
                                            )}{' '}
                                            kWh
                                        </span>
                                    </div>
                                </div>
                            );
                        }}
                    />
                    <Area
                        type="monotone"
                        dataKey="value"
                        stroke="#059669"
                        strokeWidth={3}
                        fill={`url(#${gradientId})`}
                        dot={{ r: 3, strokeWidth: 2 }}
                        activeDot={{ r: 6 }}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}

export default function Analytics({
    actualPredictedTrend = [],
    filterOptions = { years: [], months: [] },
    yearlyComparison = [],
    seasonalPattern = [],
    summary,
}: any) {
    const [infoModal, setInfoModal] = useState<{
        title: string;
        content: string;
    } | null>(null);
    const [selectedMonth, setSelectedMonth] = useState('all');
    const [selectedYear, setSelectedYear] = useState('all');
    const [showActual, setShowActual] = useState(true);
    const [showPredicted, setShowPredicted] = useState(true);

    const filteredActualPredictedTrend = useMemo(() => {
        return actualPredictedTrend.filter((item: any) => {
            const monthMatches =
                selectedMonth === 'all' || String(item.month) === selectedMonth;
            const yearMatches =
                selectedYear === 'all' || String(item.year) === selectedYear;

            return monthMatches && yearMatches;
        });
    }, [actualPredictedTrend, selectedMonth, selectedYear]);

    const hasPredictedForSelection = filteredActualPredictedTrend.some(
        (item: any) => item.predicted !== null,
    );
    const analyticsInfo = {
        averageConsumption: {
            title: 'Average Consumption',
            content:
                'Average Consumption represents the typical amount of electricity used within a specific period. It is calculated by adding all Consumption kWh values and dividing the total by the number of records. \n\nFormula: \naverage_consumption = total_consumption_kwh / number_of_records. \n\nThis helps identify the normal electricity usage trend over time.',
        },
        highestConsumption: {
            title: 'Highest Consumption',
            content:
                'Highest Consumption refers to the highest recorded electricity usage found in the dataset. It is obtained by identifying the maximum value in the consumption_kwh column. This metric helps determine the period with the greatest electricity demand.',
        },
        averagePeakDemand: {
            title: 'Average Peak Demand',
            content:
                'Average Peak Demand represents the average of the highest electricity demand values recorded in the dataset. It is calculated by adding all Peak Demand kW values and dividing the total by the number of records. \n\nFormula: \npeak_demand_kw = total_peak_demand_kw / number_of_records. \n\nThis helps measure the typical maximum power demand.',
        },
        averageSolarIrradiance: {
            title: 'Average Solar Irradiance',
            content:
                'Average Solar Irradiance represents the average amount of solar energy received in a specific location. It is calculated by adding all solar_irradiance values and dividing the total by the number of records. \n\nFormula: \nsolar_irradiance = total_solar_irradiance / number_of_records. \n\nThis helps assess the solar energy potential in the dataset.',
        },
    };

    return (
        <div className="space-y-6 p-6">
            <div>
                <h1 className="text-2xl font-semibold">
                    Consumption Analytics
                </h1>
                <p className="text-sm text-muted-foreground">
                    Historical consumption, seasonal behavior, and peak demand
                    insights.
                </p>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <StatCard
                    title="Average Consumption"
                    value={
                        summary?.average_consumption == null
                            ? 'No data'
                            : `${summary.average_consumption} kWh`
                    }
                    onInfoClick={() =>
                        setInfoModal(analyticsInfo.averageConsumption)
                    }
                />

                <StatCard
                    title="Highest Consumption"
                    value={
                        summary?.highest_consumption == null
                            ? 'No data'
                            : `${summary.highest_consumption} kWh`
                    }
                    helper={
                        summary?.highest_consumption_year &&
                        summary?.highest_consumption_month
                            ? `${summary.highest_consumption_year}-${String(
                                  summary.highest_consumption_month,
                              ).padStart(2, '0')}`
                            : ''
                    }
                    onInfoClick={() =>
                        setInfoModal(analyticsInfo.highestConsumption)
                    }
                />

                <StatCard
                    title="Average Peak Demand"
                    value={
                        summary?.average_peak_demand == null
                            ? 'No data'
                            : `${summary.average_peak_demand} kW`
                    }
                    onInfoClick={() =>
                        setInfoModal(analyticsInfo.averagePeakDemand)
                    }
                />

                <StatCard
                    title="Avg Solar Irradiance"
                    value={summary?.average_solar_irradiance ?? 'No data'}
                    onInfoClick={() =>
                        setInfoModal(analyticsInfo.averageSolarIrradiance)
                    }
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <Card className="rounded-lg xl:col-span-2">
                    <CardHeader className="gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>
                                Actual vs Predicted Consumption
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Actual consumption compared with historical
                                model estimates and the saved next-month
                                forecast. Historical estimates use the training
                                data and do not measure future forecast
                                accuracy.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Select
                                value={selectedMonth}
                                onValueChange={setSelectedMonth}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Month" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Months
                                    </SelectItem>
                                    {filterOptions.months.map(
                                        (month: number) => (
                                            <SelectItem
                                                key={month}
                                                value={String(month)}
                                            >
                                                {monthNames[month - 1]}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <Select
                                value={selectedYear}
                                onValueChange={setSelectedYear}
                            >
                                <SelectTrigger className="w-[130px]">
                                    <SelectValue placeholder="Year" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Years
                                    </SelectItem>
                                    {filterOptions.years.map((year: number) => (
                                        <SelectItem
                                            key={year}
                                            value={String(year)}
                                        >
                                            {year}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={showActual}
                                    onCheckedChange={(checked) =>
                                        setShowActual(Boolean(checked))
                                    }
                                />
                                Actual Data
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={showPredicted}
                                    onCheckedChange={(checked) =>
                                        setShowPredicted(Boolean(checked))
                                    }
                                />
                                Predicted Data
                            </label>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ActualPredictedChart
                            data={filteredActualPredictedTrend}
                            showActual={showActual}
                            showPredicted={showPredicted}
                        />
                        {showPredicted && !hasPredictedForSelection && (
                            <div className="mt-4 rounded-md border border-dashed bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
                                No predicted data is available for the selected
                                month or year.
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle>Yearly Comparison</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AnalyticsChart
                            data={yearlyComparison}
                            gradientId="analyticsYearlyComparison"
                            valueLabel="Average Consumption"
                        />
                    </CardContent>
                </Card>

                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle>Seasonal Pattern Analysis</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AnalyticsChart
                            data={seasonalPattern}
                            gradientId="analyticsSeasonalPattern"
                            valueLabel="Average Consumption"
                        />
                    </CardContent>
                </Card>
            </div>

            <Dialog open={!!infoModal} onOpenChange={() => setInfoModal(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{infoModal?.title}</DialogTitle>
                    </DialogHeader>

                    <p className="text-sm leading-relaxed whitespace-pre-line text-muted-foreground">
                        {infoModal?.content}
                    </p>
                </DialogContent>
            </Dialog>
        </div>
    );
}
