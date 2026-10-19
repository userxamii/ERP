import WarehouseLayout from '@/components/WarehouseLayout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type ReceiveProps = {
    auth?: { user?: { name?: string; role?: string } };
    materials: Array<{ id: number; code: string; name: string; unit_of_measure: { code: string } }>;
    suppliers: Array<{ id: number; name: string }>;
};

const fieldClassName = 'mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800';

export default function Receive({ auth, materials, suppliers }: ReceiveProps) {
    const { data, setData, post, processing, errors } = useForm({
        material_id: '',
        supplier_id: '',
        lot_code: '',
        quantity_received: '',
        received_at: new Date().toISOString().slice(0, 10),
        manufactured_at: '',
        expires_at: '',
        storage_location: '',
        reference: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('warehouse.receipts.store'));
    };

    return (
        <WarehouseLayout user={auth?.user} title="Receive Materials" description="Record a traceable incoming material batch and update on-hand inventory.">
            <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="grid gap-5 md:grid-cols-2">
                    <label className="text-sm font-medium text-slate-700">
                        Material
                        <select required value={data.material_id} onChange={(event) => setData('material_id', event.target.value)} className={fieldClassName}>
                            <option value="">Select material</option>
                            {materials.map((material) => (
                                <option key={material.id} value={material.id}>
                                    {material.code} — {material.name} ({material.unit_of_measure.code})
                                </option>
                            ))}
                        </select>
                        {errors.material_id && <span className="mt-1 block text-xs text-red-600">{errors.material_id}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Supplier
                        <select required value={data.supplier_id} onChange={(event) => setData('supplier_id', event.target.value)} className={fieldClassName}>
                            <option value="">Select supplier</option>
                            {suppliers.map((supplier) => (
                                <option key={supplier.id} value={supplier.id}>
                                    {supplier.name}
                                </option>
                            ))}
                        </select>
                        {errors.supplier_id && <span className="mt-1 block text-xs text-red-600">{errors.supplier_id}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Batch / lot number
                        <input required value={data.lot_code} onChange={(event) => setData('lot_code', event.target.value)} className={fieldClassName} maxLength={100} />
                        {errors.lot_code && <span className="mt-1 block text-xs text-red-600">{errors.lot_code}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Quantity received
                        <input
                            required
                            type="number"
                            min="0.001"
                            step="0.001"
                            value={data.quantity_received}
                            onChange={(event) => setData('quantity_received', event.target.value)}
                            className={fieldClassName}
                        />
                        {errors.quantity_received && <span className="mt-1 block text-xs text-red-600">{errors.quantity_received}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Received date
                        <input required type="date" value={data.received_at} onChange={(event) => setData('received_at', event.target.value)} className={fieldClassName} />
                        {errors.received_at && <span className="mt-1 block text-xs text-red-600">{errors.received_at}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Manufacturing date (optional)
                        <input type="date" value={data.manufactured_at} onChange={(event) => setData('manufactured_at', event.target.value)} className={fieldClassName} />
                        {errors.manufactured_at && <span className="mt-1 block text-xs text-red-600">{errors.manufactured_at}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Expiration date (optional)
                        <input type="date" value={data.expires_at} onChange={(event) => setData('expires_at', event.target.value)} className={fieldClassName} />
                        {errors.expires_at && <span className="mt-1 block text-xs text-red-600">{errors.expires_at}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700">
                        Storage location
                        <input
                            required
                            value={data.storage_location}
                            onChange={(event) => setData('storage_location', event.target.value)}
                            className={fieldClassName}
                            maxLength={120}
                            placeholder="Aisle / rack / bin"
                        />
                        {errors.storage_location && <span className="mt-1 block text-xs text-red-600">{errors.storage_location}</span>}
                    </label>
                    <label className="text-sm font-medium text-slate-700 md:col-span-2">
                        Receipt reference (optional)
                        <input value={data.reference} onChange={(event) => setData('reference', event.target.value)} className={fieldClassName} maxLength={120} />
                        {errors.reference && <span className="mt-1 block text-xs text-red-600">{errors.reference}</span>}
                    </label>
                </div>
                <div className="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <button type="submit" disabled={processing || materials.length === 0 || suppliers.length === 0} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                        {processing ? 'Saving receipt…' : 'Save receipt and update stock'}
                    </button>
                </div>
                {materials.length === 0 || suppliers.length === 0 ? (
                    <p className="mt-3 text-right text-xs text-amber-700">An active material and supplier are required before a receipt can be recorded.</p>
                ) : null}
            </form>
        </WarehouseLayout>
    );
}
