import DistributionFilters from '@/components/DistributionFilters';
import DistributionLayout from '@/components/DistributionLayout';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Bell, CircleAlert, Info } from 'lucide-react';

type Notice = {
    id: string;
    severity: string;
    title: string;
    detail: string;
    occurred_at: string;
    href: string;
};

type NotificationsProps = {
    auth?: { user?: { name?: string; role?: string } };
    filters: { from: string; to: string; category: number | string; search: string; risk: string };
    categories: Array<{ id: number; name: string }>;
    summary: { shortage_materials: number; risk_materials: number };
    notices: Notice[];
};

export default function Notifications({ auth, filters, categories, summary, notices }: NotificationsProps) {
    return (
        <DistributionLayout user={auth?.user} title="Supply Notifications" description="Relevant notices for material shortages, overdue deliveries, and inventory expiration.">
            <DistributionFilters routeName="distribution.notifications" filters={filters} categories={categories} />
            <div className="mb-5 flex flex-wrap gap-3">
                <span className="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-800">
                    <AlertTriangle size={16} /> {summary.shortage_materials} shortage risks
                </span>
                <span className="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800">
                    <Bell size={16} /> {notices.length} active supply notices
                </span>
                <span className="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-800">
                    <Info size={16} /> {summary.risk_materials} materials at risk
                </span>
            </div>
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="divide-y divide-slate-100">
                    {notices.map((notice) => (
                        <article key={notice.id} className="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-start gap-3">
                                <CircleAlert className={`mt-0.5 shrink-0 ${notice.severity === 'high' ? 'text-red-600' : 'text-amber-600'}`} size={19} />
                                <div>
                                    <h2 className="font-semibold text-slate-900">{notice.title}</h2>
                                    <p className="mt-1 text-sm text-slate-600">{notice.detail}</p>
                                    <p className="mt-2 text-xs text-slate-400">Relevant date: {notice.occurred_at}</p>
                                </div>
                            </div>
                            <Link href={notice.href} className="shrink-0 text-sm font-semibold text-blue-700 hover:underline">
                                View related availability
                            </Link>
                        </article>
                    ))}
                    {notices.length === 0 && (
                        <div className="px-5 py-16 text-center">
                            <Bell className="mx-auto mb-3 text-slate-300" size={28} />
                            <p className="font-medium text-slate-700">No active supply notices</p>
                            <p className="mt-1 text-sm text-slate-500">The selected material filters show no shortage, delivery, or expiration notices.</p>
                        </div>
                    )}
                </div>
            </section>
        </DistributionLayout>
    );
}
