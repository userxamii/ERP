import WarehouseLayout from '@/components/WarehouseLayout';
import { Link } from '@inertiajs/react';

type Movement = {
    id: number;
    movement_type: string;
    quantity: string;
    on_hand_delta: string;
    batch_number: string | null;
    actor: string | null;
    occurred_at: string;
    reference: string | null;
};

type MaterialDetailsProps = {
    auth?: { user?: { name?: string; role?: string } };
    material: {
        id: number;
        code: string;
        name: string;
        category: string;
        unit_of_measure: string;
        physical_on_hand: string;
        usable_on_hand: string;
        reserved: string;
        available_to_use: string;
        low_stock: boolean;
    };
    batches: Array<{
        id: number;
        lot_code: string | null;
        supplier: string | null;
        received_at: string;
        manufactured_at: string | null;
        expires_at: string | null;
        age_days: number;
        days_remaining: number | null;
        quantity_on_hand: string;
        unit_of_measure: string;
        storage_location: string | null;
        quality_status: string;
        received_by: string | null;
    }>;
    movements: Movement[];
};

export default function MaterialDetails({ auth, material, batches, movements }: MaterialDetailsProps) {
    return (
        <WarehouseLayout user={auth?.user} title={material.name} description={`${material.code} · ${material.category} · ${material.unit_of_measure}`}>
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['Physical on hand', material.physical_on_hand],
                    ['Usable stock', material.usable_on_hand],
                    ['Reserved', material.reserved],
                    ['Available to use', material.available_to_use],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{label}</p>
                        <p className="mt-2 font-mono text-2xl font-bold text-slate-900">
                            {value} <span className="text-sm font-medium text-slate-500">{material.unit_of_measure}</span>
                        </p>
                    </div>
                ))}
            </div>
            {material.low_stock && (
                <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Available stock is at or below the material's minimum stock level.
                </div>
            )}

            <section className="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-slate-200 p-5">
                    <h2 className="font-semibold text-slate-900">Batch details</h2>
                    <Link href={route('warehouse.receipts.create')} className="text-sm font-medium text-blue-700 hover:underline">
                        Receive another batch
                    </Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-5 py-3">Batch / lot</th>
                                <th className="px-5 py-3">Supplier</th>
                                <th className="px-5 py-3">On hand</th>
                                <th className="px-5 py-3">Location</th>
                                <th className="px-5 py-3">Received / age</th>
                                <th className="px-5 py-3">Expires / remaining</th>
                                <th className="px-5 py-3">Review status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {batches.map((batch) => (
                                <tr key={batch.id}>
                                    <td className="px-5 py-4 font-medium">{batch.lot_code ?? '—'}</td>
                                    <td className="px-5 py-4">{batch.supplier ?? '—'}</td>
                                    <td className="px-5 py-4 font-mono">
                                        {batch.quantity_on_hand} {batch.unit_of_measure}
                                    </td>
                                    <td className="px-5 py-4">{batch.storage_location ?? 'Not assigned'}</td>
                                    <td className="px-5 py-4">
                                        {batch.received_at}
                                        <span className="block text-xs text-slate-500">{batch.age_days} days old</span>
                                    </td>
                                    <td className="px-5 py-4">
                                        {batch.expires_at ?? 'No expiry date'}
                                        {batch.days_remaining !== null && (
                                            <span className="block text-xs text-slate-500">
                                                {batch.days_remaining < 0 ? `${Math.abs(batch.days_remaining)} days overdue` : `${batch.days_remaining} days remaining`}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 capitalize">{batch.quality_status}</td>
                                </tr>
                            ))}
                            {batches.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-10 text-center text-slate-400">
                                        No batches have been received for this material.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <h2 className="border-b border-slate-200 p-5 font-semibold text-slate-900">Stock movement history</h2>
                <div className="divide-y divide-slate-100">
                    {movements.map((movement) => (
                        <div key={movement.id} className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                            <div>
                                <p className="font-medium text-slate-800">{movement.movement_type.replaceAll('_', ' ')}</p>
                                <p className="text-xs text-slate-500">
                                    {movement.batch_number ?? 'No batch'} · {movement.actor ?? 'System'} · {movement.occurred_at}
                                    {movement.reference ? ` · Ref ${movement.reference}` : ''}
                                </p>
                            </div>
                            <span className="font-mono text-slate-700">
                                {movement.on_hand_delta} {material.unit_of_measure}
                            </span>
                        </div>
                    ))}
                    {movements.length === 0 && <p className="px-5 py-8 text-center text-sm text-slate-400">No stock movements recorded.</p>}
                </div>
            </section>
        </WarehouseLayout>
    );
}
