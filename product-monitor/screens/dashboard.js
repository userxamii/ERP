function renderDashboard() {
    if (authState.role === "Sales & Distribution Staff") {
        return salesDistributionStaff.renderDashboard();
    }

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
    const zoneOverview = getWarehouseZoneOverview(authState.role);
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

        <section class="panel zone-overview-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">ZONE OVERVIEW</p>
                    <h2>Warehouse capacity</h2>
                </div>
            </div>
            <div class="zone-list">
                ${zoneOverview.map(zone => `
                    <div class="zone-item">
                        <div>
                            <strong>${escapeHTML(zone.zone)}</strong>
                            <small>${escapeHTML(zone.detail)}</small>
                        </div>
                        <span class="zone-usage">${escapeHTML(zone.usage)}</span>
                        <span class="zone-status ${zone.status.toLowerCase().replace(/\s+/g, "-")}">${escapeHTML(zone.status)}</span>
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
