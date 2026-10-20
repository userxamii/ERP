import WarehouseLayout from '@/components/WarehouseLayout';
import { useForm, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Movement = {
    id: number;
    movement_type: string;
    quantity: string;
    on_hand_delta: string;
    material_code: string;
    material_name: string;
    batch_number: string | null;
    source_location: string | null;
    destination_location: string | null;
    reference: string | null;
    reason: string | null;
    actor: string | null;
    occurred_at: string;
};

type StockOutBatch = {
    id: number;
    lot_code: string | null;
    material_code: string;
    material_name: string;
    quantity_on_hand: string;
    unit_of_measure: string;
    storage_location: string | null;
};

type MovementsProps = {
    auth?: { user?: { name?: string; role?: string } };
    movements: {
        data: Movement[];
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search: string; type: string };
    types: Array<{ value: string; label: string }>;
    stock_out_batches: StockOutBatch[];
};

const fieldClassName = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800';

export default function Movements({ auth, movements, filters, types, stock_out_batches }: MovementsProps) {
    const [search, setSearch] = useState(filters.search);
    const [type, setType] = useState(filters.type);
    const form = useForm({
        inventory_batch_id: '',
        quantity: '',
        destination_location: '',
        reference: '',
        reason: '',
    });

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route('warehouse.movements'), { search, type }, { preserveState: true, replace: true });
    };
    const submitStockOut = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(route('warehouse.stock-outs.store'), { onSuccess: () => form.reset() });
    };

    return (
        <WarehouseLayout user={auth?.user} title="Stock Movements" description="Review append-only stock history or record an authorized stock-out.">
            <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="mb-1 font-semibold text-slate-900">Record stock-out</h2>
                <p className="mb-4 text-xs text-slate-500">Issue available stock from a single batch. Quantity is checked again while the batch is locked.</p>
                <form onSubmit={submitStockOut} className="grid gap-3 md:grid-cols-6">
                    <label className="text-xs font-medium text-slate-600 md:col-span-2">
                        Batch
                        <select required value={form.data.inventory_batch_id} onChange={(event) => form.setData('inventory_batch_id', event.target.value)} className={`${fieldClassName} mt-1`}>
                            <option value="">Select available batch</option>
                            {stock_out_batches.map((batch) => (
                                <option key={batch.id} value={batch.id}>
                                    {batch.material_code} · {batch.lot_code ?? 'No lot'} · {batch.quantity_on_hand} {batch.unit_of_measure}
                                </option>
                            ))}
                        </select>
                        {form.errors.inventory_batch_id && <span className="mt-1 block text-red-600">{form.errors.inventory_batch_id}</span>}
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Quantity
                        <input required type="number" min="0.001" step="0.001" value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} className={`${fieldClassName} mt-1`} />
                        {form.errors.quantity && <span className="mt-1 block text-red-600">{form.errors.quantity}</span>}
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Destination
                        <input required maxLength={120} value={form.data.destination_location} onChange={(event) => form.setData('destination_location', event.target.value)} className={`${fieldClassName} mt-1`} />
                        {form.errors.destination_location && <span className="mt-1 block text-red-600">{form.errors.destination_location}</span>}
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Reference
                        <input maxLength={120} value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} className={`${fieldClassName} mt-1`} />
                        {form.errors.reference && <span className="mt-1 block text-red-600">{form.errors.reference}</span>}
                    </label>
                    <label className="text-xs font-medium text-slate-600">
                        Reason
                        <input required maxLength={1000} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={`${fieldClassName} mt-1`} />
                        {form.errors.reason && <span className="mt-1 block text-red-600">{form.errors.reason}</span>}
                    </label>
                    <div className="flex items-end">
                        <button type="submit" disabled={form.processing || stock_out_batches.length === 0} className="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50">
                            {form.processing ? 'Recording…' : 'Record stock-out'}
                        </button>
                    </div>
                </form>
                {stock_out_batches.length === 0 && <p className="mt-3 text-xs text-amber-700">There are no available, unexpired batches to issue.</p>}
            </section>

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <form onSubmit={submitFilters} className="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row">
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search material, batch, reference, or reason" className={`${fieldClassName} sm:flex-1`} />
                    <select value={type} onChange={(event) => setType(event.target.value)} className={fieldClassName}>
                        <option value="all">All movement types</option>
                        {types.map((movementType) => (
                            <option key={movementType.value} value={movementType.value}>
                                {movementType.label}
                            </option>
                        ))}
                    </select>
                    <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Filter
                    </button>
                </form>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-5 py-3">Date / reference</th>
                                <th className="px-5 py-3">Movement</th>
                                <th className="px-5 py-3">Material / batch</th>
                                <th className="px-5 py-3">Quantity</th>
                                <th className="px-5 py-3">From / to</th>
                                <th className="px-5 py-3">Reason / person</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {movements.data.map((movement) => (
                                <tr key={movement.id}>
                                    <td className="px-5 py-4">
                                        {movement.occurred_at}
                                        <span className="block text-xs text-slate-500">{movement.reference ?? 'No reference'}</span>
                                    </td>
                                    <td className="px-5 py-4 capitalize">{movement.movement_type.replaceAll('_', ' ')}</td>
                                    <td className="px-5 py-4">
                                        {movement.material_name}
                                        <span className="block text-xs text-slate-500">{movement.material_code} · {movement.batch_number ?? 'No batch'}</span>
                                    </td>
                                    <td className="px-5 py-4 font-mono">{movement.on_hand_delta}</td>
                                    <td className="px-5 py-4">
                                        {movement.source_location ?? '—'}
                                        <span className="block text-xs text-slate-500">to {movement.destination_location ?? '—'}</span>
                                    </td>
                                    <td className="px-5 py-4">
                                        {movement.reason ?? '—'}
                                        <span className="block text-xs text-slate-500">{movement.actor ?? 'System'}</span>
                                    </td>
                                </tr>
                            ))}
                            {movements.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-slate-400">
                                        No stock movements match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                    <span>
                        Showing {movements.from ?? 0}–{movements.to ?? 0} of {movements.total} movements
                    </span>
                    <div className="flex gap-1">
                        {movements.links.map((link, index) => (
                            <button
                                key={`${link.label}-${index}`}
                                type="button"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                className={`rounded border px-2.5 py-1 ${link.active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white disabled:opacity-40'}`}
                            >
                                {link.label.replace('&laquo;', '«').replace('&raquo;', '»')}
                            </button>
                        ))}
                    </div>
                </div>
            </section>
        </WarehouseLayout>
    );
}
