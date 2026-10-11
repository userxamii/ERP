const salesDistributionStaff = {
    theme: { accent: "#4b4f54", accentStrong: "#252a2e", accentSoft: "#f0f1f3" },
    operationalQueue: [
        { title: "Review products nearing expiration", status: "Priority", detail: "Check the expiration alerts and upcoming batches" },
        { title: "Prioritize products for distribution", status: "Pending", detail: "Move eligible near-expiry products into distribution" },
        { title: "Update distribution actions", status: "Ready", detail: "Confirm product status after prioritizing" },
    ],
    timeline: [
        { time: "08:50", title: "Dispatch window", note: "Outbound plan refreshed for the next customer cycle." },
        { time: "12:05", title: "Capacity check", note: "Transport slots remain stable for active orders." },
        { time: "17:15", title: "Risk update", note: "One route remains under supply watch due to aging stock." },
    ],
    dockSchedule: [
        { label: "Customer dispatch", zone: "Dock 1", eta: "10:40", status: "Queued" },
        { label: "Cold chain release", zone: "Zone K", eta: "14:50", status: "Ready" },
        { label: "Return transfer", zone: "Dock 2", eta: "18:10", status: "Review" },
    ],
    zoneOverview: [
        { zone: "Outbound", usage: "70%", status: "Stable", detail: "Dispatch window still open" },
        { zone: "Cold Chain", usage: "59%", status: "Healthy", detail: "Condition-controlled stock available" },
        { zone: "Dock 1", usage: "82%", status: "Busy", detail: "Pickup cycle in progress" },
    ],
    insights: [
        "Products expiring soon should be reviewed before dispatch.",
        "Distribution priority should follow the nearest expiration date.",
        "Expired products are excluded from distribution prioritization.",
    ],
    recentActivity: [
        "Dispatch plan refreshed for active orders",
        "Outbound stock reservation updated",
        "Risk flag shared with fulfillment team",
    ],
    renderDashboard() {
        const expiringProducts = inventory
            .filter(product => {
                const daysRemaining = getDaysRemaining(product);
                return daysRemaining >= 0 && daysRemaining <= 30 && product.status !== "Expired";
            })
            .sort((first, second) => first.expiration.localeCompare(second.expiration));
        const expirationAlerts = getActiveAlerts().filter(alert => alert.category === "expiration");
        const prioritizedCount = expiringProducts.filter(product => product.status === "For Distribution").length;
        const cards = [
            {
                label: "Finished products",
                value: String(inventory.length),
                note: "Tracked inventory records",
                color: "blue",
                icon: '<svg viewBox="0 0 24 24" fill="none"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4.3 7.2 7.7 4.3 7.7-4.3M12 11.5V21"/></svg>',
            },
            {
                label: "Nearing expiration",
                value: String(expiringProducts.length),
                note: "Within the next 30 days",
                color: "orange",
                icon: renderAlertIcon("expiration"),
            },
            {
                label: "Prioritized for distribution",
                value: String(prioritizedCount),
                note: "Marked for distribution",
                color: "green",
                icon: '<svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4 10-10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            },
        ];

        return `
            <section class="welcome-row">
                <div>
                    <h2>Finished product distribution</h2>
                    <p>Review products nearing expiration, act on alerts, and prioritize eligible stock.</p>
                </div>
                <span class="date-chip">${getDateLabel(todayString())}</span>
            </section>

            <section class="stats-grid" aria-label="Finished product distribution summary">
                ${cards.map(card => renderStatCard(card.label, card.value, card.note, card.color, card.icon)).join("")}
            </section>

            <section class="panel alert-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">EXPIRATION ALERTS</p>
                        <h2>Products needing attention</h2>
                        <p class="panel-description">Review these alerts and prioritize eligible products before they expire.</p>
                    </div>
                    <a class="text-link" href="#alerts">View all alerts <span aria-hidden="true">→</span></a>
                </div>
                ${expirationAlerts.length > 0
                    ? `<div class="alert-list">${expirationAlerts.map(renderDashboardAlert).join("")}</div>`
                    : renderEmptyState("No expiration alerts", "There are no finished products nearing expiration.")}
            </section>

            <section class="panel inventory-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">DISTRIBUTION PRIORITIES</p>
                        <h2>Finished products nearing expiration</h2>
                        <p class="panel-description">Products are listed by soonest expiration date.</p>
                    </div>
                    <a class="text-link" href="#expiring">Open expiration watch <span aria-hidden="true">→</span></a>
                </div>
                ${expiringProducts.length > 0
                    ? `<div class="table-wrap">
                        <table class="data-table inventory-table">
                            <thead><tr><th>Product</th><th>Batch number</th><th>Quantity</th><th>Expiration date</th><th>Time remaining</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>${expiringProducts.map(product => {
                                const prioritized = product.status === "For Distribution";
                                const daysRemaining = getDaysRemaining(product);

                                return `<tr>
                                    <td>${renderProductName(product)}</td>
                                    <td class="mono">${escapeHTML(product.batch)}</td>
                                    <td><strong class="quantity">${product.quantity}</strong><span class="unit-label"> units</span></td>
                                    <td>${getDateLabel(product.expiration)}</td>
                                    <td><span class="expiry-badge ${daysRemaining <= 7 ? "urgent" : "soon"}">${escapeHTML(getExpiryLabel(daysRemaining))}</span></td>
                                    <td>${renderStatusBadge(product.status)}</td>
                                    <td><button class="table-action ${prioritized ? "" : "primary-action"}" type="button" data-action="prioritize-distribution" data-product-id="${escapeHTML(product.id)}" ${prioritized ? "disabled" : ""}>${prioritized ? "Prioritized" : "Prioritize for distribution"}</button></td>
                                </tr>`;
                            }).join("")}</tbody>
                        </table>
                    </div>`
                    : renderEmptyState("No products nearing expiration", "Finished products within 30 days of expiration will appear here.")}
            </section>
        `;
    },
};
