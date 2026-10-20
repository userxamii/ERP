import WarehouseLayout from '@/components/WarehouseLayout';
import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

type AlertBatch = {
    id: number;
    material_id: number;
    batch_number: string | null;
    material_code: string;
    material_name: string;
    quantity_on_hand: string;
    unit_of_measure: string;
    storage_location: string | null;
    expires_at: string;
    days_remaining: number;
    escalation_status: string;
    quality_status: string;
    recommended_follow_up: string;
};

type AlertsProps = {
    auth?: { user?: { name?: string; role?: string } };
    batches: {
        data: AlertBatch[];
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

export default function Alerts({ auth, batches }: AlertsProps) {
    return (
        <WarehouseLayout user={auth?.user} title="Expiration Alerts" description="Monitor expiring stock and escalate expired batches for independent quality review.">
            <div className="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <AlertTriangle className="mt-0.5 shrink-0" size={18} />
                <p>Alert priority is based on expiration date. Warehouse staff can act on stock and request review, but cannot approve QA release or change system-wide alert rules.</p>
            </div>
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-5 py-3">Material / batch</th>
                                <th className="px-5 py-3">On hand</th>
                                <th className="px-5 py-3">Location</th>
                                <th className="px-5 py-3">Expiration</th>
                                <th className="px-5 py-3">Escalation</th>
                                <th className="px-5 py-3">Recommended follow-up</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {batches.data.map((batch) => (
                                <tr key={batch.id}>
                                    <td className="px-5 py-4">
                                        <Link href={route('warehouse.materials.show', batch.material_id)} className="font-medium text-blue-700 hover:underline">
                                            {batch.material_name}
                                        </Link>
                                        <span className="block text-xs text-slate-500">
                                            {batch.material_code} · {batch.batch_number ?? 'No batch number'} · QA {batch.quality_status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 font-mono">
                                        {batch.quantity_on_hand} {batch.unit_of_measure}
                                    </td>
                                    <td className="px-5 py-4">{batch.storage_location ?? 'Not assigned'}</td>
                                    <td className="px-5 py-4">
                                        {batch.expires_at}
                                        <span className={`block text-xs ${batch.days_remaining < 0 ? 'text-red-700' : 'text-slate-500'}`}>
                                            {batch.days_remaining < 0 ? `${Math.abs(batch.days_remaining)} days expired` : `${batch.days_remaining} days remaining`}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${batch.days_remaining < 0 || batch.days_remaining <= 7 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'}`}>
                                            {batch.escalation_status}
                                        </span>
                                    </td>
                                    <td className="max-w-md px-5 py-4">{batch.recommended_follow_up}</td>
                                </tr>
                            ))}
                            {batches.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-slate-400">
                                        No active batches are expired or due to expire in the next 30 days.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                    <span>
                        Showing {batches.from ?? 0}–{batches.to ?? 0} of {batches.total} alerts
                    </span>
                    <div className="flex gap-1">
                        {batches.links.map((link, index) => (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url ?? '#'}
                                aria-disabled={!link.url}
                                className={`rounded border px-2.5 py-1 ${link.active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                            >
                                {link.label.replace('&laquo;', '«').replace('&raquo;', '»')}
                            </Link>
                        ))}
                    </div>
                </div>
            </section>
        </WarehouseLayout>
    );
}
