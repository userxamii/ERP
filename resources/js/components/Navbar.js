import React from 'react';
import { 
  Package, 
  Bell, 
  User, 
  MapPin, 
  LogOut, 
  ShieldAlert 
} from 'lucide-react';

export default function Navbar({ user, station = "North Hub - Zone A" }) {
  return (
    <nav className="bg-slate-900 text-white border-b border-slate-800 px-6 py-3.5 flex items-center justify-between shadow-md">
      
      {/* Left Section: Brand & Station Location */}
      <div className="flex items-center gap-6">
        <div className="flex items-center gap-2.5">
          <div className="bg-emerald-500 p-2 rounded-lg text-slate-900 font-bold shadow-sm">
            <Package size={20} />
          </div>
          <span className="font-bold text-lg tracking-wide">ERPSYS <span className="text-xs text-emerald-400 font-normal uppercase px-1.5 py-0.5 bg-emerald-950/80 border border-emerald-800 rounded">WMS</span></span>
        </div>

        <div className="hidden md:flex items-center gap-1.5 text-xs text-slate-400 bg-slate-800/80 px-3 py-1.5 rounded-lg border border-slate-700/60">
          <MapPin size={14} className="text-emerald-400" />
          <span>Station: <strong className="text-slate-200">{station}</strong></span>
        </div>
      </div>

      {/* Right Section: Alerts, Profile & Session Actions */}
      <div className="flex items-center gap-4">
        
        {/* Urgent Emergency Alert Trigger (Optional for Warehouse safety) */}
        <button 
          title="Emergency / Incident Report"
          className="relative p-2 text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg transition"
        >
          <ShieldAlert size={20} className="text-amber-400" />
        </button>

        {/* Notifications */}
        <div className="relative">
          <button 
            title="Notifications"
            className="relative p-2 text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg transition"
          >
            <Bell size={20} />
            <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
          </button>
        </div>

        <div className="h-6 w-px bg-slate-800"></div>

        {/* User Profile Info & Logout */}
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2 text-left">
            <div className="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-200 font-semibold text-sm">
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
              className="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg transition"
            >
              <LogOut size={18} />
            </button>
          </form>
        </div>

      </div>
    </nav>
  );
}