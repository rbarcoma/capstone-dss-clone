import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { Dataset } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Search, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

export default function DataManagement({
    datasets = [],
    requiredColumns,
}: {
    datasets: Dataset[];
    requiredColumns: string[];
}) {
    const { auth, flash } = usePage().props as any;

    const [openUploadModal, setOpenUploadModal] = useState(false);

    const [search, setSearch] = useState('');
    const [currentPage, setCurrentPage] = useState(1);

    const [itemsPerPage, setItemsPerPage] = useState(10);
    const latestDatasetId = datasets[0]?.id;

    const { data, setData, post, processing, reset } = useForm<{
        dataset: File | null;
        replace_existing: boolean;
    }>({
        dataset: null,
        replace_existing: false,
    });

    const filteredDatasets = useMemo(() => {
        return datasets.filter((item) => {
            const fileName = item.original_name?.toLowerCase() ?? '';
            const type = item.type?.toLowerCase() ?? '';
            const status = item.status?.toLowerCase() ?? '';
            const uploadedBy = item.user?.name?.toLowerCase() ?? auth?.user?.name?.toLowerCase() ?? '';
            const uploadedAt = new Date(item.created_at).toLocaleString().toLowerCase();

            const matchesSearch =
                fileName.includes(search.toLowerCase()) ||
                type.includes(search.toLowerCase()) ||
                status.includes(search.toLowerCase()) ||
                uploadedBy.includes(search.toLowerCase()) ||
                uploadedAt.includes(search.toLowerCase());

            return matchesSearch;
        });
    }, [datasets, search]);

    const totalPages = Math.ceil(filteredDatasets.length / itemsPerPage);

    const paginatedDatasets = filteredDatasets.slice(
        (currentPage - 1) * itemsPerPage,
        currentPage * itemsPerPage,
    );

    const submitUpload = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const toastId = toast.loading('Uploading dataset...', {
            description: 'Please wait while your dataset is being uploaded.',
        });

        post('/admin/data-management', {
            forceFormData: true,

            onProgress: (progress) => {
                toast.loading(`Uploading dataset... ${progress?.percentage ?? 0}%`, {
                    id: toastId,
                    description: 'Please wait while your dataset is being uploaded.',
                });
            },

            onSuccess: () => {
                toast.success('Dataset uploaded successfully.', {
                    id: toastId,
                    description: 'The dataset has been validated and saved.',
                });

                reset();
                setOpenUploadModal(false);
            },

            onError: () => {
                toast.error('Upload failed.', {
                    id: toastId,
                    description: 'Please check the CSV format and try again.',
                });
            },
        });
    };

    return (
        <div className="space-y-6 p-6">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Data Management</h1>
                    <p className="text-sm text-muted-foreground">
                        Upload, validate, replace, and review electricity and climate datasets.
                    </p>
                </div>

                <Button onClick={() => setOpenUploadModal(true)}>
                    <Upload className="mr-2 h-4 w-4" />
                    Upload Dataset
                </Button>
            </div>

            {flash?.success && (
                <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}

            {flash?.error && (
                <div className="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                    {flash.error}
                </div>
            )}

            <Card className="rounded-lg">
                <CardHeader>
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <CardTitle>Uploaded Datasets Table</CardTitle>

                        <div className="flex flex-col gap-2 md:flex-row">
                            <div className="relative">
                                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    className="w-full pl-9 md:w-64"
                                    placeholder="Search datasets..."
                                    value={search}
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        setCurrentPage(1);
                                    }}
                                />
                            </div>

                            <select
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                value={itemsPerPage}
                                onChange={(event) => {
                                    setItemsPerPage(Number(event.target.value));
                                    setCurrentPage(1);
                                }}
                            >
                                <option value={10}>10</option>
                                <option value={50}>50</option>
                                <option value={100}>100</option>
                            </select>
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left">
                                <th className="px-3 py-3 font-semibold">File</th>
                                <th className="px-3 py-3 font-semibold">Type</th>
                                <th className="px-3 py-3 font-semibold">Uploaded By</th>
                                <th className="px-3 py-3 font-semibold">Uploaded At</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 font-semibold">Records</th>
                            </tr>
                        </thead>

                        <tbody>
                            {paginatedDatasets.length > 0 ? (
                                paginatedDatasets.map((item) => {
                                    const isLatest = item.id === latestDatasetId;

                                    return (
                                        <tr
                                            key={item.id}
                                            className={`border-b ${isLatest ? 'bg-primary/5 font-bold' : ''}`}
                                        >
                                            <td className="px-3 py-1">{item.original_name}</td>
                                            <td className="px-3 py-1 capitalize">
                                                {item.type === 'combined'
                                                    ? 'Electricity + Climate'
                                                    : item.type}
                                            </td>
                                            <td className="px-3 py-1">
                                                {item.user?.name ?? auth?.user?.name ?? 'Admin'}
                                            </td>
                                            <td className="px-3 py-1">
                                                {new Date(item.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-3 py-1">{item.status}</td>
                                            <td className="px-3 py-1">{item.record_count}</td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No datasets found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    <div className="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {paginatedDatasets.length} of {filteredDatasets.length} datasets
                        </p>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={currentPage === 1}
                                onClick={() => setCurrentPage(currentPage - 1)}
                            >
                                Previous
                            </Button>

                            <span className="text-sm">
                                Page {currentPage} of {totalPages || 1}
                            </span>

                            <Button
                                variant="outline"
                                size="sm"
                                disabled={currentPage === totalPages || totalPages === 0}
                                onClick={() => setCurrentPage(currentPage + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {openUploadModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
                    <div className="w-full max-w-xl rounded-xl bg-white p-6 shadow-lg">
                        <div className="mb-5 flex items-center justify-between">
                            <div>
                                <h2 className="text-xl font-semibold">Upload Dataset</h2>
                                <p className="text-sm text-muted-foreground">
                                    Upload a daily or monthly CSV containing electricity and climate data.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => setOpenUploadModal(false)}
                                className="rounded-md px-2 py-1 text-xl text-gray-500 hover:bg-gray-100"
                            >
                                ×
                            </button>
                        </div>

                        <form className="space-y-4" onSubmit={submitUpload}>
                            <Input
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(event) =>
                                    setData('dataset', event.target.files?.[0] ?? null)
                                }
                            />

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.replace_existing}
                                    onChange={(event) =>
                                        setData('replace_existing', event.target.checked)
                                    }
                                />
                                Replace existing records
                            </label>

                            <p className="text-xs text-muted-foreground">
                                Required columns: {requiredColumns.join(', ') || 'No required columns'}
                            </p>

                            <p className="text-xs text-muted-foreground">
                                Daily rows are automatically aggregated into monthly records for preprocessing and forecasting.
                            </p>

                            <p className="text-xs text-muted-foreground">
                                When replacing a file with the same name, its existing dataset entry is updated instead of duplicated.
                            </p>

                            <div className="flex justify-end gap-2 pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpenUploadModal(false)}
                                >
                                    Cancel
                                </Button>

                                <Button disabled={processing}>
                                    {processing ? 'Uploading...' : 'Upload CSV'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
