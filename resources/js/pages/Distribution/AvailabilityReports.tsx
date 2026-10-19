import DistributionFilters from '@/components/DistributionFilters';
import DistributionLayout from '@/components/DistributionLayout';

type MaterialRow = {
    id: number;
    code: string;
    name: string;
    category: string;
    unit: string;
    approved_quantity: string;
    usable_stock: string;
    open_inbound: string;
    shortage_quantity: string;
    risk: string;
    requests: Array<{ request_number: string; required_date: string | null; approved_quantity: string }>;
};

type AvailabilityReportsProps = {
    auth?: { user?: { name?: string; role?: string } };
    filters: { from: string; to: string; category: number | string; search: string; risk: string };
    categories: Array<{ id: number; name: string }>;
    summary: {
        approved_quantity: string;
        usable_stock: string;
        open_inbound: string;
        shortage_materials: number;
        risk_materials: number;
    };
    materials: MaterialRow[];
};

export default function AvailabilityReports({ auth, filters, categories, summary, materials }: AvailabilityReportsProps) {
    return (
        <DistributionLayout user={auth?.user} title="Availability Reports" description="Compare approved material demand, usable inventory, open supply, and production shortage risk for a selected period.">
            <DistributionFilters routeName="distribution.reports" filters={filters} categories={categories} />

            <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {[
                    ['Approved quantity', summary.approved_quantity],
                    ['Usable stock', summary.usable_stock],
                    ['Open inbound', summary.open_inbound],
                    ['Shortage materials', summary.shortage_materials],
                    ['Materials at risk', summary.risk_materials],
                ].map(([label, value]) => (
                    <article key={label} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold text-slate-500 uppercase">{label}</p>
                        <p className="mt-2 font-mono text-xl font-bold text-slate-900">{value}</p>
                    </article>
                ))}
            </div>

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 p-5">
                    <h2 className="font-semibold text-slate-900">Material supply position</h2>
                    <p className="mt-1 text-xs text-slate-500">Approved demand is based on approval dates within {filters.from}–{filters.to}; stock and inbound quantities reflect the current position.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-5 py-3">Material</th>
                                <th className="px-5 py-3">Approved quantity</th>
                                <th className="px-5 py-3">Usable stock</th>
                                <th className="px-5 py-3">Open inbound</th>
                                <th className="px-5 py-3">Shortage after inbound</th>
                                <th className="px-5 py-3">Risk</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {materials.map((material) => (
                                <tr key={material.id} className="align-top">
                                    <td className="px-5 py-4">
                                        <p className="font-medium text-slate-900">{material.name}</p>
                                        <p className="text-xs text-slate-500">{material.code} · {material.category}</p>
                                        {material.requests.length > 0 && (
                                            <details className="mt-2 text-xs text-blue-700">
                                                <summary className="cursor-pointer">Approved requests ({material.requests.length})</summary>
                                                <ul className="mt-1 space-y-1 text-slate-600">
                                                    {material.requests.map((item, index) => (
                                                        <li key={`${item.request_number}-${index}`}>
                                                            {item.request_number}: {item.approved_quantity} {material.unit}
                                                            {item.required_date ? ` · required ${item.required_date}` : ''}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </details>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 font-mono">{material.approved_quantity} {material.unit}</td>
                                    <td className="px-5 py-4 font-mono">{material.usable_stock} {material.unit}</td>
                                    <td className="px-5 py-4 font-mono">{material.open_inbound} {material.unit}</td>
                                    <td className={`px-5 py-4 font-mono ${material.risk === 'shortage' ? 'font-semibold text-red-700' : ''}`}>
                                        {material.shortage_quantity} {material.unit}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${material.risk === 'shortage' ? 'bg-red-100 text-red-700' : material.risk === 'low_stock' ? 'bg-amber-100 text-amber-800' : material.risk === 'inbound' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                            {material.risk.replaceAll('_', ' ')}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                            {materials.length === 0 && (
                                <tr><td colSpan={6} className="px-5 py-12 text-center text-slate-500">No material availability data matches these filters.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </DistributionLayout>
    );
}
