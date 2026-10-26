const managementEndpoint = "/warehouse/management/dashboard";
let latestManagementData = null;

function escape(value) {
    return escapeHTML(value ?? "—");
}

function number(value) {
    return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 3 });
}

function renderLoading(message = "Loading management workspace…") {
    return `<section class="panel"><p class="admin-table-note" role="status">${escape(message)}</p></section>`;
}

function renderPanel(title, eyebrow, content, action = "") {
    return `
        <section class="panel management-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">${escape(eyebrow)}</p><h2>${escape(title)}</h2></div>
                ${action}
            </div>
            ${content}
        </section>
    `;
}

function renderTable(headers, rows, emptyMessage) {
    return `
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr>${headers.map(header => `<th>${escape(header)}</th>`).join("")}</tr></thead>
                <tbody>${rows || `<tr><td colspan="${headers.length}">${escape(emptyMessage)}</td></tr>`}</tbody>
            </table>
        </div>
    `;
}

function renderMetrics(summary) {
    const value = Object.entries(summary.inventoryValueByCurrency || {})
        .map(([currency, amount]) => `${currency} ${number(amount)}`)
        .join(" · ");
    const cards = [
        ["Materials tracked", number(summary.materialCount), "Active material catalog"],
        ["Physical stock", `${number(summary.onHandQuantity)} units`, "Across the selected filters"],
        ["Estimated inventory value", value || "Not tracked", `${number(summary.unvaluedBatchCount)} batches have no purchase cost`],
        ["Low stock", number(summary.lowStockCount), "At or below minimum stock"],
        ["Expired / expiring", `${number(summary.expiredBatchCount)} / ${number(summary.expiringBatchCount)}`, "Batches with stock on hand"],
        ["Quality held", number(summary.heldBatchCount), "Held, rejected, or damaged batches"],
    ];

    return `<div class="management-metric-grid">${cards.map(([label, amount, detail]) => `
        <article class="panel management-metric">
            <span>${escape(label)}</span>
            <strong>${escape(amount)}</strong>
            <small>${escape(detail)}</small>
        </article>
    `).join("")}</div>`;
}

function renderFilters(data) {
    const categories = data.categories.map(category => `
        <option value="${escape(category.id)}" ${Number(data.filters.categoryId) === Number(category.id) ? "selected" : ""}>${escape(category.name)}</option>
    `).join("");
    const locations = data.locations.map(location => `
        <option value="${escape(location)}" ${data.filters.location === location ? "selected" : ""}>${escape(location)}</option>
    `).join("");

    return `
        <form class="management-filters" data-management-filter>
            <label><span>From</span><input type="date" name="from" value="${escape(data.filters.from)}" required></label>
            <label><span>To</span><input type="date" name="to" value="${escape(data.filters.to)}" required></label>
            <label><span>Category</span><select name="category_id"><option value="">All categories</option>${categories}</select></label>
            <label><span>Warehouse / location</span><select name="location"><option value="">All locations</option>${locations}</select></label>
            <button class="primary-button" type="submit">Apply filters</button>
        </form>
    `;
}

function renderHorizontalBars(rows, labelKey, valueKey) {
    if (!rows.length) {
        return '<p class="admin-table-note">No report data for this period.</p>';
    }

    const maximum = Math.max(...rows.map(row => Number(row[valueKey]) || 0), 1);
    return `<div class="management-bars">${rows.map(row => `
        <div class="management-bar-row">
            <span>${escape(row[labelKey])}</span>
            <div class="management-bar-track"><i style="width:${Math.max(2, Number(row[valueKey]) / maximum * 100)}%"></i></div>
            <strong>${escape(number(row[valueKey]))}</strong>
        </div>
    `).join("")}</div>`;
}

function renderDashboard(data) {
    const movementRows = data.movementTrend.map(row => `
        <tr><td>${escape(row.month)}</td><td>${escape(number(row.received))}</td><td>${escape(number(row.issued))}</td><td>${escape(number(row.rejected))}</td></tr>
    `).join("");
    const agingRows = data.agingBuckets.map(row => `
        <tr><td>${escape(row.label)}</td><td>${escape(number(row.quantity))}</td></tr>
    `).join("");
    const topMaterialRows = data.topMaterials.map(row => `
        <tr><td>${escape(row.code)}</td><td>${escape(row.name)}</td><td>${escape(number(row.quantity))}</td></tr>
    `).join("");
    const topUserRows = data.topUsers.map(row => `
        <tr><td>${escape(row.name)}</td><td>${escape(number(row.movementCount))}</td></tr>
    `).join("");
    const topWorkCenterRows = data.topWorkCenters.map(row => `
        <tr><td>${escape(row.name)}</td><td>${escape(number(row.issuedQuantity))}</td></tr>
    `).join("");
    const categoryBars = renderHorizontalBars(data.inventoryByCategory, "name", "quantity");
    const alertRows = data.alerts.map(alert => `
        <article class="management-alert management-alert-${escape(alert.severity)}">
            <strong>${escape(alert.title)}</strong><span>${escape(alert.detail)}</span>
        </article>
    `).join("");
    const expiryCostRows = data.nearExpiryCostExposure.map(batch => `
        <tr><td>${escape(batch.materialCode)} · ${escape(batch.material)}</td><td>${escape(batch.lotCode)}</td><td>${escape(batch.expiresAt)}</td><td>${escape(number(batch.quantity))}</td><td>${escape(batch.currency)} ${escape(number(batch.estimatedValue))}</td></tr>
    `).join("");
    const currencyValues = Object.entries(data.summary.inventoryValueByCurrency || {});
    const financeNote = currencyValues.length
        ? currencyValues.map(([currency, amount]) => `${currency} ${number(amount)}`).join(" · ")
        : "No purchase-order unit costs are recorded for the selected stock.";

    return `
        <div class="management-workspace">
            ${renderFilters(data)}
            ${renderMetrics(data.summary)}
            <nav class="management-section-links" aria-label="Management report sections">
                <a href="#management-analytics">Analytics</a>
                <a href="#management-departments">Department reports</a>
                <a href="#management-decisions">Alerts &amp; decisions</a>
                <a href="#management-report-center">Report center</a>
            </nav>
            <section id="management-analytics" class="management-section">
                <div class="management-two-column">
                    ${renderPanel("Inventory by category", "CURRENT POSITION", categoryBars)}
                    ${renderPanel("Inventory aging", "BATCH AGE", renderTable(["Time in storage", "Quantity on hand"], agingRows, "No batch aging data is available."))}
                </div>
                <div class="management-two-column">
                    ${renderPanel("Monthly inventory movement", "SELECTED DATE RANGE", renderTable(["Month", "Received", "Issued", "Rejected at receipt"], movementRows, "No stock movements were recorded in this period."))}
                    ${renderPanel("Materials with highest use", "STOCK-OUT ACTIVITY", renderTable(["Code", "Material", "Quantity issued"], topMaterialRows, "No material issues were recorded in this period."))}
                </div>
                ${renderPanel("Expiry risk by month", "UPCOMING BATCH EXPIRY", `${renderHorizontalBars(data.expiryTrend, "month", "quantity")}<p class="management-source-note">The inventory ledger does not record expired-stock write-offs, so historical expiry loss trends are not available.</p>`)}
                <div class="management-two-column">
                    ${renderPanel("Most active stock handlers", "MOVEMENT ACTIVITY", renderTable(["User", "Recorded movements"], topUserRows, "No user-attributed movements were recorded in this period."))}
                    ${renderPanel("Top receiving work centers", "PRODUCTION ISSUE DESTINATIONS", renderTable(["Work center / destination", "Quantity issued"], topWorkCenterRows, "No destination-attributed issues were recorded in this period."))}
                </div>
            </section>
            <section id="management-departments" class="management-section">
                ${renderDepartmentReports(data.departments, financeNote)}
            </section>
            <section id="management-decisions" class="management-section">
                <div class="management-two-column">
                    ${renderPanel("Risks needing attention", "ALERTS", alertRows || '<p class="admin-table-note">No critical stock or expiry alerts found.</p>')}
                    ${renderPanel("Near-expiry cost exposure", "COST-LINKED BATCHES", `${renderTable(["Material", "Lot", "Expires", "Quantity", "Estimated value"], expiryCostRows, "No cost-linked eligible batches expire within 30 days.")}<p class="management-source-note">Values are estimates from purchase-order unit costs and remain separated by currency; no high-value threshold is configured.</p>`)}
                    ${renderPanel("Pending management approvals", "DECISIONS", `
                        <p>${escape(number(data.summary.pendingPurchaseApprovals))} purchase requests are waiting for review.</p>
                        <a class="secondary-button" href="#procurement-requests" data-page="procurement-requests">Review purchase requests</a>
                        <p>${escape(number(data.summary.pendingProductionApprovals))} production requests are waiting for review.</p>
                        <a class="secondary-button" href="#production-requests" data-page="production-requests">Review production requests</a>
                        <p class="management-source-note">Approval decisions are recorded through the existing purchase and production workflows.</p>
                    `)}
                </div>
            </section>
            <section id="management-report-center" class="management-section">
                ${renderPanel("Export filtered reports", "REPORT CENTER", `
                    <div class="management-export">
                        <label><span>Report</span><select name="export-report" data-management-export>
                            <option value="inventoryReport">Inventory position</option>
                            <option value="movementReport">Stock movement history</option>
                            <option value="inspectionReport">QA inspections</option>
                            <option value="productionReport">Production material requests</option>
                        </select></label>
                        <button class="secondary-button" type="button" data-management-download>Download CSV</button>
                    </div>
                    <p class="management-source-note">Inventory position is a current snapshot because historical stock snapshots are not tracked; date filters apply to event reports. Values are estimates from linked purchase-order costs, shown separately by currency. Stock without recorded cost is left unvalued, and inventory loss write-offs are not separately tracked.</p>
                    <p class="management-export-status" role="status" data-management-export-status></p>
                `)}
            </section>
        </div>
    `;
}

function renderDepartmentReports(departments, financeNote) {
    const departmentCards = [
        ["Warehouse", `${number(departments.warehouse.receivedQuantity)} units received`, `${number(departments.warehouse.movementCount)} movements in period`],
        ["Procurement", `${number(departments.procurement.requestCount)} requests`, `${number(departments.procurement.pendingApprovals)} waiting · ${number(departments.procurement.orderCount)} orders`],
        ["Quality assurance", `${number(departments.quality.inspectionCount)} inspections`, `${number(departments.quality.rejectedInspectionCount)} rejected inspections · ${number(departments.quality.rejectedReceiptQuantity)} rejected units`],
        ["Production", `${number(departments.production.fulfilledQuantity)} units issued`, `${number(departments.production.requestCount)} requests · ${number(departments.production.pendingApprovals)} pending approval`],
        ["Finance", financeNote, "Cost-linked, not currency-converted; loss write-offs are not tracked."],
    ];

    return `<div class="management-department-grid">${departmentCards.map(([title, metric, detail]) => `
        <article class="panel management-department-card">
            <p class="eyebrow">DEPARTMENT REPORT</p>
            <h2>${escape(title)}</h2>
            <strong>${escape(metric)}</strong>
            <small>${escape(detail)}</small>
        </article>
    `).join("")}</div>`;
}

function csvCell(value) {
    const text = String(value ?? "");
    const safeText = /^[=+\-@]/.test(text.trimStart()) ? `'${text}` : text;
    return `"${safeText.replaceAll('"', '""')}"`;
}

function downloadCsv(data, reportName, target) {
    const rows = data[reportName];
    const status = target.querySelector("[data-management-export-status]");

    if (!Array.isArray(rows) || rows.length === 0) {
        status.textContent = "No rows are available for this report and filter selection.";
        return;
    }

    const columns = Object.keys(rows[0]);
    const csv = [columns.map(csvCell).join(","), ...rows.map(row => columns.map(column => csvCell(row[column])).join(","))].join("\r\n");
    const link = document.createElement("a");
    const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    link.href = url;
    link.download = `${reportName.replace("Report", "").toLowerCase()}-${data.filters.from}-to-${data.filters.to}.csv`;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 0);
    status.textContent = `${number(rows.length)} rows exported.`;
}

const managementWorkspace = {
    renderDashboard() {
        return renderLoading();
    },
    async loadScreen(target = document.getElementById("appView"), query = "") {
        if (!target) {
            return;
        }

        latestManagementData = null;
        target.innerHTML = renderLoading();
        try {
            const response = await fetch(`${managementEndpoint}${query}`, {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || `The request failed (${response.status}).`);
            }

            target.innerHTML = renderDashboard(data);
            latestManagementData = data;
        } catch (error) {
            target.innerHTML = `<section class="panel"><p class="error-message" role="alert">${escape(error.message || "Could not load management reports.")}</p></section>`;
        }
    },
};

document.addEventListener("submit", event => {
    const form = event.target.closest("[data-management-filter]");
    if (!form) {
        return;
    }

    event.preventDefault();
    const query = new URLSearchParams(new FormData(form));
    managementWorkspace.loadScreen(document.getElementById("appView"), `?${query}`);
});

document.addEventListener("click", event => {
    const button = event.target.closest("[data-management-download]");
    if (!button) {
        return;
    }

    const target = document.getElementById("appView");
    const reportName = target?.querySelector("[data-management-export]")?.value;
    if (latestManagementData && reportName) {
        downloadCsv(latestManagementData, reportName, target);
    }
});

export { managementWorkspace };
