import { router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type DistributionFiltersProps = {
    routeName: string;
    filters: { from: string; to: string; category: number | string; search: string; risk: string };
    categories: Array<{ id: number; name: string }>;
};

const fieldClassName = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700';

export default function DistributionFilters({ routeName, filters, categories }: DistributionFiltersProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [category, setCategory] = useState(String(filters.category));
    const [search, setSearch] = useState(filters.search);
    const [risk, setRisk] = useState(filters.risk);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route(routeName), { from, to, category, search, risk }, { preserveState: true, replace: true });
    };

    return (
        <form onSubmit={submit} className="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
            <label className="text-xs font-semibold text-slate-600">
                From
                <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className={`${fieldClassName} mt-1`} />
            </label>
            <label className="text-xs font-semibold text-slate-600">
                To
                <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className={`${fieldClassName} mt-1`} />
            </label>
            <label className="text-xs font-semibold text-slate-600">
                Material category
                <select value={category} onChange={(event) => setCategory(event.target.value)} className={`${fieldClassName} mt-1`}>
                    <option value="">All categories</option>
                    {categories.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="text-xs font-semibold text-slate-600">
                Supply risk
                <select value={risk} onChange={(event) => setRisk(event.target.value)} className={`${fieldClassName} mt-1`}>
                    <option value="all">All risk levels</option>
                    <option value="shortage">Shortage</option>
                    <option value="low_stock">Low stock</option>
                    <option value="inbound">Inbound supply</option>
                    <option value="sufficient">Sufficient</option>
                </select>
            </label>
            <label className="text-xs font-semibold text-slate-600 lg:col-span-2">
                Search material
                <div className="mt-1 flex gap-2">
                    <input value={search} onChange={(event) => setSearch(event.target.value)} className={fieldClassName} placeholder="Code or name" />
                    <button type="submit" className="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Apply
                    </button>
                </div>
            </label>
        </form>
    );
}
