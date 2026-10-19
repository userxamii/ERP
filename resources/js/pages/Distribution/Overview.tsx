import DistributionFilters from '@/components/DistributionFilters';
import DistributionLayout from '@/components/DistributionLayout';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowDownToLine, ArrowUpFromLine, Boxes, CircleAlert, BellRing } from 'lucide-react';

type Material = {
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
};

type Notice = {
    id: string;
    severity: string;
    title: string;
    detail: string;
    occurred_at: string;
    href: string;
};

type OverviewProps = {
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
    materials: Material[];
    trend: Array<{ date: string; increased: string; decreased: string; net_change: string }>;
    notices: Notice[];
};

export default function Overview({ auth, filters, categories, summary, materials, trend, notices }: OverviewProps) {
    const largestMovement = Math.max(1, ...trend.flatMap((day) => [Number(day.increased), Number(day.decreased)]));
    const cards = [
        { label: 'Approved demand', value: summary.approved_quantity, icon: Boxes, color: 'text-blue-700 bg-blue-50' },
        { label: 'Usable stock', value: summary.usable_stock, icon: ArrowDownToLine, color: 'text-emerald-700 bg-emerald-50' },
        { label: 'Open inbound supply', value: summary.open_inbound, icon: ArrowUpFromLine, color: 'text-indigo-700 bg-indigo-50' },
        { label: 'Materials with shortage risk', value: summary.shortage_materials, icon: AlertTriangle, color: 'text-amber-800 bg-amber-50' },
    ];

    return (
        <DistributionLayout user={auth?.user} title="Distribution Overview" description="Read-only summary of material availability, inbound supply, inventory trends, and production supply risks.">
            <DistributionFilters routeName="distribution.overview" filters={filters} categories={categories} />

            <section className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => {
                    const Icon = card.icon;
                    return (
                        <article key={card.label} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{card.label}</p>
                                <p className="mt-2 font-mono text-2xl font-bold text-slate-900">{card.value}</p>
                            </div>
                            <span className={`rounded-xl p-3 ${card.color}`}>
                                <Icon size={22} />
                            </span>
                        </article>
                    );
                })}
            </section>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,1fr)]">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="font-semibold text-slate-900">Inventory availability trend</h2>
                            <p className="mt-1 text-xs text-slate-500">Daily increases and reductions in usable stock · {filters.from} to {filters.to}</p>
                        </div>
                        <Link href={route('distribution.reports', filters)} className="text-sm font-medium text-blue-700 hover:underline">
                            Open availability report
                        </Link>
                    </div>
                    {trend.some((day) => Number(day.increased) > 0 || Number(day.decreased) > 0) ? (
                        <div className="overflow-x-auto">
                            <div className="flex min-w-max items-end gap-2 border-b border-slate-200 pb-2" style={{ minHeight: 210 }}>
                                {trend.map((day) => (
                                    <div key={day.date} className="flex w-12 flex-col items-center justify-end gap-1">
                                        <div className="flex h-40 items-end gap-1">
                                            <span
                                                title={`Available stock increased by ${day.increased}`}
                                                className="w-4 rounded-t bg-emerald-500"
                                                style={{ height: `${Math.max(Number(day.increased) > 0 ? 4 : 0, (Number(day.increased) / largestMovement) * 100)}%` }}
                                            />
                                            <span
                                                title={`Available stock reduced by ${day.decreased}`}
                                                className="w-4 rounded-t bg-rose-400"
                                                style={{ height: `${Math.max(Number(day.decreased) > 0 ? 4 : 0, (Number(day.decreased) / largestMovement) * 100)}%` }}
                                            />
                                        </div>
                                        <span className="text-[10px] text-slate-500">{day.date.slice(5)}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-3 flex gap-4 text-xs text-slate-600">
                                <span><i className="mr-1.5 inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500" />Stock added / released</span>
                                <span><i className="mr-1.5 inline-block h-2.5 w-2.5 rounded-sm bg-rose-400" />Stock issued / reserved</span>
                            </div>
                        </div>
                    ) : (
                        <p className="rounded-lg bg-slate-50 py-12 text-center text-sm text-slate-500">No inventory movements were recorded during this reporting period.</p>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="font-semibold text-slate-900">Supply notices</h2>
                            <p className="text-xs text-slate-500">{notices.length} active notices</p>
                        </div>
                        <Link href={route('distribution.notifications', filters)} aria-label="View all supply notifications" className="rounded-lg p-2 text-blue-700 hover:bg-blue-50">
                            <BellRing size={18} />
                        </Link>
                    </div>
                    <div className="space-y-3">
                        {notices.slice(0, 5).map((notice) => (
                            <Link key={notice.id} href={notice.href} className="block rounded-lg border border-slate-100 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                                <div className="flex items-start gap-2">
                                    <CircleAlert className={`mt-0.5 shrink-0 ${notice.severity === 'high' ? 'text-red-600' : 'text-amber-600'}`} size={16} />
                                    <div>
                                        <p className="text-sm font-medium text-slate-800">{notice.title}</p>
                                        <p className="mt-1 text-xs text-slate-500">{notice.detail}</p>
                                    </div>
                                </div>
                            </Link>
                        ))}
                        {notices.length === 0 && <p className="py-8 text-center text-sm text-slate-500">No active supply notices.</p>}
                    </div>
                </section>
            </div>

            <section className="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-slate-200 p-5">
                    <div>
                        <h2 className="font-semibold text-slate-900">Materials with potential supply risk</h2>
                        <p className="text-xs text-slate-500">Comparing period-approved demand with current usable stock and open inbound orders</p>
                    </div>
                    <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">{summary.risk_materials} at risk</span>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="px-5 py-3">Material</th>
                                <th className="px-5 py-3">Approved demand</th>
                                <th className="px-5 py-3">Usable stock</th>
                                <th className="px-5 py-3">Open inbound</th>
                                <th className="px-5 py-3">Shortage after inbound</th>
                                <th className="px-5 py-3">Supply status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {materials.filter((material) => ['shortage', 'low_stock'].includes(material.risk)).slice(0, 8).map((material) => (
                                <tr key={material.id}>
                                    <td className="px-5 py-4">
                                        <span className="font-medium text-slate-900">{material.name}</span>
                                        <span className="block text-xs text-slate-500">{material.code} · {material.category}</span>
                                    </td>
                                    <td className="px-5 py-4 font-mono">{material.approved_quantity} {material.unit}</td>
                                    <td className="px-5 py-4 font-mono">{material.usable_stock} {material.unit}</td>
                                    <td className="px-5 py-4 font-mono">{material.open_inbound} {material.unit}</td>
                                    <td className="px-5 py-4 font-mono">{material.shortage_quantity} {material.unit}</td>
                                    <td className="px-5 py-4 capitalize">{material.risk.replaceAll('_', ' ')}</td>
                                </tr>
                            ))}
                            {!materials.some((material) => ['shortage', 'low_stock'].includes(material.risk)) && (
                                <tr><td colSpan={6} className="px-5 py-10 text-center text-slate-500">No material shortage risks for the selected filters.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </DistributionLayout>
    );
}
