import { Bell, LogOut, MapPin, Package, ShieldAlert, User } from 'lucide-react';

export default function Navbar({ user, station = 'North Hub - Zone A' }) {
    return (
        <nav className="flex items-center justify-between border-b border-slate-800 bg-slate-900 px-6 py-3.5 text-white shadow-md">
            {/* Left Section: Brand & Station Location */}
            <div className="flex items-center gap-6">
                <div className="flex items-center gap-2.5">
                    <div className="rounded-lg bg-emerald-500 p-2 font-bold text-slate-900 shadow-sm">
                        <Package size={20} />
                    </div>
                    <span className="text-lg font-bold tracking-wide">
                        ERPSYS{' '}
                        <span className="rounded border border-emerald-800 bg-emerald-950/80 px-1.5 py-0.5 text-xs font-normal text-emerald-400 uppercase">
                            WMS
                        </span>
                    </span>
                </div>

                <div className="hidden items-center gap-1.5 rounded-lg border border-slate-700/60 bg-slate-800/80 px-3 py-1.5 text-xs text-slate-400 md:flex">
                    <MapPin size={14} className="text-emerald-400" />
                    <span>
                        Station: <strong className="text-slate-200">{station}</strong>
                    </span>
                </div>
            </div>

            {/* Right Section: Alerts, Profile & Session Actions */}
            <div className="flex items-center gap-4">
                {/* Urgent Emergency Alert Trigger (Optional for Warehouse safety) */}
                <button
                    title="Emergency / Incident Report"
                    className="relative rounded-lg p-2 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                >
                    <ShieldAlert size={20} className="text-amber-400" />
                </button>

                {/* Notifications */}
                <div className="relative">
                    <button title="Notifications" className="relative rounded-lg p-2 text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <Bell size={20} />
                        <span className="absolute top-1.5 right-1.5 h-2 w-2 animate-pulse rounded-full bg-red-500"></span>
                    </button>
                </div>

                <div className="h-6 w-px bg-slate-800"></div>

                {/* User Profile Info & Logout */}
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-2 text-left">
                        <div className="flex h-9 w-9 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-sm font-semibold text-slate-200">
                            {user?.name ? user.name.charAt(0) : <User size={18} />}
                        </div>
                        <div className="hidden sm:block">
                            <p className="text-xs font-medium text-slate-200">{user?.name ?? 'Warehouse Staff'}</p>
                            <p className="text-[10px] text-slate-400 capitalize">{user?.role ?? 'Operator'}</p>
                        </div>
                    </div>

                    {/* Logout button (Links to Laravel auth route) */}
                    <form action="/logout" method="POST">
                        <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')} />
                        <button
                            type="submit"
                            title="Log Out"
                            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-red-400"
                        >
                            <LogOut size={18} />
                        </button>
                    </form>
                </div>
            </div>
        </nav>
    );
}
