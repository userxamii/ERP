const inventory = [
    {
        id: "B-001",
        name: "Product A",
        batch: "B-001",
        quantity: 120,
        received: "2026-09-10",
        expiration: "2026-10-15",
        status: "Available",
    },
    {
        id: "B-002",
        name: "Product B",
        batch: "B-002",
        quantity: 85,
        received: "2026-08-20",
        expiration: "2026-10-22",
        status: "Aging",
    },
    {
        id: "B-003",
        name: "Product C",
        batch: "B-003",
        quantity: 64,
        received: "2026-08-10",
        expiration: "2026-10-29",
        status: "Aging",
    },
    {
        id: "B-004",
        name: "Product D",
        batch: "B-004",
        quantity: 12,
        received: "2026-06-20",
        expiration: "2026-10-01",
        status: "Expired",
    },
    {
        id: "B-005",
        name: "Product E",
        batch: "B-005",
        quantity: 40,
        received: "2026-09-30",
        expiration: "2026-12-20",
        status: "Available",
    },
    {
        id: "B-006",
        name: "Product F",
        batch: "B-006",
        quantity: 32,
        received: "2026-08-01",
        expiration: "2026-12-01",
        status: "Aging",
    },
];

const statusOptions = [
    "Available",
    "Aging",
    "For Production",
    "For Distribution",
    "Expired",
    "Damaged",
];

const pageMetadata = {
    dashboard: {
        title: "Warehouse Management",
        subtitle: "Monitor inventory, aging items, and expiration dates.",
    },
    inventory: {
        title: "Inventory",
        subtitle: "Review stock levels, condition, and product history.",
    },
    aging: {
        title: "Aging Inventory",
        subtitle: "Prioritize stock that has been in storage for more than 30 days.",
    },
    expiring: {
        title: "Expiring Products",
        subtitle: "Track upcoming expiration dates and expired batches.",
    },
    operations: {
        title: "Operations",
        subtitle: "Track live work orders, receiving flow, and operational priorities.",
    },
    alerts: {
        title: "Alerts",
        subtitle: "Review aging, expiration, and expired inventory alerts.",
    },
    settings: {
        title: "Settings",
        subtitle: "Warehouse staff workspace preferences.",
    },
};

const rolePermissions = {
    "Warehouse Staff": ["dashboard", "inventory", "aging", "expiring", "operations", "alerts", "settings"],
    "Procurement Staff": ["dashboard", "inventory", "operations", "alerts", "settings"],
    "Production Staff": ["dashboard", "inventory", "operations", "alerts", "settings"],
    "Sales & Distribution Staff": ["dashboard", "expiring", "operations", "alerts", "settings"],
    "Quality Assurance (QA)": ["dashboard", "inventory", "aging", "expiring", "operations", "alerts", "settings"],
    "Finance Staff": ["dashboard", "operations", "alerts", "settings"],
    "Management": ["dashboard", "inventory", "aging", "expiring", "operations", "alerts", "settings"],
    "System Administrator": ["dashboard", "inventory", "aging", "expiring", "operations", "alerts", "settings"],
};

const roleHighlights = {
    "Warehouse Staff": [
        "Receive incoming raw materials and confirm storage locations.",
        "Track active batch quantities and stock movements.",
        "Review expiring and aging items before production usage.",
    ],
    "Procurement Staff": [
        "Monitor low-stock materials and replenish critical items.",
        "Review supplier delivery timelines and shortages.",
        "Prioritize procurement actions for expiring or constrained stock.",
    ],
    "Production Staff": [
        "Check usable material availability and batch quality status.",
        "Request materials for planned production orders.",
        "Use FEFO recommendations when selecting eligible stock.",
    ],
    "Quality Assurance (QA)": [
        "Inspect incoming batches and capture inspection findings.",
        "Release, hold, or reject stock based on compliance checks.",
        "Protect production from expired or failed quality batches.",
    ],
    "Finance Staff": [
        "Estimate batch value and identify expired or rejected losses.",
        "Track valuation trends and material loss exposure.",
        "Review inventory cost summaries without altering operational data.",
    ],
    Management: [
        "Review overall inventory health and department KPIs.",
        "Follow aging and expiration trends across the warehouse.",
        "Approve actions driven by risk, cost, and material availability.",
    ],
    "System Administrator": [
        "Manage user roles, master data, and alert thresholds.",
        "Monitor audit trails and system activity.",
        "Maintain secure access and configuration controls.",
    ],
};

function getStatCardIcon(label) {
    const icons = {
        // Warehouse Staff
        "Receiving queue": '<svg viewBox="0 0 24 24" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>',
        "Stock moves": '<svg viewBox="0 0 24 24" fill="none"><path d="M17 3v10"/><path d="m21 9-4 4-4-4"/><path d="M7 21V11"/><path d="m3 15 4-4 4 4"/></svg>',
        "Aging stock": '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',

        // Procurement Staff
        "Reorder alerts": '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3v12"/><path d="M5.5 17.5 12 21l6.5-3.5"/><path d="M5.5 9.5 12 13l6.5-3.5"/></svg>',
        "Expected deliveries": '<svg viewBox="0 0 24 24" fill="none"><rect width="16" height="13" x="1" y="3" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        "Risk items": '<svg viewBox="0 0 24 24" fill="none"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',

        // Production Staff
        "Available units": '<svg viewBox="0 0 24 24" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>',
        "Ready batches": '<svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        "FEFO queue": '<svg viewBox="0 0 24 24" fill="none"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M12 14v4M12 18h3"/></svg>',

        // Quality Assurance (QA)
        "Pending inspection": '<svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="m8 11 2 2 4-4"/></svg>',
        "Released stock": '<svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
        "Hold review": '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',

        // Finance Staff
        "Estimated value": '<svg viewBox="0 0 24 24" fill="none"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        "Loss exposure": '<svg viewBox="0 0 24 24" fill="none"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>',
        "Valuation cycles": '<svg viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>',

        // Management
        "Active batches": '<svg viewBox="0 0 24 24" fill="none"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>',
        "Aging risk": '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        "Expired value": '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',

        // System Administrator
        "User accounts": '<svg viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        "Audit events": '<svg viewBox="0 0 24 24" fill="none"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        "Alert rules": '<svg viewBox="0 0 24 24" fill="none"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>',

        // Operations
        "Open tasks": '<svg viewBox="0 0 24 24" fill="none"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/></svg>',
        "Urgent": '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        "Ready": '<svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',

        // Sales & Distribution
        "Finished products": '<svg viewBox="0 0 24 24" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>',
        "Nearing expiration": '<svg viewBox="0 0 24 24" fill="none"><path d="M8 2v4M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M12 14v4M12 18h3"/></svg>',
        "Prioritized for distribution": '<svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    };

    return icons[label] || '<svg viewBox="0 0 24 24" fill="none"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>';
}

function getRoleDashboardCards(role) {
    if (role === "Sales & Distribution Staff") {
        return typeof salesDistributionStaff !== "undefined" && typeof salesDistributionStaff.getDashboardCards === "function"
            ? salesDistributionStaff.getDashboardCards()
            : [
                { label: "Finished products", value: String(inventory.length), note: "Tracked inventory records", color: "blue", icon: getStatCardIcon("Finished products") },
                { label: "Nearing expiration", value: String(inventory.filter(p => getDaysRemaining(p) >= 0 && getDaysRemaining(p) <= 30 && p.status !== "Expired").length), note: "Within the next 30 days", color: "orange", icon: getStatCardIcon("Nearing expiration") },
                { label: "Prioritized for distribution", value: String(inventory.filter(p => p.status === "For Distribution").length), note: "Marked for distribution", color: "green", icon: getStatCardIcon("Prioritized for distribution") },
            ];
    }

    const totalInStock = inventory.reduce((sum, product) => sum + product.quantity, 0);
    const lowStock = inventory.filter(product => product.quantity < 40).length;
    const agingItems = inventory.filter(product => getInventoryAge(product) > 30 && getDaysRemaining(product) >= 0 && product.status !== "Expired").length;
    const expiringSoon = inventory.filter(product => getDaysRemaining(product) <= 30 && product.status !== "Expired").length;

    const roleMap = {
        "Warehouse Staff": [
            { label: "Receiving queue", value: "4 batches", note: "Awaiting intake review", color: "blue", icon: getStatCardIcon("Receiving queue") },
            { label: "Stock moves", value: "12 entries", note: "This week", color: "orange", icon: getStatCardIcon("Stock moves") },
            { label: "Aging stock", value: agingItems, note: "Older than 30 days", color: "amber", icon: getStatCardIcon("Aging stock") },
        ],
        "Procurement Staff": [
            { label: "Reorder alerts", value: lowStock, note: "Below target stock", color: "orange", icon: getStatCardIcon("Reorder alerts") },
            { label: "Expected deliveries", value: "3 orders", note: "In transit", color: "blue", icon: getStatCardIcon("Expected deliveries") },
            { label: "Risk items", value: expiringSoon, note: "Priority purchases", color: "red", icon: getStatCardIcon("Risk items") },
        ],
        "Production Staff": [
            { label: "Available units", value: totalInStock.toLocaleString(), note: "Usable stock", color: "blue", icon: getStatCardIcon("Available units") },
            { label: "Ready batches", value: "5", note: "QA cleared", color: "green", icon: getStatCardIcon("Ready batches") },
            { label: "FEFO queue", value: expiringSoon, note: "Next expiry review", color: "amber", icon: getStatCardIcon("FEFO queue") },
        ],
        "Quality Assurance (QA)": [
            { label: "Pending inspection", value: "3 batches", note: "Queue", color: "orange", icon: getStatCardIcon("Pending inspection") },
            { label: "Released stock", value: "18", note: "Approved batches", color: "green", icon: getStatCardIcon("Released stock") },
            { label: "Hold review", value: "2 items", note: "Requires action", color: "red", icon: getStatCardIcon("Hold review") },
        ],
        "Finance Staff": [
            { label: "Estimated value", value: "$48.6K", note: "Inventory value", color: "blue", icon: getStatCardIcon("Estimated value") },
            { label: "Loss exposure", value: "$8.4K", note: "Expired / rejected", color: "red", icon: getStatCardIcon("Loss exposure") },
            { label: "Valuation cycles", value: "12", note: "This month", color: "amber", icon: getStatCardIcon("Valuation cycles") },
        ],
        Management: [
            { label: "Active batches", value: inventory.length, note: "Tracked records", color: "blue", icon: getStatCardIcon("Active batches") },
            { label: "Aging risk", value: agingItems, note: "High-risk stock", color: "orange", icon: getStatCardIcon("Aging risk") },
            { label: "Expired value", value: "$7.1K", note: "Exposure tracked", color: "red", icon: getStatCardIcon("Expired value") },
        ],
        "System Administrator": [
            { label: "User accounts", value: "26", note: "Active users", color: "blue", icon: getStatCardIcon("User accounts") },
            { label: "Audit events", value: "184", note: "This week", color: "green", icon: getStatCardIcon("Audit events") },
            { label: "Alert rules", value: "9", note: "Configured thresholds", color: "amber", icon: getStatCardIcon("Alert rules") },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getRoleQuickActions(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.quickActions;
    }

    const roleMap = {
        "Warehouse Staff": [
            { label: "Operations board", page: "operations" },
            { label: "Receive batch", page: "inventory" },
            { label: "Review aging", page: "aging" },
            { label: "Check alerts", page: "alerts" },
        ],
        "Procurement Staff": [
            { label: "Operations board", page: "operations" },
            { label: "Open risk list", page: "alerts" },
            { label: "Review inventory", page: "inventory" },
            { label: "Plan replenishment", page: "dashboard" },
        ],
        "Production Staff": [
            { label: "Operations board", page: "operations" },
            { label: "Check stock", page: "inventory" },
            { label: "View expiries", page: "expiring" },
            { label: "Open alerts", page: "alerts" },
        ],
        "Quality Assurance (QA)": [
            { label: "Operations board", page: "operations" },
            { label: "Batch review", page: "inventory" },
            { label: "Aging review", page: "aging" },
            { label: "Expiration watch", page: "expiring" },
        ],
        "Finance Staff": [
            { label: "Operations board", page: "operations" },
            { label: "Open dashboard", page: "dashboard" },
            { label: "Risk review", page: "alerts" },
            { label: "Workspace settings", page: "settings" },
        ],
        Management: [
            { label: "Operations board", page: "operations" },
            { label: "Summary view", page: "dashboard" },
            { label: "Aging inventory", page: "aging" },
            { label: "Expiry watch", page: "expiring" },
        ],
        "System Administrator": [
            { label: "Operations board", page: "operations" },
            { label: "Access control", page: "settings" },
            { label: "Audit review", page: "alerts" },
            { label: "Inventory overview", page: "inventory" },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getRoleOperationalQueue(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.operationalQueue;
    }

    const roleMap = {
        "Warehouse Staff": [
            { title: "Receive 3 inbound batches", status: "Pending", detail: "Prioritize materials stored in Zone B" },
            { title: "Confirm shelf locations", status: "In progress", detail: "Two pallets awaiting cycle count" },
            { title: "Check damage report", status: "Review", detail: "One crate flagged for QA inspection" },
        ],
        "Procurement Staff": [
            { title: "Reorder low-stock items", status: "Priority", detail: "Three SKUs below target threshold" },
            { title: "Approve supplier updates", status: "Pending", detail: "Lead times need confirmation" },
            { title: "Review shortage risk", status: "Watch", detail: "Potential disruption near month-end" },
        ],
        "Production Staff": [
            { title: "Plan material release", status: "Priority", detail: "Need QA-cleared stock for line 3" },
            { title: "Review FEFO queue", status: "Pending", detail: "Two lots approaching expiration" },
            { title: "Confirm production schedule", status: "Ready", detail: "Shift handoff complete" },
        ],
        "Quality Assurance (QA)": [
            { title: "QA sample review", status: "Pending", detail: "Three incoming lots need inspection" },
            { title: "Approve release record", status: "In progress", detail: "Batch B-005 awaiting final signoff" },
            { title: "Hold review", status: "Review", detail: "Check one damaged pallet before release" },
        ],
        "Finance Staff": [
            { title: "Inventory value review", status: "Pending", detail: "Month-end costs need validation" },
            { title: "Loss exposure check", status: "Watch", detail: "Expired inventory exposure remains high" },
            { title: "Approve variance report", status: "Ready", detail: "No blocking exceptions" },
        ],
        Management: [
            { title: "Department KPI review", status: "Priority", detail: "Aging stock trend requires attention" },
            { title: "Approve action plan", status: "Pending", detail: "Follow-up on expiration watchlist" },
            { title: "Review exception summary", status: "Ready", detail: "No serious operational issues" },
        ],
        "System Administrator": [
            { title: "Audit event review", status: "Pending", detail: "User access activity needs approval" },
            { title: "Alert threshold update", status: "In progress", detail: "Review threshold anomalies in Zone C" },
            { title: "Backup check", status: "Ready", detail: "System sync completed successfully" },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getRoleOperationsTimeline(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.timeline;
    }

    const roleMap = {
        "Warehouse Staff": [
            { time: "08:30", title: "Inbound receiving", note: "Three batches cleared for put-away approval." },
            { time: "10:15", title: "Cycle count check", note: "Zone A count variance is under review." },
            { time: "14:00", title: "Put-away release", note: "Two pallets prepared for final rack assignment." },
        ],
        "Procurement Staff": [
            { time: "09:10", title: "Supplier follow-up", note: "Replenishment lead times confirmed for critical SKUs." },
            { time: "11:45", title: "Demand review", note: "Order coverage updated after the morning stock check." },
            { time: "16:20", title: "Risk handoff", note: "Shortage watch shared with operations and finance." },
        ],
        "Production Staff": [
            { time: "07:45", title: "Line readiness", note: "Line 3 confirms materials are available for the next run." },
            { time: "12:30", title: "FEFO release", note: "Two material lots prepared for the afternoon schedule." },
            { time: "15:10", title: "Quality handoff", note: "QA-cleared stock queued for final issue release." },
        ],
        "Quality Assurance (QA)": [
            { time: "09:00", title: "Batch inspection", note: "Three inbound lots await final signoff." },
            { time: "11:20", title: "Hold review", note: "One damaged pallet requires documentation before release." },
            { time: "15:40", title: "Compliance closeout", note: "QA notes uploaded for all released stock." },
        ],
        "Finance Staff": [
            { time: "08:05", title: "Valuation snapshot", note: "Month-end inventory value refreshed with current stock levels." },
            { time: "13:25", title: "Loss exposure", note: "Expired stock exposure reviewed against current limits." },
            { time: "18:00", title: "Variance review", note: "Operational variance report prepared for closeout." },
        ],
        Management: [
            { time: "09:30", title: "KPI review", note: "Department metrics were shared for this shift." },
            { time: "13:00", title: "Aging response", note: "Leadership action plan approved for aging stock items." },
            { time: "16:45", title: "Exception summary", note: "Operational risk summary circulated for final approval." },
        ],
        "System Administrator": [
            { time: "07:15", title: "Access review", note: "User permissions checked against current role matrix." },
            { time: "12:40", title: "Alert tuning", note: "System thresholds reviewed for operational stability." },
            { time: "17:55", title: "Sync verification", note: "All connected warehouse tasks are reporting healthy." },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getWarehouseDockSchedule(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.dockSchedule;
    }

    const roleMap = {
        "Warehouse Staff": [
            { label: "Inbound arrival", zone: "Dock 4", eta: "08:45", status: "Receiving" },
            { label: "Trailer unload", zone: "Dock 2", eta: "11:20", status: "In progress" },
            { label: "Outbound dispatch", zone: "Dock 1", eta: "15:10", status: "Queued" },
        ],
        "Procurement Staff": [
            { label: "Supplier delivery", zone: "Dock 3", eta: "09:05", status: "Receiving" },
            { label: "Replenishment crate", zone: "Hold bay B", eta: "13:35", status: "Queued" },
            { label: "Return pallet", zone: "Dock 6", eta: "17:00", status: "Review" },
        ],
        "Production Staff": [
            { label: "Line 3 material run", zone: "Zone C", eta: "08:30", status: "Ready" },
            { label: "FEFO issue", zone: "Zone A", eta: "12:10", status: "In progress" },
            { label: "Load-out handoff", zone: "Dock 5", eta: "16:00", status: "Queued" },
        ],
        "Quality Assurance (QA)": [
            { label: "Sample inspection", zone: "QA bay", eta: "09:25", status: "Pending" },
            { label: "Hold release", zone: "QA bay", eta: "12:55", status: "In progress" },
            { label: "Final sign-off", zone: "Dock 4", eta: "17:30", status: "Queued" },
        ],
        "Finance Staff": [
            { label: "Inventory count match", zone: "Control room", eta: "11:00", status: "Ready" },
            { label: "Variance check", zone: "Finance desk", eta: "14:15", status: "In progress" },
            { label: "Closeout handoff", zone: "Ops desk", eta: "18:00", status: "Queued" },
        ],
        Management: [
            { label: "Leadership review", zone: "Board room", eta: "10:00", status: "Ready" },
            { label: "Aging action review", zone: "Ops desk", eta: "13:40", status: "In progress" },
            { label: "Exception approval", zone: "Control room", eta: "16:30", status: "Queued" },
        ],
        "System Administrator": [
            { label: "Access sync", zone: "Admin suite", eta: "08:15", status: "Ready" },
            { label: "Alert threshold check", zone: "Operations app", eta: "12:00", status: "In progress" },
            { label: "Audit closeout", zone: "Server room", eta: "18:20", status: "Queued" },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getWarehouseZoneOverview(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.zoneOverview;
    }

    const roleMap = {
        "Warehouse Staff": [
            { zone: "Zone A", usage: "72%", status: "Stable", detail: "4 pallets awaiting pick review" },
            { zone: "Zone B", usage: "86%", status: "Busy", detail: "Inbound staging active" },
            { zone: "Zone C", usage: "58%", status: "Healthy", detail: "Production reserve available" },
        ],
        "Procurement Staff": [
            { zone: "Dock 3", usage: "81%", status: "Busy", detail: "Supplier delivery window open" },
            { zone: "Hold Bay B", usage: "64%", status: "Stable", detail: "Backorder materials staged" },
            { zone: "Cold Storage", usage: "49%", status: "Healthy", detail: "Replenishment risk low" },
        ],
        "Production Staff": [
            { zone: "Line 3", usage: "76%", status: "Busy", detail: "Material issue is scheduled" },
            { zone: "Zone A", usage: "69%", status: "Stable", detail: "FEFO batches queued" },
            { zone: "Zone C", usage: "51%", status: "Healthy", detail: "Reserve stock comfortable" },
        ],
        "Quality Assurance (QA)": [
            { zone: "QA Bay", usage: "66%", status: "Busy", detail: "Sample checks active" },
            { zone: "Hold Area", usage: "77%", status: "Review", detail: "Damaged lots on watch" },
            { zone: "Approved Stock", usage: "60%", status: "Healthy", detail: "Release queue moving" },
        ],
        "Finance Staff": [
            { zone: "Control Room", usage: "45%", status: "Healthy", detail: "Inventory counts aligned" },
            { zone: "Finance Desk", usage: "72%", status: "Busy", detail: "Variance review in progress" },
            { zone: "Ops Desk", usage: "68%", status: "Stable", detail: "Closeout data synced" },
        ],
        Management: [
            { zone: "Zone A", usage: "69%", status: "Stable", detail: "Aging watch is controlled" },
            { zone: "Zone B", usage: "83%", status: "Busy", detail: "Storage pressure remains moderate" },
            { zone: "Dock 1", usage: "74%", status: "Busy", detail: "Outbound flow under review" },
        ],
        "System Administrator": [
            { zone: "Admin Suite", usage: "40%", status: "Healthy", detail: "No active access issues" },
            { zone: "Ops App", usage: "68%", status: "Stable", detail: "Alert sync healthy" },
            { zone: "Server Room", usage: "57%", status: "Healthy", detail: "Monitoring systems normal" },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getRoleInsights(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.insights;
    }

    const roleMap = {
        "Warehouse Staff": [
            "Cycle counts are on pace for the week.",
            "Two storage zones need review for congestion.",
            "Inbound receipt backlog is manageable today.",
        ],
        "Procurement Staff": [
            "Urgent replacement orders are below threshold.",
            "Supplier lead times remain stable for 70% of items.",
            "Shortage risk is concentrated in one supplier segment.",
        ],
        "Production Staff": [
            "Production line demand is stable across shifts.",
            "FEFO queue is manageable with current release plans.",
            "Material release timing matches weekly schedule.",
        ],
        "Quality Assurance (QA)": [
            "Inspection backlog is trending down this week.",
            "Two lots require priority signoff before release.",
            "Quality exception rate remains within tolerance.",
        ],
        "Finance Staff": [
            "Inventory write-down risk is elevated but controlled.",
            "Loss exposure is concentrated in expired inventory.",
            "Month-end valuation variance remains within target range.",
        ],
        Management: [
            "Operational risk is concentrated in expiration handling.",
            "Department KPIs are stable after the recent review.",
            "Stock health shows a moderate aging trend this month.",
        ],
        "System Administrator": [
            "User access remains stable across departments.",
            "Alert thresholds are within expected variance bands.",
            "System sync health remains green across business hours.",
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getRecentActivity(role) {
    if (role === "Sales & Distribution Staff") {
        return salesDistributionStaff.recentActivity;
    }

    const roleMap = {
        "Warehouse Staff": [
            "Cycle count completed in Zone A",
            "Inbound shipment cleared for staging",
            "Damaged pallet flagged for inspection",
        ],
        "Procurement Staff": [
            "Supplier confirmation received for reorder lot",
            "Shortage alert reviewed with sourcing team",
            "Lead time variance report shared",
        ],
        "Production Staff": [
            "Material release approved for Line 3",
            "FEFO queue refreshed for this shift",
            "Production handoff completed at 14:00",
        ],
        "Quality Assurance (QA)": [
            "QA hold released for approved batch",
            "Sample review completed for three lots",
            "Compliance notes recorded for received stock",
        ],
        "Finance Staff": [
            "Variance report posted for month-end close",
            "Expired stock exposure reviewed with ops",
            "Inventory valuation snapshot refreshed",
        ],
        Management: [
            "KPI review submitted to leadership",
            "Aging exception summary distributed",
            "Board-ready operational snapshot shared",
        ],
        "System Administrator": [
            "User access review closed successfully",
            "Alert threshold tuning saved",
            "Sync check passed across operations apps",
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

const roleTheme = {
    "Warehouse Staff": { accent: "#1e2024", accentStrong: "#0b0d10", accentSoft: "#f3f4f6" },
    "Procurement Staff": { accent: "#2a2d31", accentStrong: "#17191d", accentSoft: "#eef0f3" },
    "Production Staff": { accent: "#3b3f45", accentStrong: "#1d2126", accentSoft: "#f0f2f4" },
    "Quality Assurance (QA)": { accent: "#5a5d61", accentStrong: "#2a2d31", accentSoft: "#f2f3f5" },
    "Finance Staff": { accent: "#4c4f53", accentStrong: "#1d1f22", accentSoft: "#eef1f4" },
    Management: { accent: "#2a2d31", accentStrong: "#101214", accentSoft: "#f4f5f6" },
    "System Administrator": { accent: "#3d3f44", accentStrong: "#17191c", accentSoft: "#f1f3f5" },
};

const authState = {
    isAuthenticated: false,
    hasAccounts: false,
    role: "Warehouse Staff",
    email: "",
    fullName: "",
};

let csrfToken = "";
const readAlertIds = new Set();
const appView = document.getElementById("appView");
const pageTitle = document.getElementById("pageTitle");
const pageSubtitle = document.getElementById("pageSubtitle");
const modalLayer = document.getElementById("modalLayer");
const modalContent = document.getElementById("modalContent");
const toast = document.getElementById("toast");
let toastTimeout;
let returnFocusElement = null;
let currentPage = "dashboard";

function getAllowedPages() {
    return rolePermissions[authState.role] || ["dashboard"];
}

function getRoleInitials(role) {
    const initials = role
        .split(/\s|\(|\)|&|-/)
        .filter(Boolean)
        .map(part => part[0]?.toUpperCase() ?? "")
        .join("")
        .slice(0, 2);

    return initials || "WM";
}

function getNameInitials(name) {
    const words = String(name || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return "WM";
    }

    const initials = words.slice(0, 2)
        .map(word => word[0]?.toUpperCase() ?? "")
        .join("");

    return initials || "WM";
}

function applyRoleTheme() {
    const theme = authState.role === "Sales & Distribution Staff"
        ? salesDistributionStaff.theme
        : roleTheme[authState.role] || roleTheme["Warehouse Staff"];
    const root = document.documentElement;

    root.style.setProperty("--role-accent", theme.accent);
    root.style.setProperty("--role-accent-strong", theme.accentStrong);
    root.style.setProperty("--role-accent-soft", theme.accentSoft);
    root.style.setProperty("--blue", theme.accent);
    root.style.setProperty("--blue-soft", theme.accentSoft);
    root.style.setProperty("--blue-dark", theme.accentStrong);
    document.body.dataset.role = authState.role;
}

function syncProfile() {
    const sidebarUserName = document.querySelector(".sidebar-user strong");
    const profileName = document.querySelector(".profile-copy strong");
    const sidebarUserSmall = document.querySelector(".sidebar-user small");
    const profileSmall = document.querySelector(".profile-copy small");
    const sidebarAvatars = document.querySelectorAll(".sidebar-user .avatar");
    const profileAvatars = document.querySelectorAll(".profile .avatar");
    const accountName = authState.fullName || authState.role;
    const accountInitials = getNameInitials(accountName);

    applyRoleTheme();

    if (sidebarUserName) {
        sidebarUserName.textContent = accountName;
    }

    if (profileName) {
        profileName.textContent = accountName;
    }

    if (sidebarUserSmall) {
        sidebarUserSmall.textContent = authState.isAuthenticated ? authState.role : "Inventory team";
    }

    if (profileSmall) {
        profileSmall.textContent = authState.isAuthenticated ? authState.role : "Inventory team";
    }

    [...sidebarAvatars, ...profileAvatars].forEach(element => {
        element.textContent = accountInitials;
    });

    document.querySelectorAll("[data-page]").forEach(link => {
        const page = link.dataset.page;
        const isVisible = page === "logout" || getAllowedPages().includes(page);
        link.hidden = !isVisible;
    });
}

function showAuthError(message) {
    const errorMessage = document.getElementById("authError");

    if (errorMessage) {
        errorMessage.textContent = message;
        errorMessage.hidden = false;
    }
}

function showAuthMode(mode, message = "", email = "") {
    appView.innerHTML = mode === "signup"
        ? renderSignupPage(message)
        : renderLoginPage(message, email);
    const authMessage = appView.querySelector(".auth-message");

    if (authMessage) {
        authMessage.id = "authMessage";
    }

    pageTitle.textContent = mode === "signup" ? "Create account" : "Sign in";
    pageSubtitle.textContent = "Warehouse Management";
    document.title = `${mode === "signup" ? "Sign up" : "Sign in"} | Warehouse Management`;
}

function applyAuthState() {
    const sidebar = document.querySelector(".sidebar");
    const topbar = document.querySelector(".topbar");

    if (!sidebar || !topbar) {
        return;
    }

    sidebar.hidden = !authState.isAuthenticated;
    topbar.hidden = !authState.isAuthenticated;
    document.body.classList.toggle("authenticated", authState.isAuthenticated);
    modalLayer.hidden = true;
    document.body.classList.remove("modal-open");

    syncProfile();

    if (!authState.isAuthenticated) {
        const mode = authState.hasAccounts ? "login" : "signup";
        showAuthMode(mode);
        return;
    }

    pageTitle.textContent = `${authState.role}`;
    pageSubtitle.textContent = `Role workspace for ${authState.role}.`;
    document.title = `${authState.role} | Warehouse Management`;
}

async function sendAuthRequest(endpoint, data) {
    const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify(data),
    });
    const payload = await response.json();

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {}).flat()[0];
        throw new Error(validationMessage || payload.message || `The request failed (${response.status}).`);
    }

    return payload;
}

async function registerAccount(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const name = String(formData.get("fullName") || "").trim();
    const email = String(formData.get("email") || "").trim().toLowerCase();
    const role = String(formData.get("role") || "");
    const password = String(formData.get("password") || "");
    const passwordConfirmation = String(formData.get("password_confirmation") || "");
    const payload = await sendAuthRequest("/warehouse/auth/register", {
        name,
        email,
        role,
        password,
        password_confirmation: passwordConfirmation,
    });

    authState.hasAccounts = true;
    showAuthMode("login", payload.message, email);
}

async function loginUser(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const email = String(formData.get("email") || "").trim().toLowerCase();
    const password = String(formData.get("password") || "");
    const payload = await sendAuthRequest("/warehouse/auth/login", { email, password });

    csrfToken = payload.csrfToken;
    authState.role = payload.user.role;
    authState.email = payload.user.email;
    authState.fullName = payload.user.name;
    authState.isAuthenticated = true;
    authState.hasAccounts = true;

    window.location.hash = "dashboard";
    applyAuthState();
    handlePageChange();
}

async function logoutUser() {
    const payload = await sendAuthRequest("/warehouse/auth/logout", {});
    csrfToken = payload.csrfToken;
    authState.isAuthenticated = false;
    authState.role = "Warehouse Staff";
    authState.email = "";
    authState.fullName = "";
    window.location.hash = "";
    applyAuthState();
}

function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
    })[character]);
}

function dateAtLocalMidnight(dateString) {
    const [year, month, day] = dateString.split("-").map(Number);
    return new Date(year, month - 1, day);
}

function daysBetween(fromDate, toDate) {
    const from = dateAtLocalMidnight(fromDate);
    const to = dateAtLocalMidnight(toDate);
    return Math.round((to - from) / 86400000);
}

function todayString() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

function getInventoryAge(product) {
    return Math.max(0, daysBetween(product.received, todayString()));
}

function getDaysRemaining(product) {
    return daysBetween(todayString(), product.expiration);
}

function getAgeLabel(age) {
    if (age > 60) {
        return "Critical";
    }

    if (age > 30) {
        return "Aging";
    }

    return "Normal";
}

function getDateLabel(dateString) {
    return dateAtLocalMidnight(dateString).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
}

function getStatusClass(status) {
    return status.toLowerCase().replaceAll(" ", "-");
}

function getExpiryLabel(daysRemaining) {
    if (daysRemaining < 0) {
        const daysExpired = Math.abs(daysRemaining);
        return `Expired ${daysExpired} ${daysExpired === 1 ? "day" : "days"} ago`;
    }

    if (daysRemaining === 0) {
        return "Expires today";
    }

    return `${daysRemaining} ${daysRemaining === 1 ? "day" : "days"} left`;
}

function getActiveAlerts() {
    const alerts = [];

    inventory.forEach(product => {
        const age = getInventoryAge(product);
        const daysRemaining = getDaysRemaining(product);
        const manuallyExpired = product.status === "Expired";

        if (age > 30 && !manuallyExpired && daysRemaining >= 0) {
            const critical = age > 60;
            alerts.push({
                id: `aging-${product.id}`,
                type: "Aging inventory",
                category: "aging",
                product,
                priority: critical ? "Critical" : "Medium",
                date: todayString(),
                description: `${product.name} has been in inventory for ${age} days.`,
            });
        }

        if (daysRemaining < 0 || manuallyExpired) {
            alerts.push({
                id: `expired-${product.id}`,
                type: "Expired product",
                category: "expired",
                product,
                priority: "Critical",
                date: todayString(),
                description: `${product.name} is expired and should be removed from available stock.`,
            });
        } else if (daysRemaining <= 30) {
            alerts.push({
                id: `expiration-${product.id}`,
                type: "Expiration alert",
                category: "expiration",
                product,
                priority: daysRemaining <= 7 ? "High" : "Medium",
                date: todayString(),
                description: `${product.name} will expire in ${daysRemaining} ${daysRemaining === 1 ? "day" : "days"}.`,
            });
        }
    });

    return alerts.sort((first, second) => {
        const priorityOrder = { Critical: 0, High: 1, Medium: 2 };
        return priorityOrder[first.priority] - priorityOrder[second.priority];
    });
}

function updateAlertCounts() {
    const unreadCount = getActiveAlerts().filter(alert => !readAlertIds.has(alert.id)).length;
    const headerCount = document.getElementById("headerAlertCount");
    const navCount = document.getElementById("navAlertCount");

    [headerCount, navCount].forEach(countElement => {
        countElement.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
        countElement.hidden = unreadCount === 0;
    });
}

function renderStatCard(label, value, note, color, icon) {
    const resolvedIcon = icon || (typeof getStatCardIcon === "function" ? getStatCardIcon(label) : "");
    return `
        <article class="stat-card">
            <div class="stat-icon ${color}">${resolvedIcon}</div>
            <div class="stat-copy">
                <span>${label}</span>
                <strong>${value}</strong>
                <small>${note}</small>
            </div>
        </article>
    `;
}

function getPriorityClass(priority) {
    return priority.toLowerCase();
}

function renderEmptyState(title, description) {
    return `<div class="empty-state"><span class="empty-icon">✓</span><strong>${title}</strong><p>${description}</p></div>`;
}

function renderPage(page) {
    if (!authState.isAuthenticated) {
        applyAuthState();
        return;
    }

    const allowedPages = getAllowedPages();
    const safePage = allowedPages.includes(page) ? page : "dashboard";

    currentPage = safePage;
    const metadata = pageMetadata[safePage] || pageMetadata.dashboard;
    const roleHeader = safePage === "dashboard" ? `${authState.role}` : `${authState.role} · ${metadata.title}`;
    const roleSubtitle = safePage === "dashboard"
        ? `Role workspace for ${authState.role}.`
        : `${metadata.subtitle}`;

    pageTitle.textContent = roleHeader;
    pageSubtitle.textContent = roleSubtitle;
    document.title = `${authState.role} | ${metadata.title} | Warehouse Management`;

    document.querySelectorAll("[data-page]").forEach(link => {
        const isActive = link.dataset.page === safePage;
        link.classList.toggle("active", isActive);
        if (isActive) {
            link.setAttribute("aria-current", "page");
        } else {
            link.removeAttribute("aria-current");
        }
    });

    if (safePage === "dashboard") {
        appView.innerHTML = renderDashboard();
    } else if (["inventory", "aging", "expiring"].includes(safePage)) {
        appView.innerHTML = renderTablePage(safePage);
        renderInventoryRows(safePage);
    } else if (safePage === "operations") {
        appView.innerHTML = renderOperationsPage();
    } else if (safePage === "alerts") {
        appView.innerHTML = renderAlertsPage();
    } else if (safePage === "settings") {
        appView.innerHTML = renderSettings();
    } else {
        appView.innerHTML = renderDashboard();
        showToast("Sign-out is not connected in this sample module.");
    }

    updateAlertCounts();
}

function openModal(content) {
    returnFocusElement = document.activeElement;
    modalContent.innerHTML = content;
    modalLayer.hidden = false;
    document.body.classList.add("modal-open");
    modalContent.querySelector("button, select")?.focus();
}

function closeModal() {
    if (modalLayer.hidden) {
        return;
    }

    modalLayer.hidden = true;
    document.body.classList.remove("modal-open");
    modalContent.innerHTML = "";
    returnFocusElement?.focus();
}

function showProductDetails(product) {
    const age = getInventoryAge(product);
    const daysRemaining = getDaysRemaining(product);

    openModal(`
        <div class="modal-header">
            <div><p class="eyebrow">INVENTORY RECORD</p><h2 id="modalTitle">Product details</h2></div>
            <button class="icon-button" type="button" data-action="close-modal" aria-label="Close dialog"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="detail-product-heading">${renderProductName(product)}${renderStatusBadge(product.status)}</div>
        <div class="detail-grid">
            <div><span>Batch number</span><strong class="mono">${escapeHTML(product.batch)}</strong></div>
            <div><span>Quantity</span><strong>${product.quantity} units</strong></div>
            <div><span>Date received</span><strong>${getDateLabel(product.received)}</strong></div>
            <div><span>Inventory age</span><strong>${age} days · ${getAgeLabel(age)}</strong></div>
            <div><span>Expiration date</span><strong>${getDateLabel(product.expiration)}</strong></div>
            <div><span>Days remaining</span><strong class="${daysRemaining < 0 ? "text-red" : daysRemaining <= 30 ? "text-orange" : ""}">${escapeHTML(getExpiryLabel(daysRemaining))}</strong></div>
        </div>
        <div class="modal-footer">
            <button class="secondary-button" type="button" data-action="close-modal">Close</button>
            <button class="primary-button" type="button" data-action="update-from-details" data-product-id="${escapeHTML(product.id)}">Update status</button>
        </div>
    `);
}

function showStatusModal(product) {
    openModal(`
        <form id="statusForm" data-product-id="${escapeHTML(product.id)}">
            <div class="modal-header">
                <div><p class="eyebrow">INVENTORY ACTION</p><h2 id="modalTitle">Update inventory status</h2></div>
                <button class="icon-button" type="button" data-action="close-modal" aria-label="Close dialog"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <p class="modal-intro">Change the stock status for this batch. Your update will be reflected throughout the dashboard.</p>
            <div class="selected-product-card">
                ${renderProductName(product)}
                <span>Batch <strong class="mono">${escapeHTML(product.batch)}</strong></span>
                <span>${product.quantity} units</span>
            </div>
            <label class="form-label" for="newProductStatus">New status</label>
            <select id="newProductStatus" name="status" required>
                ${statusOptions.map(status => `<option value="${escapeHTML(status)}" ${product.status === status ? "selected" : ""}>${escapeHTML(status)}</option>`).join("")}
            </select>
            <p class="current-status-note">Current status: ${renderStatusBadge(product.status)}</p>
            <div class="modal-footer">
                <button class="secondary-button" type="button" data-action="close-modal">Cancel</button>
                <button class="primary-button" type="submit">Save changes</button>
            </div>
        </form>
    `);
}

function showToast(message) {
    clearTimeout(toastTimeout);
    toast.textContent = message;
    toast.classList.add("visible");
    toastTimeout = setTimeout(() => toast.classList.remove("visible"), 2800);
}

function handlePageChange() {
    if (!authState.isAuthenticated) {
        applyAuthState();
        return;
    }

    const requestedPage = window.location.hash.slice(1);
    const page = requestedPage;

    if (page === "logout") {
        logoutUser().catch(error => showToast(error.message));
        return;
    }

    renderPage(pageMetadata[page] ? page : "dashboard");
}

appView.addEventListener("submit", async event => {
    if (!["signupForm", "loginForm"].includes(event.target.id)) {
        return;
    }

    try {
        if (event.target.id === "signupForm") {
            await registerAccount(event);
        } else {
            await loginUser(event);
        }
    } catch (error) {
        showAuthError(error instanceof Error ? error.message : "Unable to complete account access. Please try again.");
    }
});

appView.addEventListener("input", event => {
    if (event.target.id === "productSearch") {
        renderInventoryRows(currentPage);
        document.getElementById("productSearch").focus();
    }
});

appView.addEventListener("change", event => {
    if (appView.contains(event.target) && ["statusFilter", "sortProducts"].includes(event.target.id)) {
        renderInventoryRows(currentPage);
    }
});

appView.addEventListener("click", event => {
    const authModeButton = event.target.closest("[data-auth-mode]");

    if (authModeButton) {
        showAuthMode(authModeButton.dataset.authMode);
        return;
    }

    const quickAction = event.target.closest(".quick-action");

    if (quickAction) {
        const page = quickAction.dataset.page;

        if (!authState.isAuthenticated) {
            event.preventDefault();
            return;
        }

        if (page && getAllowedPages().includes(page)) {
            window.location.hash = page;
            event.preventDefault();
            return;
        }

        event.preventDefault();
        window.location.hash = getAllowedPages()[0] || "dashboard";
        return;
    }

    const actionButton = event.target.closest("[data-action]");

    if (!actionButton) {
        return;
    }

    const { action, productId, alertId } = actionButton.dataset;

    if (action === "details" || action === "view-alert-product") {
        const product = inventory.find(item => item.id === productId);

        if (product) {
            showProductDetails(product);
        }
    } else if (action === "update-status") {
        const product = inventory.find(item => item.id === productId);

        if (product) {
            showStatusModal(product);
        }
    } else if (action === "prioritize-distribution") {
        const product = inventory.find(item => item.id === productId);

        if (
            authState.role !== "Sales & Distribution Staff"
            || !product
            || product.status === "Expired"
            || getDaysRemaining(product) < 0
            || getDaysRemaining(product) > 30
        ) {
            return;
        }

        product.status = "For Distribution";
        renderPage(currentPage);
        showToast(`${product.name} prioritized for distribution.`);
    } else if (action === "mark-read") {
        readAlertIds.add(alertId);
        renderPage("alerts");
    } else if (action === "mark-all-read") {
        getActiveAlerts().forEach(alert => readAlertIds.add(alert.id));
        renderPage("alerts");
        showToast("All active alerts marked as read.");
    }
});

modalLayer.addEventListener("click", event => {
    if (event.target === modalLayer || event.target.closest('[data-action="close-modal"]')) {
        closeModal();
        return;
    }

    const updateFromDetails = event.target.closest('[data-action="update-from-details"]');

    if (updateFromDetails) {
        const product = inventory.find(item => item.id === updateFromDetails.dataset.productId);

        if (product) {
            showStatusModal(product);
        }
    }
});

modalContent.addEventListener("submit", event => {
    if (event.target.id !== "statusForm") {
        return;
    }

    event.preventDefault();
    const product = inventory.find(item => item.id === event.target.dataset.productId);
    const newStatus = new FormData(event.target).get("status");

    if (!product || !statusOptions.includes(newStatus)) {
        showToast("Choose a valid inventory status.");
        return;
    }

    product.status = newStatus;
    closeModal();
    renderPage(currentPage);
    showToast(`${product.name} status updated to ${newStatus}.`);
});

document.getElementById("notificationButton").addEventListener("click", () => {
    if (!authState.isAuthenticated) {
        return;
    }

    window.location.hash = "alerts";
});

modalLayer.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        closeModal();
    }
});

document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        closeModal();
    }
});

document.querySelectorAll("[data-page]").forEach(link => {
    link.addEventListener("click", event => {
        if (!authState.isAuthenticated) {
            event.preventDefault();
            return;
        }

        const page = link.dataset.page;

        if (page === "logout") {
            event.preventDefault();
            logoutUser().catch(error => showToast(error.message));
            return;
        }

        if (getAllowedPages().includes(page)) {
            window.location.hash = page;
        } else {
            event.preventDefault();
            window.location.hash = getAllowedPages()[0];
        }
    });
});

async function initializeAuthentication() {
    try {
        const response = await fetch("/warehouse/auth/session", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });

        if (!response.ok) {
            throw new Error(`Unable to check the sign-in session (${response.status}).`);
        }

        const session = await response.json();
        csrfToken = session.csrfToken;
        authState.hasAccounts = session.hasAccounts;

        if (session.authenticated) {
            if (
                !session.user
                || typeof session.user.name !== "string"
                || typeof session.user.email !== "string"
                || !Object.hasOwn(rolePermissions, session.user.role)
            ) {
                throw new Error("The signed-in account has invalid role data. Contact the system administrator.");
            }

            authState.isAuthenticated = true;
            authState.role = session.user.role;
            authState.email = session.user.email;
            authState.fullName = session.user.name;
        }

        applyAuthState();

        if (authState.isAuthenticated) {
            handlePageChange();
        }
    } catch (error) {
        authState.isAuthenticated = false;
        applyAuthState();
        showAuthMode(
            "login",
            error instanceof Error ? error.message : "Unable to connect to the warehouse authentication service.",
        );
    }
}

window.addEventListener("hashchange", handlePageChange);
applyAuthState();
initializeAuthentication();
