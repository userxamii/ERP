import Navbar from '@/components/Navbar';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, Package, RefreshCw, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type DashboardProps = {
    auth?: { user?: { name?: string; role?: string } };
    stats?: {
        active_materials?: number;
        low_stock_materials?: number;
        expiring_batches?: number;
        review_batches?: number;
    };
    inventory?: {
        data: Array<{
            id: number;
            batch_number: string;
            material_code: string;
            material_name: string;
            quantity_on_hand: string;
            unit_of_measure: string;
            category: string;
            priority: string;
            quality_status: string;
        }>;
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters?: { tab?: string; search?: string };
};

export default function Dashboard({ auth, stats, inventory, filters }: DashboardProps) {
    const [activeTab, setActiveTab] = useState(filters?.tab || 'all');
    const [searchQuery, setSearchQuery] = useState(filters?.search || '');

    const handleTabChange = (tabName: string) => {
        setActiveTab(tabName);
        router.get(route('warehouse.dashboard'), { tab: tabName, search: searchQuery }, { preserveState: true, replace: true });
    };

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        router.get(route('warehouse.dashboard'), { tab: activeTab, search: searchQuery }, { preserveState: true, replace: true });
    };

    const statCards = [
        { title: 'Active Materials', value: stats?.active_materials ?? 0, change: 'Tracked in inventory', icon: Package, color: 'bg-blue-500' },
        {
            title: 'Low Stock Materials',
            value: stats?.low_stock_materials ?? 0,
            change: 'At or below minimum stock',
            icon: AlertTriangle,
            color: 'bg-amber-500',
        },
        { title: 'Expiring Batches', value: stats?.expiring_batches ?? 0, change: 'Within 30 days', icon: Clock, color: 'bg-emerald-500' },
        {
            title: 'Quality Review',
            value: stats?.review_batches ?? 0,
            change: 'Held, rejected, or damaged',
            icon: CheckCircle2,
            color: 'bg-indigo-500',
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50 font-sans">
            {/* Top Navbar Component */}
            <Navbar user={auth?.user} station="North Hub - Zone A" />

            {/* Main Container */}
            <div className="mx-auto max-w-7xl p-6 md:p-8">
                {/* Header Title & Actions */}
                <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Warehouse Staff Dashboard</h1>
                        <p className="text-sm text-slate-500">Review material stock, batch quality, and expiration risk</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => router.reload()}
                            className="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100"
                        >
                            <RefreshCw size={16} /> Sync ERP Data
                        </button>
                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                            <span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> Live Sync Active
                        </span>
                    </div>
                </div>

                {/* KPI Metrics Grid */}
                <div className="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat, idx) => {
                        const Icon = stat.icon;
                        return (
                            <div key={idx} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div>
                                    <p className="text-xs font-semibold tracking-wider text-slate-500 uppercase">{stat.title}</p>
                                    <h3 className="mt-1 text-2xl font-bold text-slate-900">{stat.value}</h3>
                                    <p className="mt-1 text-xs text-slate-500">{stat.change}</p>
                                </div>
                                <div className={`rounded-xl p-3 text-white ${stat.color} shadow-md`}>
                                    <Icon size={24} />
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Main Workspace Section */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    {/* Table Header Controls & Filters */}
                    <div className="flex flex-col justify-between gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-center">
                        <div className="flex flex-wrap gap-2">
                            {['all', 'available', 'held', 'rejected', 'damaged'].map((tab) => (
                                <button
                                    key={tab}
                                    onClick={() => handleTabChange(tab)}
                                    className={`rounded-lg px-4 py-2 text-sm font-medium capitalize transition ${
                                        activeTab === tab ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    {tab === 'all' ? 'All batches' : `${tab} batches`}
                                </button>
                            ))}
                        </div>

                        {/* Search Bar */}
                        <form onSubmit={handleSearch} className="relative">
                            <Search className="absolute top-2.5 left-3 text-slate-400" size={18} />
                            <input
                                type="text"
                                placeholder="Search material, code, or batch..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pr-4 pl-9 text-sm focus:ring-2 focus:ring-slate-900 focus:outline-none sm:w-64"
                            />
                        </form>
                    </div>

                    {/* Inventory batch table */}
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-6 py-3">Batch / Lot</th>
                                    <th className="px-6 py-3">Material</th>
                                    <th className="px-6 py-3">Item / Code</th>
                                    <th className="px-6 py-3">On hand</th>
                                    <th className="px-6 py-3">Category</th>
                                    <th className="px-6 py-3">Priority</th>
                                    <th className="px-6 py-3">Quality status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 text-sm text-slate-700">
                                {inventory?.data && inventory.data.length > 0 ? (
                                    inventory.data.map((batch) => (
                                        <tr key={batch.id} className="transition hover:bg-slate-50/85">
                                            <td className="px-6 py-4 font-medium text-slate-900">{batch.batch_number}</td>
                                            <td className="px-6 py-4 font-semibold text-slate-700">{batch.material_name}</td>
                                            <td className="px-6 py-4">{batch.material_code}</td>
                                            <td className="px-6 py-4 font-mono text-slate-600">
                                                {batch.quantity_on_hand} {batch.unit_of_measure}
                                            </td>
                                            <td className="px-6 py-4 text-slate-500">{batch.category}</td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        batch.priority === 'Expired' || batch.priority === 'Urgent'
                                                            ? 'bg-red-100 text-red-700'
                                                            : batch.priority === 'High' || batch.priority === 'Review'
                                                              ? 'bg-orange-100 text-orange-700'
                                                              : 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {batch.priority}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium ${
                                                        batch.quality_status === 'available'
                                                            ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                                            : batch.quality_status === 'held'
                                                              ? 'border border-amber-200 bg-amber-50 text-amber-700'
                                                              : 'border border-red-200 bg-red-50 text-red-700'
                                                    }`}
                                                >
                                                    {batch.quality_status === 'available' && <CheckCircle2 size={12} />}
                                                    {batch.quality_status === 'held' && <Clock size={12} />}
                                                    {batch.quality_status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="7" className="py-8 text-center text-sm text-slate-400">
                                            No inventory batches match these filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer / Pagination summary */}
                    <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50 p-4 text-xs text-slate-500">
                        <span>
                            Showing {inventory?.from ?? 0} to {inventory?.to ?? 0} of {inventory?.total ?? 0} batches
                        </span>

                        {/* Laravel Pagination Links */}
                        <div className="flex gap-1">
                            {inventory?.links?.map((link, idx) => (
                                <button
                                    key={idx}
                                    disabled={!link.url}
                                    onClick={() => router.get(link.url, {}, { preserveState: true })}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    className={`rounded-md border px-3 py-1 font-medium transition ${
                                        link.active
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
