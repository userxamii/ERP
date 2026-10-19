import WarehouseLayout from '@/components/WarehouseLayout';
import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type InventoryRow = {
    id: number;
    code: string;
    name: string;
    category: string;
    category_id: number | null;
    unit_of_measure: string;
    available_quantity: string;
    physical_on_hand: string;
    total_batches: number;
    storage_locations: string[];
    next_expiration: string | null;
    stock_status: string;
};

type InventoryProps = {
    auth?: { user?: { name?: string; role?: string } };
    inventory: {
        data: InventoryRow[];
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    categories: Array<{ id: number; name: string }>;
    filters: { search: string; category: number | ''; stock: string; sort: string; direction: string };
};

const inputClassName = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700';

export default function Inventory({ auth, inventory, categories, filters }: InventoryProps) {
    const [search, setSearch] = useState(filters.search);
    const [category, setCategory] = useState(String(filters.category));
    const [stock, setStock] = useState(filters.stock);
    const [sort, setSort] = useState(filters.sort);
    const [direction, setDirection] = useState(filters.direction);

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route('warehouse.materials.inventory'), { search, category, stock, sort, direction }, { preserveState: true, replace: true });
    };

    return (
        <WarehouseLayout user={auth?.user} title="Materials Inventory" description="Search materials and review usable stock, batches, and locations.">
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <form onSubmit={submitFilters} className="grid gap-3 border-b border-slate-200 p-5 md:grid-cols-6">
                    <label className="relative md:col-span-2">
                        <span className="sr-only">Search materials</span>
                        <Search className="absolute top-2.5 left-3 text-slate-400" size={17} />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search code or material"
                            className={`${inputClassName} w-full pl-9`}
                        />
                    </label>
                    <select value={category} onChange={(event) => setCategory(event.target.value)} className={inputClassName} aria-label="Filter by category">
                        <option value="">All categories</option>
                        {categories.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    <select value={stock} onChange={(event) => setStock(event.target.value)} className={inputClassName} aria-label="Filter by stock status">
                        <option value="all">All stock levels</option>
                        <option value="in_stock">In stock</option>
                        <option value="low_stock">Low stock</option>
                        <option value="out_of_stock">Out of stock</option>
                        <option value="review_required">Quality review</option>
                    </select>
                    <select value={sort} onChange={(event) => setSort(event.target.value)} className={inputClassName} aria-label="Sort inventory">
                        <option value="name">Sort by name</option>
                        <option value="code">Sort by code</option>
                        <option value="category">Sort by category</option>
                        <option value="available">Sort by available quantity</option>
                        <option value="batches">Sort by batch count</option>
                    </select>
                    <div className="flex gap-2">
                        <select value={direction} onChange={(event) => setDirection(event.target.value)} className={`${inputClassName} min-w-0 flex-1`} aria-label="Sort direction">
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Apply
                        </button>
                    </div>
                </form>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th className="px-5 py-3">Material</th>
                                <th className="px-5 py-3">Category</th>
                                <th className="px-5 py-3">Available</th>
                                <th className="px-5 py-3">Physical on hand</th>
                                <th className="px-5 py-3">Batches</th>
                                <th className="px-5 py-3">Storage location</th>
                                <th className="px-5 py-3">Next expiry</th>
                                <th className="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {inventory.data.length > 0 ? (
                                inventory.data.map((material) => (
                                    <tr key={material.id} className="hover:bg-slate-50">
                                        <td className="px-5 py-4">
                                            <Link href={route('warehouse.materials.show', material.id)} className="font-semibold text-blue-700 hover:underline">
                                                {material.name}
                                            </Link>
                                            <div className="text-xs text-slate-500">{material.code}</div>
                                        </td>
                                        <td className="px-5 py-4">{material.category}</td>
                                        <td className="px-5 py-4 font-mono">
                                            {material.available_quantity} {material.unit_of_measure}
                                        </td>
                                        <td className="px-5 py-4 font-mono text-slate-500">
                                            {material.physical_on_hand} {material.unit_of_measure}
                                        </td>
                                        <td className="px-5 py-4">{material.total_batches}</td>
                                        <td className="px-5 py-4">{material.storage_locations.join(', ') || 'Not assigned'}</td>
                                        <td className="px-5 py-4">{material.next_expiration ?? '—'}</td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                    material.stock_status === 'out_of_stock'
                                                        ? 'bg-red-100 text-red-700'
                                                        : material.stock_status === 'review_required'
                                                          ? 'bg-indigo-100 text-indigo-700'
                                                        : material.stock_status === 'low_stock'
                                                          ? 'bg-amber-100 text-amber-800'
                                                          : 'bg-emerald-100 text-emerald-700'
                                                }`}
                                            >
                                                {material.stock_status.replaceAll('_', ' ')}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={8} className="px-5 py-12 text-center text-slate-400">
                                        No materials match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                    <span>
                        Showing {inventory.from ?? 0}–{inventory.to ?? 0} of {inventory.total} materials
                    </span>
                    <div className="flex gap-1">
                        {inventory.links.map((link, index) => (
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
