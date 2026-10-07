import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Navbar from '@/Components/Navbar';
import { 
  Package, 
  Truck, 
  ClipboardList, 
  AlertTriangle, 
  CheckCircle2, 
  Clock, 
  Search, 
  ArrowUpRight,
  RefreshCw
} from 'lucide-react';

export default function Dashboard({ auth, stats, tasks, filters }) {
  // Local state initialized from Laravel Inertia props
  const [activeTab, setActiveTab] = useState(filters?.tab || 'picking');
  const [searchQuery, setSearchQuery] = useState(filters?.search || '');

  // Handle tab switching with Inertia reload
  const handleTabChange = (tabName) => {
    setActiveTab(tabName);
    router.get(
      route('warehouse.dashboard'), 
      { tab: tabName, search: searchQuery }, 
      { preserveState: true, replace: true }
    );
  };

  // Handle search query updates
  const handleSearch = (e) => {
    e.preventDefault();
    router.get(
      route('warehouse.dashboard'), 
      { tab: activeTab, search: searchQuery }, 
      { preserveState: true, replace: true }
    );
  };

  // Map stats data to dashboard visual elements
  const statCards = [
    { title: "Pending Picks", value: stats?.pending_picks ?? 0, change: "Requires action", icon: ClipboardList, color: "bg-blue-500" },
    { title: "Incoming Shipments", value: stats?.incoming_shipments ?? 0, change: "Expected arrivals", icon: Truck, color: "bg-emerald-500" },
    { title: "Low Stock Items", value: stats?.low_stock_items ?? 0, change: "Below reorder level", icon: AlertTriangle, color: "bg-amber-500" },
    { title: "Packed & Ready", value: stats?.packed_ready ?? 0, change: "Ready for dispatch", icon: Package, color: "bg-indigo-500" },
  ];

  return (
    <div className="min-h-screen bg-slate-50 font-sans">
      
      {/* Top Navbar Component */}
      <Navbar user={auth?.user} station="North Hub - Zone A" />

      {/* Main Container */}
      <div className="p-6 md:p-8 max-w-7xl mx-auto">
        
        {/* Header Title & Actions */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Warehouse Staff Dashboard</h1>
            <p className="text-sm text-slate-500">Real-time inventory tracking and order fulfillment queue</p>
          </div>
          <div className="flex items-center gap-3">
            <button 
              onClick={() => router.reload()}
              className="flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition shadow-sm"
            >
              <RefreshCw size={16} /> Sync ERP Data
            </button>
            <span className="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-lg text-xs font-semibold">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Sync Active
            </span>
          </div>
        </div>

        {/* KPI Metrics Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          {statCards.map((stat, idx) => {
            const Icon = stat.icon;
            return (
              <div key={idx} className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                  <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{stat.title}</p>
                  <h3 className="text-2xl font-bold text-slate-900 mt-1">{stat.value}</h3>
                  <p className="text-xs text-slate-500 mt-1">{stat.change}</p>
                </div>
                <div className={`p-3 rounded-xl text-white ${stat.color} shadow-md`}>
                  <Icon size={24} />
                </div>
              </div>
            );
          })}
        </div>

        {/* Main Workspace Section */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          
          {/* Table Header Controls & Filters */}
          <div className="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex flex-wrap gap-2">
              {['picking', 'receiving', 'packing', 'all'].map((tab) => (
                <button
                  key={tab}
                  onClick={() => handleTabChange(tab)}
                  className={`px-4 py-2 rounded-lg text-sm font-medium capitalize transition ${
                    activeTab === tab 
                      ? 'bg-slate-900 text-white shadow-sm' 
                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  }`}
                >
                  {tab} Tasks
                </button>
              ))}
            </div>

            {/* Search Bar */}
            <form onSubmit={handleSearch} className="relative">
              <Search className="absolute left-3 top-2.5 text-slate-400" size={18} />
              <input 
                type="text" 
                placeholder="Search SKU, task ID, zone..." 
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 w-full sm:w-64"
              />
            </form>
          </div>

          {/* Task Queue Table */}
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-slate-200">
                  <th className="py-3 px-6">Task ID</th>
                  <th className="py-3 px-6">Type</th>
                  <th className="py-3 px-6">Item / SKU</th>
                  <th className="py-3 px-6">Quantity</th>
                  <th className="py-3 px-6">Location / Zone</th>
                  <th className="py-3 px-6">Priority</th>
                  <th className="py-3 px-6">Status</th>
                  <th className="py-3 px-6 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 text-sm text-slate-700">
                {tasks?.data && tasks.data.length > 0 ? (
                  tasks.data.map((task) => (
                    <tr key={task.id} className="hover:bg-slate-50/85 transition">
                      <td className="py-4 px-6 font-medium text-slate-900">{task.task_id}</td>
                      <td className="py-4 px-6 font-semibold text-slate-700">{task.type}</td>
                      <td className="py-4 px-6">{task.item_sku}</td>
                      <td className="py-4 px-6 font-mono text-slate-600">{task.quantity}</td>
                      <td className="py-4 px-6 text-slate-500">{task.zone}</td>
                      <td className="py-4 px-6">
                        <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-semibold ${
                          task.priority === 'Urgent' ? 'bg-red-100 text-red-700' :
                          task.priority === 'High' ? 'bg-orange-100 text-orange-700' :
                          'bg-slate-100 text-slate-700'
                        }`}>
                          {task.priority}
                        </span>
                      </td>
                      <td className="py-4 px-6">
                        <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium ${
                          task.status === 'In Progress' ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                          task.status === 'Ready' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                          'bg-amber-50 text-amber-700 border border-amber-200'
                        }`}>
                          {task.status === 'In Progress' && <Clock size={12} />}
                          {task.status === 'Ready' && <CheckCircle2 size={12} />}
                          {task.status}
                        </span>
                      </td>
                      <td className="py-4 px-6 text-right">
                        <button 
                          onClick={() => router.post(route('warehouse.tasks.status', task.id), { status: 'In Progress' })}
                          className="bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-800 transition shadow-sm inline-flex items-center gap-1"
                        >
                          Process <ArrowUpRight size={14} />
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="8" className="py-8 text-center text-slate-400 text-sm">
                      No active warehouse tasks found in this queue.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Footer / Pagination summary */}
          <div className="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
            <span>Showing {tasks?.from ?? 0} to {tasks?.to ?? 0} of {tasks?.total ?? 0} tasks</span>
            
            {/* Laravel Pagination Links */}
            <div className="flex gap-1">
              {tasks?.links?.map((link, idx) => (
                <button
                  key={idx}
                  disabled={!link.url}
                  onClick={() => router.get(link.url, {}, { preserveState: true })}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={`px-3 py-1 rounded-md font-medium border transition ${
                    link.active 
                      ? 'bg-slate-900 text-white border-slate-900' 
                      : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40'
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