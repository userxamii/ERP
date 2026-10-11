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
    "Sales & Distribution Staff": ["dashboard", "operations", "alerts", "settings"],
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
    "Sales & Distribution Staff": [
        "Review aggregate material availability and risk summaries.",
        "Track supply constraints that could affect distribution plans.",
        "Use read-only visibility to support customer commitments.",
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

function getRoleDashboardCards(role) {
    const totalInStock = inventory.reduce((sum, product) => sum + product.quantity, 0);
    const lowStock = inventory.filter(product => product.quantity < 40).length;
    const agingItems = inventory.filter(product => getInventoryAge(product) > 30 && getDaysRemaining(product) >= 0 && product.status !== "Expired").length;
    const expiringSoon = inventory.filter(product => getDaysRemaining(product) <= 30 && product.status !== "Expired").length;

    const roleMap = {
        "Warehouse Staff": [
            { label: "Receiving queue", value: "4 batches", note: "Awaiting intake review", color: "blue" },
            { label: "Stock moves", value: "12 entries", note: "This week", color: "orange" },
            { label: "Aging stock", value: agingItems, note: "Older than 30 days", color: "amber" },
        ],
        "Procurement Staff": [
            { label: "Reorder alerts", value: lowStock, note: "Below target stock", color: "orange" },
            { label: "Expected deliveries", value: "3 orders", note: "In transit", color: "blue" },
            { label: "Risk items", value: expiringSoon, note: "Priority purchases", color: "red" },
        ],
        "Production Staff": [
            { label: "Available units", value: totalInStock.toLocaleString(), note: "Usable stock", color: "blue" },
            { label: "Ready batches", value: "5", note: "QA cleared", color: "green" },
            { label: "FEFO queue", value: expiringSoon, note: "Next expiry review", color: "amber" },
        ],
        "Sales & Distribution Staff": [
            { label: "Available supply", value: totalInStock.toLocaleString(), note: "Units available", color: "blue" },
            { label: "Supply risk", value: "2 items", note: "Potential constraints", color: "orange" },
            { label: "Distribution plan", value: "6 orders", note: "Current visibility", color: "green" },
        ],
        "Quality Assurance (QA)": [
            { label: "Pending inspection", value: "3 batches", note: "Queue", color: "orange" },
            { label: "Released stock", value: "18", note: "Approved batches", color: "green" },
            { label: "Hold review", value: "2 items", note: "Requires action", color: "red" },
        ],
        "Finance Staff": [
            { label: "Estimated value", value: "$48.6K", note: "Inventory value", color: "blue" },
            { label: "Loss exposure", value: "$8.4K", note: "Expired / rejected", color: "red" },
            { label: "Valuation cycles", value: "12", note: "This month", color: "amber" },
        ],
        Management: [
            { label: "Active batches", value: inventory.length, note: "Tracked records", color: "blue" },
            { label: "Aging risk", value: agingItems, note: "High-risk stock", color: "orange" },
            { label: "Expired value", value: "$7.1K", note: "Exposure tracked", color: "red" },
        ],
        "System Administrator": [
            { label: "User accounts", value: "26", note: "Active users", color: "blue" },
            { label: "Audit events", value: "184", note: "This week", color: "green" },
            { label: "Alert rules", value: "9", note: "Configured thresholds", color: "amber" },
        ],
    };

    return roleMap[role] || roleMap["Warehouse Staff"];
}

function getRoleQuickActions(role) {
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
        "Sales & Distribution Staff": [
            { label: "Operations board", page: "operations" },
            { label: "Supply summary", page: "dashboard" },
            { label: "Alert center", page: "alerts" },
            { label: "Inventory view", page: "inventory" },
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
        "Sales & Distribution Staff": [
            { title: "Confirm outbound capacity", status: "Pending", detail: "Check stock for 6 active orders" },
            { title: "Flag supply risk", status: "Watch", detail: "One route impacted by aging stock" },
            { title: "Review dispatch window", status: "Ready", detail: "No major shipping constraints" },
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
        "Sales & Distribution Staff": [
            { time: "08:50", title: "Dispatch window", note: "Outbound plan refreshed for the next customer cycle." },
            { time: "12:05", title: "Capacity check", note: "Transport slots remain stable for active orders." },
            { time: "17:15", title: "Risk update", note: "One route remains under supply watch due to aging stock." },
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
        "Sales & Distribution Staff": [
            { label: "Customer dispatch", zone: "Dock 1", eta: "10:40", status: "Queued" },
            { label: "Cold chain release", zone: "Zone K", eta: "14:50", status: "Ready" },
            { label: "Return transfer", zone: "Dock 2", eta: "18:10", status: "Review" },
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

function getRoleInsights(role) {
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
        "Sales & Distribution Staff": [
            "Outbound dispatch is aligned to current demand.",
            "One route is exposed to supply risk from aging stock.",
            "Customer orders remain within stock availability limits.",
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
        "Sales & Distribution Staff": [
            "Dispatch plan refreshed for active orders",
            "Outbound stock reservation updated",
            "Risk flag shared with fulfillment team",
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

const authState = {
    isAuthenticated: localStorage.getItem("warehouse-auth") === "true",
    role: localStorage.getItem("warehouse-role") || "Warehouse Staff",
};

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

function syncProfile() {
    const sidebarUserName = document.querySelector(".sidebar-user strong");
    const profileName = document.querySelector(".profile-copy strong");
    const sidebarUserSmall = document.querySelector(".sidebar-user small");
    const profileSmall = document.querySelector(".profile-copy small");
    const sidebarAvatars = document.querySelectorAll(".sidebar-user .avatar");
    const profileAvatars = document.querySelectorAll(".profile .avatar");
    const roleInitials = getRoleInitials(authState.role);

    if (sidebarUserName) {
        sidebarUserName.textContent = authState.role;
    }

    if (profileName) {
        profileName.textContent = authState.role;
    }

    if (sidebarUserSmall) {
        sidebarUserSmall.textContent = authState.isAuthenticated ? "Signed in" : "Inventory team";
    }

    if (profileSmall) {
        profileSmall.textContent = authState.isAuthenticated ? "Staff account" : "Inventory team";
    }

    sidebarAvatars.forEach(element => {
        element.textContent = roleInitials;
    });

    profileAvatars.forEach(element => {
        element.textContent = roleInitials;
    });

    document.querySelectorAll("[data-page]").forEach(link => {
        const page = link.dataset.page;
        const isVisible = page === "logout" || getAllowedPages().includes(page);
        link.hidden = !isVisible;
    });
}

function renderLoginPage() {
    return `
        <div class="login-shell">
            <div class="login-card">
                <div class="login-header">
                    <p class="eyebrow">SECURE ACCESS</p>
                    <h2>Warehouse management</h2>
                </div>
                <form id="loginForm" class="login-form">
                    <label>
                        <span>Role</span>
                        <select name="role" aria-label="Select role">
                            ${Object.keys(rolePermissions).map(role => `<option value="${escapeHTML(role)}" ${role === authState.role ? "selected" : ""}>${escapeHTML(role)}</option>`).join("")}
                        </select>
                    </label>
                    <label>
                        <span>Username</span>
                        <input name="username" type="text" value="warehouse.staff" aria-label="Username" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input name="password" type="password" value="password123" aria-label="Password" required>
                    </label>
                    <button type="submit" class="primary-button login-button">Sign in</button>
                </form>
            </div>
        </div>
    `;
}

function applyAuthState() {
    const sidebar = document.querySelector(".sidebar");
    const topbar = document.querySelector(".topbar");

    if (!sidebar || !topbar) {
        return;
    }

    sidebar.hidden = !authState.isAuthenticated;
    topbar.hidden = !authState.isAuthenticated;
    modalLayer.hidden = true;
    document.body.classList.remove("modal-open");

    syncProfile();

    if (!authState.isAuthenticated) {
        pageTitle.textContent = "Warehouse Access";
        pageSubtitle.textContent = "Sign in to continue.";
        appView.innerHTML = renderLoginPage();
        return;
    }

    pageTitle.textContent = pageMetadata.dashboard.title;
    pageSubtitle.textContent = pageMetadata.dashboard.subtitle;
}

function loginUser(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const selectedRole = String(formData.get("role") || authState.role);

    authState.role = selectedRole;
    authState.isAuthenticated = true;
    localStorage.setItem("warehouse-auth", "true");
    localStorage.setItem("warehouse-role", selectedRole);

    window.location.hash = "dashboard";
    applyAuthState();
    handlePageChange();
}

function logoutUser() {
    authState.isAuthenticated = false;
    authState.role = "Warehouse Staff";
    localStorage.removeItem("warehouse-auth");
    localStorage.removeItem("warehouse-role");
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
    return `
        <article class="stat-card">
            <div class="stat-icon ${color}">${icon}</div>
            <div class="stat-copy">
                <span>${label}</span>
                <strong>${value}</strong>
                <small>${note}</small>
            </div>
        </article>
    `;
}

function renderDashboard() {
    const totalUnits = inventory.reduce((total, product) => total + product.quantity, 0);
    const agingCount = inventory.filter(product => (
        getInventoryAge(product) > 30
        && getDaysRemaining(product) >= 0
        && product.status !== "Expired"
    )).length;
    const expiringCount = inventory.filter(product => {
        const daysRemaining = getDaysRemaining(product);
        return daysRemaining >= 0 && daysRemaining <= 30 && product.status !== "Expired";
    }).length;
    const expiredCount = inventory.filter(product => (
        getDaysRemaining(product) < 0 || product.status === "Expired"
    )).length;
    const alerts = getActiveAlerts().slice(0, 4);
    const roleSummary = roleHighlights[authState.role] || roleHighlights["Warehouse Staff"];
    const roleCards = getRoleDashboardCards(authState.role);
    const quickActions = getRoleQuickActions(authState.role);
    const operationalQueue = getRoleOperationalQueue(authState.role);
    const dockSchedule = getWarehouseDockSchedule(authState.role);
    const insights = getRoleInsights(authState.role);
    const recentActivity = getRecentActivity(authState.role);

    return `
        <section class="welcome-row">
            <div>
                <h2>Good ${getGreeting()}, ${authState.role}</h2>
                <p>Here is the latest overview of your warehouse inventory.</p>
            </div>
            <span class="date-chip">${getDateLabel(todayString())}</span>
        </section>

        <section class="stats-grid" aria-label="Inventory summary">
            ${roleCards.map(card => renderStatCard(card.label, card.value, card.note, card.color, card.label === "Receiving queue" ? '<svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4.3 7.7 7.7 4.4 7.7-4.4M12 12v9M8 5.2l8 4.6"/></svg>' : card.label === "Reorder alerts" ? '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3v12"/><path d="M5.5 17.5 12 21l6.5-3.5"/><path d="M5.5 9.5 12 13l6.5-3.5"/></svg>' : card.label === "Available units" ? '<svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4.3 7.7 7.7 4.4 7.7-4.4M12 12v9M8 5.2l8 4.6"/></svg>' : card.label === "Estimated value" ? '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M17 6.5A4.5 4.5 0 0 0 12 5a4.5 4.5 0 0 0 0 9 4.5 4.5 0 0 1 0 9 4.5 4.5 0 0 1-5-1.5"/></svg>' : '<svg viewBox="0 0 24 24" fill="none"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.3 7.2 7.7 4.3 7.7-4.3M12 11.5V21"/></svg>')).join("")}
        </section>

        <section class="panel role-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">ROLE FOCUS</p>
                    <h2>${authState.role} overview</h2>
                </div>
            </div>
            <ul class="role-highlights">
                ${roleSummary.map(item => `<li>${escapeHTML(item)}</li>`).join("")}
            </ul>
        </section>

        <section class="panel quick-actions-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">QUICK ACTIONS</p>
                    <h2>${authState.role} next steps</h2>
                </div>
            </div>
            <div class="quick-action-list">
                ${quickActions.map(action => `<a class="quick-action" href="#${escapeHTML(action.page)}" data-page="${escapeHTML(action.page)}">${escapeHTML(action.label)}</a>`).join("")}
            </div>
        </section>

        <section class="panel operations-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">OPERATIONS QUEUE</p>
                    <h2>${authState.role} task list</h2>
                </div>
            </div>
            <div class="task-list">
                ${operationalQueue.map(task => `
                    <div class="task-row">
                        <div>
                            <strong>${escapeHTML(task.title)}</strong>
                            <small>${escapeHTML(task.detail)}</small>
                        </div>
                        <span class="task-status ${task.status.toLowerCase().replace(/\s+/g, "-")}">${escapeHTML(task.status)}</span>
                    </div>
                `).join("")}
            </div>
        </section>

        <section class="panel insights-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">INSIGHTS</p>
                    <h2>${authState.role} operational summary</h2>
                </div>
            </div>
            <ul class="insight-list">
                ${insights.map(item => `<li>${escapeHTML(item)}</li>`).join("")}
            </ul>
        </section>

        <section class="panel activity-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">RECENT ACTIVITY</p>
                    <h2>${authState.role} updates</h2>
                </div>
            </div>
            <div class="activity-feed">
                ${recentActivity.map(item => `
                    <div class="activity-item">
                        <span class="activity-dot"></span>
                        <span>${escapeHTML(item)}</span>
                    </div>
                `).join("")}
            </div>
        </section>

        <section class="dashboard-grid">
            <article class="panel alert-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">NEEDS ATTENTION</p>
                        <h2>Inventory alerts</h2>
                    </div>
                    <a class="text-link" href="#alerts">View all <span aria-hidden="true">→</span></a>
                </div>
                ${alerts.length > 0
                    ? `<div class="alert-list">${alerts.map(renderDashboardAlert).join("")}</div>`
                    : renderEmptyState("You're all caught up", "There are no active inventory alerts.")}
            </article>

            <article class="panel snapshot-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">STOCK HEALTH</p>
                        <h2>Inventory age</h2>
                    </div>
                </div>
                <div class="age-legend">
                    <div><span class="legend-dot normal"></span><span>Normal <small>0–30 days</small></span><strong>${inventory.filter(product => getInventoryAge(product) <= 30).length}</strong></div>
                    <div><span class="legend-dot aging"></span><span>Aging <small>31–60 days</small></span><strong>${inventory.filter(product => getInventoryAge(product) > 30 && getInventoryAge(product) <= 60).length}</strong></div>
                    <div><span class="legend-dot critical"></span><span>Critical <small>61+ days</small></span><strong>${inventory.filter(product => getInventoryAge(product) > 60).length}</strong></div>
                </div>
                <a class="secondary-link" href="#aging">Review aging inventory <span aria-hidden="true">→</span></a>
            </article>
        </section>

        <section class="panel dock-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">DOCK & EQUIPMENT</p>
                    <h2>Current warehouse flow</h2>
                </div>
            </div>
            <div class="dock-list">
                ${dockSchedule.map(item => `
                    <div class="dock-item">
                        <div>
                            <strong>${escapeHTML(item.label)}</strong>
                            <small>${escapeHTML(item.zone)}</small>
                        </div>
                        <span class="dock-time">${escapeHTML(item.eta)}</span>
                        <span class="dock-status ${item.status.toLowerCase().replace(/\s+/g, "-")}">${escapeHTML(item.status)}</span>
                    </div>
                `).join("")}
            </div>
        </section>

        <section class="panel recent-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">STOCK OVERVIEW</p>
                    <h2>Recently received inventory</h2>
                </div>
                <a class="text-link" href="#inventory">View inventory <span aria-hidden="true">→</span></a>
            </div>
            ${renderCompactInventory()}
        </section>
    `;
}

function getGreeting() {
    const hour = new Date().getHours();

    if (hour < 12) {
        return "morning";
    }

    if (hour < 18) {
        return "afternoon";
    }

    return "evening";
}

function renderDashboardAlert(alert) {
    return `
        <button class="dashboard-alert" type="button" data-action="view-alert-product" data-product-id="${escapeHTML(alert.product.id)}">
            <span class="alert-indicator ${alert.category}">${renderAlertIcon(alert.category)}</span>
            <span class="dashboard-alert-copy">
                <strong>${escapeHTML(alert.description)}</strong>
                <small>${escapeHTML(alert.product.batch)} · ${escapeHTML(alert.type)}</small>
            </span>
            <span class="priority-tag ${getPriorityClass(alert.priority)}">${escapeHTML(alert.priority)}</span>
        </button>
    `;
}

function renderCompactInventory() {
    const recentProducts = [...inventory]
        .sort((first, second) => second.received.localeCompare(first.received))
        .slice(0, 4);

    return `
        <div class="table-wrap">
            <table class="data-table compact-table">
                <thead><tr><th>Product</th><th>Batch</th><th>Quantity</th><th>Date received</th><th>Age</th><th>Status</th></tr></thead>
                <tbody>${recentProducts.map(product => `
                    <tr>
                        <td>${renderProductName(product)}</td>
                        <td class="mono">${escapeHTML(product.batch)}</td>
                        <td>${product.quantity} units</td>
                        <td>${getDateLabel(product.received)}</td>
                        <td>${renderAgeBadge(getInventoryAge(product))}</td>
                        <td>${renderStatusBadge(product.status)}</td>
                    </tr>
                `).join("")}</tbody>
            </table>
        </div>
    `;
}

function renderProductName(product) {
    return `<span class="product-name"><span class="product-avatar">${escapeHTML(product.name.slice(-1))}</span><strong>${escapeHTML(product.name)}</strong></span>`;
}

function renderAgeBadge(age) {
    const label = getAgeLabel(age);
    return `<span class="age-badge ${label.toLowerCase()}"><span class="status-dot"></span>${age} days <small>${label}</small></span>`;
}

function renderStatusBadge(status) {
    return `<span class="status-badge ${getStatusClass(status)}"><span class="status-dot"></span>${escapeHTML(status)}</span>`;
}

function renderTablePage(page) {
    const tableSettings = {
        inventory: {
            title: "All inventory",
            description: `${inventory.length} batches currently tracked`,
            products: [...inventory],
            showReceived: true,
            aging: false,
            expiring: false,
        },
        aging: {
            title: "Aging inventory",
            description: "Products held for more than 30 days, prioritized by age",
            products: inventory.filter(product => (
                getInventoryAge(product) > 30
                && getDaysRemaining(product) >= 0
                && product.status !== "Expired"
            )),
            showReceived: true,
            aging: true,
            expiring: false,
        },
        expiring: {
            title: "Expiration watch",
            description: "Products expiring within 30 days and expired batches",
            products: inventory.filter(product => (
                getDaysRemaining(product) <= 30 || product.status === "Expired"
            )),
            showReceived: false,
            aging: false,
            expiring: true,
        },
    }[page];

    return `
        <section class="panel inventory-panel">
            <div class="panel-heading inventory-heading">
                <div>
                    <p class="eyebrow">${page === "aging" ? "STOCK HEALTH" : page === "expiring" ? "DATE MONITORING" : "WAREHOUSE STOCK"}</p>
                    <h2>${tableSettings.title}</h2>
                    <p class="panel-description">${tableSettings.description}</p>
                </div>
                <div class="table-toolbar">
                    <label class="search-field">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
                        <input id="productSearch" type="search" placeholder="Search products..." aria-label="Search products">
                    </label>
                    <label class="select-field">
                        <span class="visually-hidden">Filter by status</span>
                        <select id="statusFilter" aria-label="Filter by status">
                            <option value="">All statuses</option>
                            ${statusOptions.map(status => `<option value="${escapeHTML(status)}">${escapeHTML(status)}</option>`).join("")}
                        </select>
                    </label>
                    <label class="select-field sort-field">
                        <span class="visually-hidden">Sort inventory</span>
                        <select id="sortProducts" aria-label="Sort inventory">
                            <option value="name">Sort: Product name</option>
                            <option value="age-desc">Sort: Oldest received</option>
                            <option value="expiration">Sort: Expiration date</option>
                            <option value="received-desc">Sort: Recently received</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table inventory-table">
                    <thead><tr>
                        <th>Product</th>
                        <th>Batch number</th>
                        <th>Quantity</th>
                        ${tableSettings.showReceived ? "<th>Date received</th>" : ""}
                        ${tableSettings.showReceived ? "<th>Inventory age</th>" : ""}
                        <th>Expiration date</th>
                        ${tableSettings.expiring ? "<th>Days remaining</th>" : ""}
                        <th>Status</th>
                        ${tableSettings.expiring ? "<th>Priority</th>" : ""}
                        ${tableSettings.aging ? "<th>Recommended action</th>" : ""}
                        <th>Actions</th>
                    </tr></thead>
                    <tbody id="inventoryRows"></tbody>
                </table>
            </div>
            <div class="table-footer"><span id="tableResultCount"></span><span>Age and expiration are calculated from today's date.</span></div>
            <div id="tableEmptyState" class="table-empty" hidden></div>
        </section>
    `;
}

function getRecommendedAction(product) {
    const daysRemaining = getDaysRemaining(product);

    if (daysRemaining <= 7) {
        return "Prioritize distribution";
    }

    if (getInventoryAge(product) > 60) {
        return "Review for production";
    }

    return "Plan stock rotation";
}

function getExpiryPriority(product) {
    const daysRemaining = getDaysRemaining(product);

    if (daysRemaining < 0 || product.status === "Expired") {
        return "Critical";
    }

    if (daysRemaining <= 7) {
        return "High";
    }

    return "Medium";
}

function renderInventoryRows(page) {
    const rowsElement = document.getElementById("inventoryRows");

    if (!rowsElement) {
        return;
    }

    const searchTerm = document.getElementById("productSearch").value.trim().toLocaleLowerCase();
    const selectedStatus = document.getElementById("statusFilter").value;
    const sortBy = document.getElementById("sortProducts").value;
    const tableSettings = {
        inventory: { showReceived: true, aging: false, expiring: false },
        aging: { showReceived: true, aging: true, expiring: false },
        expiring: { showReceived: false, aging: false, expiring: true },
    }[page];

    const filteredProducts = [...getProductsForPage(page)]
        .filter(product => {
            const matchesSearch = [
                product.name,
                product.batch,
                product.status,
                getAgeLabel(getInventoryAge(product)),
            ].join(" ").toLocaleLowerCase().includes(searchTerm);
            const matchesStatus = !selectedStatus || product.status === selectedStatus;

            return matchesSearch && matchesStatus;
        })
        .sort((first, second) => {
            if (sortBy === "age-desc") {
                return getInventoryAge(second) - getInventoryAge(first);
            }

            if (sortBy === "expiration") {
                return first.expiration.localeCompare(second.expiration);
            }

            if (sortBy === "received-desc") {
                return second.received.localeCompare(first.received);
            }

            return first.name.localeCompare(second.name);
        });

    rowsElement.innerHTML = filteredProducts.map(product => {
        const daysRemaining = getDaysRemaining(product);

        return `
            <tr>
                <td>${renderProductName(product)}</td>
                <td class="mono">${escapeHTML(product.batch)}</td>
                <td><strong class="quantity">${product.quantity}</strong><span class="unit-label"> units</span></td>
                ${tableSettings.showReceived ? `<td>${getDateLabel(product.received)}</td>` : ""}
                ${tableSettings.showReceived ? `<td>${renderAgeBadge(getInventoryAge(product))}</td>` : ""}
                <td>${getDateLabel(product.expiration)}</td>
                ${tableSettings.expiring ? `<td><span class="expiry-badge ${daysRemaining < 0 || product.status === "Expired" ? "expired" : daysRemaining <= 7 ? "urgent" : "soon"}">${escapeHTML(getExpiryLabel(daysRemaining))}</span></td>` : ""}
                <td>${renderStatusBadge(product.status)}</td>
                ${tableSettings.expiring ? `<td><span class="priority-tag ${getPriorityClass(getExpiryPriority(product))}">${getExpiryPriority(product)}</span></td>` : ""}
                ${tableSettings.aging ? `<td><span class="recommended-action">${escapeHTML(getRecommendedAction(product))}</span></td>` : ""}
                <td>
                    <div class="row-actions">
                        <button class="table-action" type="button" data-action="details" data-product-id="${escapeHTML(product.id)}">Details</button>
                        <button class="table-action primary-action" type="button" data-action="update-status" data-product-id="${escapeHTML(product.id)}">Update status</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    const emptyState = document.getElementById("tableEmptyState");
    const isEmpty = filteredProducts.length === 0;
    emptyState.hidden = !isEmpty;
    emptyState.textContent = searchTerm || selectedStatus
        ? "No inventory matches these search and filter options."
        : "No inventory items to display.";
    document.getElementById("tableResultCount").textContent = `Showing ${filteredProducts.length} of ${getProductsForPage(page).length} batches`;
}

function getProductsForPage(page) {
    if (page === "aging") {
        return inventory.filter(product => (
            getInventoryAge(product) > 30
            && getDaysRemaining(product) >= 0
            && product.status !== "Expired"
        ));
    }

    if (page === "expiring") {
        return inventory.filter(product => getDaysRemaining(product) <= 30 || product.status === "Expired");
    }

    return inventory;
}

function renderOperationsPage() {
    const queue = getRoleOperationalQueue(authState.role);
    const timeline = getRoleOperationsTimeline(authState.role);
    const insightItems = getRoleInsights(authState.role);
    const summaryCards = [
        { label: "Open tasks", value: String(queue.length), note: "live work items", color: "blue", icon: '<svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5h16M7 12h10M10 17.5h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>' },
        { label: "Urgent", value: String(queue.filter(task => task.status === "Priority").length), note: "priority actions", color: "orange", icon: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' },
        { label: "Ready", value: String(queue.filter(task => task.status === "Ready").length), note: "ready to close", color: "green", icon: '<svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4 10-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' },
    ];

    return `
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">LIVE FLOOR</p>
                    <h2>${authState.role} operations board</h2>
                    <p class="panel-description">Track the current warehouse flow, open actions, and next-release priorities.</p>
                </div>
            </div>
            <div class="stats-grid" aria-label="Operations summary">
                ${summaryCards.map(card => renderStatCard(card.label, card.value, card.note, card.color, card.icon)).join("")}
            </div>
        </section>

        <section class="panel operations-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">WORKFLOW</p>
                    <h2>Current task list</h2>
                </div>
            </div>
            <div class="task-list">
                ${queue.map(task => `
                    <div class="task-row">
                        <div>
                            <strong>${escapeHTML(task.title)}</strong>
                            <small>${escapeHTML(task.detail)}</small>
                        </div>
                        <span class="task-status ${task.status.toLowerCase().replace(/\s+/g, "-")}">${escapeHTML(task.status)}</span>
                    </div>
                `).join("")}
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">SCHEDULE</p>
                    <h2>Shift timeline</h2>
                </div>
            </div>
            <div class="timeline-list">
                ${timeline.map(item => `
                    <div class="timeline-item">
                        <span class="timeline-time">${escapeHTML(item.time)}</span>
                        <span class="timeline-dot"></span>
                        <div>
                            <strong>${escapeHTML(item.title)}</strong>
                            <small>${escapeHTML(item.note)}</small>
                        </div>
                    </div>
                `).join("")}
            </div>
        </section>

        <section class="panel insights-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">OPERATIONS INSIGHTS</p>
                    <h2>Current notes</h2>
                </div>
            </div>
            <ul class="insight-list">
                ${insightItems.map(item => `<li>${escapeHTML(item)}</li>`).join("")}
            </ul>
        </section>
    `;
}

function renderAlertsPage() {
    const alerts = getActiveAlerts();
    const categories = [
        { id: "aging", title: "Aging inventory alerts", description: "Stock held longer than 30 days." },
        { id: "expiration", title: "Expiration alerts", description: "Products approaching their expiration date." },
        { id: "expired", title: "Expired product alerts", description: "Expired stock requiring immediate action." },
    ];

    return `
        <section class="alerts-summary">
            <article><span class="summary-icon orange">${renderAlertIcon("aging")}</span><span><strong>${alerts.filter(alert => alert.category === "aging").length}</strong><small>Aging alerts</small></span></article>
            <article><span class="summary-icon amber">${renderAlertIcon("expiration")}</span><span><strong>${alerts.filter(alert => alert.category === "expiration").length}</strong><small>Expiration alerts</small></span></article>
            <article><span class="summary-icon red">${renderAlertIcon("expired")}</span><span><strong>${alerts.filter(alert => alert.category === "expired").length}</strong><small>Expired alerts</small></span></article>
            <button type="button" class="secondary-button mark-all-button" data-action="mark-all-read">Mark all as read</button>
        </section>
        ${categories.map(category => {
            const categoryAlerts = alerts.filter(alert => alert.category === category.id);

            return `
                <section class="panel alert-category-panel">
                    <div class="panel-heading">
                        <div><h2>${category.title}</h2><p class="panel-description">${category.description}</p></div>
                        <span class="category-count">${categoryAlerts.length}</span>
                    </div>
                    ${categoryAlerts.length > 0
                        ? `<div class="alert-list">${categoryAlerts.map(renderAlertCard).join("")}</div>`
                        : renderEmptyState("No alerts in this category", "New alerts will appear here automatically.")}
                </section>
            `;
        }).join("")}
    `;
}

function renderAlertCard(alert) {
    const isRead = readAlertIds.has(alert.id);

    return `
        <article class="alert-card ${isRead ? "read" : "unread"}">
            <span class="alert-indicator ${alert.category}">${renderAlertIcon(alert.category)}</span>
            <div class="alert-card-copy">
                <div class="alert-card-title">
                    <strong>${escapeHTML(alert.type)}</strong>
                    ${isRead ? '<span class="read-label">Read</span>' : '<span class="unread-label">Unread</span>'}
                </div>
                <p>${escapeHTML(alert.description)}</p>
                <small>${escapeHTML(alert.product.name)} · Batch ${escapeHTML(alert.product.batch)} · ${getDateLabel(alert.date)}</small>
            </div>
            <span class="priority-tag ${getPriorityClass(alert.priority)}">${escapeHTML(alert.priority)}</span>
            ${isRead ? "" : `<button class="mark-read-button" type="button" data-action="mark-read" data-alert-id="${escapeHTML(alert.id)}">Mark as read</button>`}
        </article>
    `;
}

function renderAlertIcon(category) {
    if (category === "expired") {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>';
    }

    if (category === "expiration") {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
}

function getPriorityClass(priority) {
    return priority.toLowerCase();
}

function renderEmptyState(title, description) {
    return `<div class="empty-state"><span class="empty-icon">✓</span><strong>${title}</strong><p>${description}</p></div>`;
}

function bindRoleSwitcher() {
    const roleSwitcher = document.getElementById("roleSwitcher");

    if (!roleSwitcher) {
        return;
    }

    roleSwitcher.onchange = event => {
        const selectedRole = String(event.target.value || "Warehouse Staff");
        authState.role = selectedRole;
        localStorage.setItem("warehouse-role", selectedRole);
        syncProfile();
        renderPage(getAllowedPages().includes(currentPage) ? currentPage : "dashboard");
    };
}

function renderSettings() {
    const roleAccess = rolePermissions[authState.role] || [];

    return `
        <section class="panel settings-panel">
            <p class="eyebrow">STAFF WORKSPACE</p>
            <h2>Workspace settings</h2>
            <p>You're signed in as ${escapeHTML(authState.role)}. This sample module stores inventory and alert changes in this browser session only.</p>

            <label class="settings-field" for="roleSwitcher">
                <span>Current role</span>
                <select id="roleSwitcher" class="role-switcher" aria-label="Select role">
                    ${Object.keys(rolePermissions).map(role => `<option value="${escapeHTML(role)}" ${role === authState.role ? "selected" : ""}>${escapeHTML(role)}</option>`).join("")}
                </select>
            </label>

            <div class="settings-row"><span>Inventory records</span><strong>${inventory.length} sample batches</strong></div>
            <div class="settings-row"><span>Alert window</span><strong>30 days before expiration</strong></div>
            <div class="settings-row"><span>Inventory aging threshold</span><strong>More than 30 days</strong></div>
            <div class="settings-row"><span>Access pages</span><strong>${roleAccess.join(", ")}</strong></div>
        </section>
    `;
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
    pageTitle.textContent = metadata.title;
    pageSubtitle.textContent = metadata.subtitle;
    document.title = `${metadata.title} | Warehouse Management`;

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
        bindRoleSwitcher();
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
            <button class="icon-button" type="button" data-action="close-modal" aria-label="Close dialog">×</button>
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
                <button class="icon-button" type="button" data-action="close-modal" aria-label="Close dialog">×</button>
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
        logoutUser();
        return;
    }

    renderPage(pageMetadata[page] ? page : "dashboard");
}

appView.addEventListener("submit", event => {
    if (event.target.id === "loginForm") {
        loginUser(event);
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
            logoutUser();
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

window.addEventListener("hashchange", handlePageChange);
applyAuthState();
if (authState.isAuthenticated) {
    handlePageChange();
}
