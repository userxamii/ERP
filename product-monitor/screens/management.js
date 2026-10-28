const managementEndpoint = "/warehouse/management/dashboard";
const chartColors = ["#168a87", "#4279c5", "#8970bd", "#d89b32", "#c7677c", "#607d8b"];

function escape(value) {
    return escapeHTML(value ?? "—");
}

function number(value) {
    return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 3 });
}

function chartValue(value) {
    const amount = Number(value);

    return Number.isFinite(amount) ? Math.max(0, amount) : 0;
}

function axisNumber(value) {
    return new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 }).format(value);
}

function chartScale(maximum) {
    if (maximum <= 0) {
        return 4;
    }

    const magnitude = 10 ** Math.floor(Math.log10(maximum));

    return Math.ceil(maximum / magnitude) * magnitude;
}

function renderDonutChart(rows, label) {
    const slices = rows.map((row, index) => ({
        ...row,
        value: chartValue(row.value),
        color: row.color || chartColors[index % chartColors.length],
    }));
    const total = slices.reduce((sum, row) => sum + row.value, 0);
    let offset = 0;
    const segments = slices.filter(row => row.value > 0).map(row => {
        const share = row.value / total * 100;
        const segment = `<circle class="bi-donut-segment" cx="110" cy="110" r="78" pathLength="100" fill="none" stroke="${row.color}" stroke-width="30" stroke-dasharray="${share} ${100 - share}" stroke-dashoffset="${-offset}"><title>${escape(row.label)}: ${escape(number(row.value))} (${escape(number(share))}%)</title></circle>`;
        offset += share;

        return segment;
    }).join("");
    const legend = slices.map(row => {
        const share = total > 0 ? row.value / total * 100 : 0;
        const contents = `<span class="bi-legend-dot" style="--chart-color:${row.color}"></span><span class="bi-legend-name">${escape(row.label)}</span><span class="bi-legend-value">${escape(number(row.value))}<small>${escape(number(share))}%</small></span>`;

        return row.categoryId !== undefined
            ? `<button class="bi-legend-row bi-legend-action" type="button" data-management-category="${escape(row.categoryId)}" aria-label="Filter report by ${escape(row.label)}: ${escape(number(row.value))} materials">${contents}<span class="bi-legend-arrow" aria-hidden="true">↗</span></button>`
            : `<div class="bi-legend-row">${contents}</div>`;
    }).join("");

    return `
        <div class="bi-donut-layout">
            <svg class="bi-donut" viewBox="0 0 220 220" role="img" aria-label="${escape(label)}: ${escape(number(total))} materials. The legend lists exact counts and percentages.">
                <circle cx="110" cy="110" r="78" fill="none" class="bi-donut-track" stroke-width="30"></circle>
                <g transform="rotate(-90 110 110)">${segments}</g>
                <text class="bi-donut-total" x="110" y="108" text-anchor="middle">${escape(number(total))}</text>
                <text class="bi-donut-caption" x="110" y="131" text-anchor="middle">materials</text>
            </svg>
            <div class="bi-donut-legend">${total > 0 ? legend : '<p class="bi-empty-message">No materials match the selected filters.</p>'}</div>
        </div>
    `;
}

function renderColumnChart(rows, labelKey, valueKey, description) {
    if (!rows.some(row => chartValue(row[valueKey]) > 0)) {
        return '<p class="bi-empty-message">No stocked batches to display for this view.</p>';
    }

    const width = 460;
    const left = 46;
    const top = 24;
    const bottom = 202;
    const plotWidth = width - left - 16;
    const maximum = Math.max(4, Math.ceil(Math.max(...rows.map(row => chartValue(row[valueKey]))) / 4) * 4);
    const step = plotWidth / rows.length;
    const barWidth = Math.min(48, step * 0.48);
    const grid = [0, 0.25, 0.5, 0.75, 1].map(ratio => {
        const y = bottom - ratio * (bottom - top);

        return `<line class="bi-chart-grid" x1="${left}" y1="${y}" x2="${width - 16}" y2="${y}"></line><text class="bi-axis-label" x="${left - 10}" y="${y + 4}" text-anchor="end">${escape(axisNumber(maximum * ratio))}</text>`;
    }).join("");
    const bars = rows.map((row, index) => {
        const value = chartValue(row[valueKey]);
        const height = value / maximum * (bottom - top);
        const x = left + step * (index + 0.5);
        const label = labelKey === "month" ? monthLabel(row[labelKey]) : row[labelKey];

        return `<g class="bi-chart-point" tabindex="0" role="group" aria-label="${escape(label)}: ${escape(number(value))} batches">
            <title>${escape(label)}: ${escape(number(value))} batches</title>
            <rect class="bi-column" x="${x - barWidth / 2}" y="${bottom - height}" width="${barWidth}" height="${height}" rx="4" fill="${chartColors[index % chartColors.length]}"></rect>
            <text class="bi-column-value" x="${x}" y="${bottom - height - 8}" text-anchor="middle">${escape(number(value))}</text>
            <text class="bi-axis-label" x="${x}" y="${bottom + 23}" text-anchor="middle">${escape(label)}</text>
        </g>`;
    }).join("");

    return `<svg class="bi-column-chart" viewBox="0 0 ${width} 240" role="group" aria-label="${escape(description)}. Counts are shown above each column.">${grid}${bars}</svg>`;
}

function currency(value, code) {
    const amount = Number(value || 0);

    if (code === "PHP") {
        return new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    }

    return `${code} ${number(amount)}`;
}

function renderLoading(message = "Loading management workspace…") {
    return `<section class="panel"><p class="admin-table-note" role="status">${escape(message)}</p></section>`;
}

function renderPanel(title, eyebrow, content, action = "") {
    return `
        <section class="panel management-panel bi-report-panel">
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
        .map(([currencyCode, amount]) => currency(amount, currencyCode))
        .join(" · ");
    const cards = [
        ["Materials tracked", number(summary.materialCount), "Active material catalog"],
        ["Physical stock", Object.entries(summary.onHandByUnit || {}).map(([unit, quantity]) => `${number(quantity)} ${unit}`).join(" · ") || "0", "Shown separately by unit of measure"],
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
            <div class="management-bar-track"><i style="width:${Number(row[valueKey]) === 0 ? 0 : Math.min(100, Math.max(2, Number(row[valueKey]) / maximum * 100))}%"></i></div>
            <strong>${escape(number(row[valueKey]))}</strong>
        </div>
    `).join("")}</div>`;
}

function monthLabel(value) {
    const date = new Date(`${value}-01T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat(undefined, { month: "short", year: "2-digit" }).format(date);
}

function renderMovementChart(rows, unit) {
    const hasActivity = rows.some(row => [row.received, row.issued, row.rejected].some(value => Number(value) > 0));

    if (!hasActivity) {
        return '<p class="admin-table-note">No stock movements were recorded in this period.</p>';
    }

    const width = 760;
    const left = 58;
    const top = 22;
    const bottom = 216;
    const plotWidth = width - left - 22;
    const maximum = chartScale(Math.max(...rows.flatMap(row => [row.received, row.issued, row.rejected].map(chartValue))));
    const step = plotWidth / rows.length;
    const barWidth = Math.min(32, step * 0.3);
    const yPosition = value => bottom - chartValue(value) / maximum * (bottom - top);
    const grid = [0, 0.25, 0.5, 0.75, 1].map(ratio => {
        const y = bottom - ratio * (bottom - top);

        return `<line class="bi-chart-grid" x1="${left}" y1="${y}" x2="${width - 22}" y2="${y}"></line><text class="bi-axis-label" x="${left - 12}" y="${y + 4}" text-anchor="end">${escape(axisNumber(maximum * ratio))}</text>`;
    }).join("");
    const points = rows.map((row, index) => `${left + step * (index + 0.5)},${yPosition(row.issued)}`).join(" ");
    const bars = rows.map((row, index) => {
        const x = left + step * (index + 0.5);

        return `<rect class="bi-trend-received" x="${x - barWidth}" y="${yPosition(row.received)}" width="${barWidth}" height="${bottom - yPosition(row.received)}" rx="3"></rect><rect class="bi-trend-rejected" x="${x + 3}" y="${yPosition(row.rejected)}" width="${barWidth * 0.5}" height="${bottom - yPosition(row.rejected)}" rx="2"></rect><text class="bi-axis-label" x="${x}" y="${bottom + 24}" text-anchor="middle">${escape(monthLabel(row.month))}</text>`;
    }).join("");
    const markers = rows.map((row, index) => {
        const x = left + step * (index + 0.5);
        const net = chartValue(row.received) - chartValue(row.issued);
        const detail = `${monthLabel(row.month)} · Received ${number(row.received)} · Issued ${number(row.issued)} · Rejected ${number(row.rejected)} · Net ${number(net)} ${unit}`;

        return `<g class="bi-chart-point" tabindex="0" role="group" aria-label="${escape(detail)}" data-management-chart-detail="${escape(detail)}"><title>${escape(detail)}</title><rect class="bi-chart-hit-area" x="${left + step * index}" y="${top}" width="${step}" height="${bottom - top}"></rect><circle class="bi-trend-marker" cx="${x}" cy="${yPosition(row.issued)}" r="4.5"></circle></g>`;
    }).join("");

    return `<div class="bi-trend-chart"><div class="bi-chart-legend"><span style="--chart-color:#4279c5">Received</span><span class="bi-line-legend" style="--chart-color:#8970bd">Issued</span><span style="--chart-color:#d89b32">Rejected at receipt</span><small>${escape(unit)}</small></div>
        <svg class="bi-combo-chart" viewBox="0 0 ${width} 260" role="group" aria-label="Monthly accepted receipts and rejections as columns, with issues as a line, in ${escape(unit)}. Focus on a month for exact quantities.">${grid}${bars}<polyline class="bi-trend-line" points="${points}"></polyline>${markers}</svg>
        <output class="bi-chart-detail" data-management-chart-tooltip>Hover over or focus on a month to see its quantities.</output>
        <p class="management-source-note">Net stock change = accepted receipts − issues. Rejected deliveries do not enter stock.</p>
    </div>`;
}

function renderMovementUnit(trend) {
    const rows = trend.months.map(row => `
        <tr><td>${escape(row.month)}</td><td>${escape(number(row.received))}</td><td>${escape(number(row.issued))}</td><td>${escape(number(row.rejected))}</td></tr>
    `).join("");
    const received = trend.months.reduce((sum, row) => sum + chartValue(row.received), 0);
    const issued = trend.months.reduce((sum, row) => sum + chartValue(row.issued), 0);
    const net = received - issued;

    return `<div class="bi-trend-summary"><div><span>Total received</span><strong>${escape(number(received))}<small>${escape(trend.unit)}</small></strong></div><div><span>Total issued</span><strong>${escape(number(issued))}<small>${escape(trend.unit)}</small></strong></div><div><span>Net stock change</span><strong class="${net < 0 ? "is-negative" : ""}">${net > 0 ? "+" : ""}${escape(number(net))}<small>${escape(trend.unit)}</small></strong></div></div>
        ${renderMovementChart(trend.months, trend.unit)}
        <details class="management-chart-data"><summary>View monthly figures (${escape(trend.unit)})</summary>${renderTable(["Month", "Received", "Issued", "Rejected at receipt"], rows, "No recorded movements.")}</details>`;
}

function renderMovementAnalytics(trends) {
    if (!trends.length) {
        return '<p class="admin-table-note">No stock movements were recorded in this period.</p>';
    }

    const selectedIndex = Math.max(0, trends.findIndex(trend => trend.unit === "kg"));
    const units = trends.map((trend, index) => `<option value="${index}" ${index === selectedIndex ? "selected" : ""}>${escape(trend.unit)}</option>`).join("");

    return `<div class="bi-unit-toolbar"><p>Compare inbound supply with material consumption.</p><label>Unit of measure <select data-management-movement-unit>${units}</select></label></div><div data-management-movement-content>${renderMovementUnit(trends[selectedIndex])}</div>`;
}

function renderRecommendations(summary) {
    const recommendations = [];

    if (Number(summary.expiredBatchCount) > 0) {
        recommendations.push([
            "critical",
            "Quarantine expired stock",
            `${number(summary.expiredBatchCount)} batch${Number(summary.expiredBatchCount) === 1 ? " is" : "es are"} expired. Prevent further use and record the disposition after review.`,
            "Review expiry risks",
            "#management-decisions",
        ]);
    }

    if (Number(summary.lowStockCount) > 0) {
        recommendations.push([
            "high",
            "Start replenishment for low-stock materials",
            `${number(summary.lowStockCount)} material${Number(summary.lowStockCount) === 1 ? " is" : "s are"} at or below the minimum level. Confirm demand and prepare the highest-priority purchase requests.`,
            "Review purchase requests",
            "#procurement-requests",
        ]);
    }

    if (Number(summary.expiringBatchCount) > 0) {
        recommendations.push([
            "medium",
            "Prioritize expiring lots for issue",
            `${number(summary.expiringBatchCount)} batch${Number(summary.expiringBatchCount) === 1 ? " expires" : "es expire"} within 30 days. Apply FEFO allocation where quality status permits.`,
            "Plan FEFO issue",
            "#management-decisions",
        ]);
    }

    if (Number(summary.heldBatchCount) > 0) {
        recommendations.push([
            "medium",
            "Resolve held inventory with QA",
            `${number(summary.heldBatchCount)} batch${Number(summary.heldBatchCount) === 1 ? " is" : "es are"} unavailable because of its quality status. Assign disposition owners and due dates.`,
            "Review held batches",
            "#management-decisions",
        ]);
    }

    const pendingApprovals = Number(summary.pendingPurchaseApprovals) + Number(summary.pendingProductionApprovals);
    if (pendingApprovals > 0) {
        recommendations.push([
            "standard",
            "Clear approval backlog",
            `${number(pendingApprovals)} request${pendingApprovals === 1 ? " is" : "s are"} awaiting management review across procurement and production.`,
            "Review requests",
            Number(summary.pendingPurchaseApprovals) > 0 ? "#procurement-requests" : "#production-requests",
        ]);
    }

    if (!recommendations.length) {
        recommendations.push([
            "standard",
            "Maintain the current control plan",
            "No stock, expiry, quality, or approval thresholds are currently breached in the selected view.",
            "Review analytics",
            "#management-analytics",
        ]);
    }

    return `<div class="management-recommendations">${recommendations.map(([priority, title, detail, action, href]) => {
        const isWorkspaceSection = href.startsWith("#management-");
        const attributes = isWorkspaceSection
            ? "data-management-section-link"
            : `data-page="${escape(href.slice(1))}"`;

        return `
            <article class="management-recommendation management-recommendation-${priority}">
                <span class="management-recommendation-priority">${escape(priority === "standard" ? "Monitor" : priority)}</span>
                <div><h3>${escape(title)}</h3><p>${escape(detail)}</p><a href="${escape(href)}" ${attributes}>${escape(action)}</a></div>
            </article>
        `;
    }).join("")}</div>`;
}

function renderDashboard(data) {
    const topMaterialRows = data.topMaterials.map(row => `
        <tr><td>${escape(row.code)}</td><td>${escape(row.name)}</td><td>${escape(number(row.quantity))} ${escape(row.unit)}</td></tr>
    `).join("");
    const topUserRows = data.topUsers.map(row => `
        <tr><td>${escape(row.name)}</td><td>${escape(number(row.movementCount))}</td></tr>
    `).join("");
    const topWorkCenterRows = data.topWorkCenters.map(row => `
        <tr><td>${escape(row.name)}</td><td>${escape(number(row.issuedQuantity))}</td></tr>
    `).join("");
    const categories = data.inventoryByCategory.map(row => {
        const category = data.categories.find(category => category.name === row.name);

        return { label: row.name, value: row.materialCount, categoryId: category?.id };
    });
    const stockHealth = [
        { label: "Sufficient stock", value: 0, color: "#168a87" },
        { label: "Below minimum", value: 0, color: "#d89b32" },
        { label: "Out of stock", value: 0, color: "#b94b66" },
    ];
    data.inventoryReport.forEach(row => {
        const index = chartValue(row.availableQuantity) <= 0 ? 2 : (row.lowStock ? 1 : 0);
        stockHealth[index].value++;
    });
    const activeCategory = data.categories.find(category => Number(category.id) === Number(data.filters.categoryId));
    const filterLabel = [activeCategory?.name || "All categories", data.filters.location || "All locations"].join(" · ");
    const alertRows = data.alerts.map(alert => `
        <article class="management-alert management-alert-${escape(alert.severity)}">
            <strong>${escape(alert.title)}</strong><span>${escape(alert.detail)}</span>
        </article>
    `).join("");
    const expiryCostRows = data.nearExpiryCostExposure.map(batch => `
        <tr><td>${escape(batch.materialCode)} · ${escape(batch.material)}</td><td>${escape(batch.lotCode)}</td><td>${escape(batch.expiresAt)}</td><td>${escape(number(batch.quantity))}</td><td>${escape(currency(batch.estimatedValue, batch.currency))}</td></tr>
    `).join("");
    const currencyValues = Object.entries(data.summary.inventoryValueByCurrency || {});
    const financeNote = currencyValues.length
        ? currencyValues.map(([currencyCode, amount]) => currency(amount, currencyCode)).join(" · ")
        : "No purchase-order unit costs are recorded for the selected stock.";

    return `
        <div class="management-workspace bi-workspace">
            <header class="bi-overview-heading"><div><p class="eyebrow">MANAGEMENT ANALYTICS</p><h2>Inventory intelligence</h2><p>Understand your stock. Spot risk. Make the next move.</p></div><span class="bi-report-badge"><span aria-hidden="true"></span>Inventory ledger</span></header>
            ${renderFilters(data)}
            <div class="bi-filter-context"><span>${escape(filterLabel)}</span><span>Activity: ${escape(data.filters.from)} → ${escape(data.filters.to)}</span>${data.filters.categoryId !== null || data.filters.location ? '<button type="button" data-management-reset-filters>Clear category &amp; location</button>' : ""}</div>
            ${renderMetrics(data.summary)}
            <nav class="management-section-links" aria-label="Management report sections">
                <a href="#management-analytics" data-management-section-link aria-current="location">Analytics</a>
                <a href="#management-departments" data-management-section-link>Department reports</a>
                <a href="#management-decisions" data-management-section-link>Alerts &amp; decisions</a>
            </nav>
            <section id="management-analytics" class="management-section bi-analytics">
                <div class="bi-section-heading"><h2>Inventory overview</h2><p>Current stock position · select a category in the legend to filter the report</p></div>
                <div class="management-two-column">
                    ${renderPanel("Materials by category", "CATEGORY MIX · CURRENT SNAPSHOT", renderDonutChart(categories, "Materials by category"))}
                    ${renderPanel("Stock availability", "MATERIAL HEALTH · CURRENT SNAPSHOT", `${renderDonutChart(stockHealth, "Material stock health")}<p class="management-source-note">Based on usable stock after reservations. Each material belongs to one group.</p>`)}
                </div>
                ${renderPanel("Monthly inventory movement", "SELECTED DATE RANGE · BY UNIT", renderMovementAnalytics(data.movementTrendsByUnit))}
                <div class="management-two-column">
                    ${renderPanel("Inventory aging", "STOCKED BATCHES · CURRENT SNAPSHOT", renderColumnChart(data.agingBuckets, "label", "batchCount", "Stocked batches by days in storage"))}
                    ${renderPanel("Batch expiry calendar", "CURRENT MONTH + NEXT FIVE", `${renderColumnChart(data.expiryTrend, "month", "batchCount", "Stocked batches by expiry month")}<p class="management-source-note">Current-month counts include earlier expiries still on hand. This stock snapshot is independent of the activity date range.</p>`)}
                </div>
                <div class="management-two-column">
                    ${renderPanel("Materials with highest use", "STOCK-OUT ACTIVITY", renderTable(["Code", "Material", "Quantity issued"], topMaterialRows, "No material issues were recorded in this period."))}
                    ${renderPanel("Stock-handling activity", "RECORDED MOVEMENT COUNT", `${renderHorizontalBars(data.topUsers, "name", "movementCount")}<details class="management-chart-data"><summary>View handler figures</summary>${renderTable(["User", "Recorded movements"], topUserRows, "No user-attributed movements were recorded in this period.")}</details>`)}
                </div>
                <div>
                    ${renderPanel("Top receiving work centers", "PRODUCTION ISSUE DESTINATIONS", renderTable(["Work center / destination", "Quantity issued"], topWorkCenterRows, "No destination-attributed issues were recorded in this period."))}
                </div>
            </section>
            <section id="management-departments" class="management-section" aria-labelledby="management-departments-title">
                <div class="bi-section-heading"><h2 id="management-departments-title">Department reports</h2></div>
                ${renderDepartmentReports(data.departments, financeNote)}
            </section>
            <section id="management-decisions" class="management-section">
                ${renderPanel("Recommended next actions", "PRIORITIZED FROM THIS VIEW", renderRecommendations(data.summary))}
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
            <h3>${escape(title)}</h3>
            <strong>${escape(metric)}</strong>
            <small>${escape(detail)}</small>
        </article>
    `).join("")}</div>`;
}

const managementWorkspace = {
    reportData: null,
    renderDashboard() {
        return renderLoading();
    },
    async loadScreen(target = document.getElementById("appView"), query = "") {
        if (!target) {
            return;
        }

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
            managementWorkspace.reportData = data;
            target.innerHTML = renderDashboard(data);
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
    const categoryButton = event.target.closest("[data-management-category]");
    const resetButton = event.target.closest("[data-management-reset-filters]");

    if (categoryButton || resetButton) {
        const workspace = (categoryButton || resetButton).closest(".management-workspace");
        const form = workspace?.querySelector("[data-management-filter]");

        if (form) {
            form.elements.category_id.value = categoryButton?.dataset.managementCategory || "";

            if (resetButton) {
                form.elements.location.value = "";
            }

            form.requestSubmit();
        }

        return;
    }

    const sectionLink = event.target.closest("[data-management-section-link]");

    if (sectionLink) {
        const workspace = sectionLink.closest(".management-workspace");
        const targetId = sectionLink.getAttribute("href")?.slice(1);
        const section = targetId ? document.getElementById(targetId) : null;

        if (workspace && section && workspace.contains(section)) {
            event.preventDefault();
            workspace.querySelectorAll("[data-management-section-link]").forEach(link => {
                link.removeAttribute("aria-current");
            });
            sectionLink.setAttribute("aria-current", "location");
            section.scrollIntoView({
                behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
                block: "start",
            });
        }

        return;
    }
});

document.addEventListener("change", event => {
    const select = event.target.closest("[data-management-movement-unit]");

    if (!select) {
        return;
    }

    const trend = managementWorkspace.reportData?.movementTrendsByUnit[Number(select.value)];
    const content = select.closest(".management-panel")?.querySelector("[data-management-movement-content]");

    if (trend && content) {
        content.innerHTML = renderMovementUnit(trend);
    }
});

function showChartDetail(event) {
    const point = event.target.closest("[data-management-chart-detail]");
    const output = point?.closest(".bi-trend-chart")?.querySelector("[data-management-chart-tooltip]");

    if (output) {
        output.textContent = point.dataset.managementChartDetail;
    }
}

document.addEventListener("pointerover", showChartDetail);
document.addEventListener("focusin", showChartDetail);

export { managementWorkspace };
