function renderAlertsPage() {
    const alerts = getActiveAlerts();
    const categories = [
        { id: "aging", title: "Aging inventory alerts", description: "Stock held longer than 30 days." },
        { id: "expiration", title: "Expiration alerts", description: "Products approaching their expiration date." },
        { id: "expired", title: "Expired product alerts", description: "Expired stock requiring immediate action." },
    ];

    return `
        <section class="alerts-summary">
            <article><span class="summary-icon orange">${renderAlertIcon("aging")}</span><span><strong data-alert-count-category="aging">${alerts.filter(alert => alert.category === "aging" && !readAlertIds.has(alert.id)).length}</strong><small>Unread aging alerts</small></span></article>
            <article><span class="summary-icon amber">${renderAlertIcon("expiration")}</span><span><strong data-alert-count-category="expiration">${alerts.filter(alert => alert.category === "expiration" && !readAlertIds.has(alert.id)).length}</strong><small>Unread expiration alerts</small></span></article>
            <article><span class="summary-icon red">${renderAlertIcon("expired")}</span><span><strong data-alert-count-category="expired">${alerts.filter(alert => alert.category === "expired" && !readAlertIds.has(alert.id)).length}</strong><small>Unread expired alerts</small></span></article>
            <button type="button" class="secondary-button mark-all-button" data-action="mark-all-read">Mark all as read</button>
        </section>
        ${categories.map(category => {
            const categoryAlerts = alerts.filter(alert => alert.category === category.id);

            return `
                <section class="panel alert-category-panel">
                    <div class="panel-heading">
                        <div><h2>${category.title}</h2><p class="panel-description">${category.description}</p></div>
                        <span class="category-count" data-alert-count-category="${category.id}" aria-label="Unread alerts">${categoryAlerts.filter(alert => !readAlertIds.has(alert.id)).length}</span>
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
            <button class="mark-read-button${isRead ? " is-read" : ""}" type="button" data-action="${isRead ? "mark-unread" : "mark-read"}" data-alert-id="${escapeHTML(alert.id)}">${isRead ? "Undo read" : "Mark as read"}</button>
        </article>
    `;
}

function renderAlertIcon(category) {
    if (category === "expired") {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    }

    if (category === "expiration") {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 2v4M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M12 14v4M12 18h3"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
}

export function renderAlertsScreen() {
    return renderAlertsPage();
}
