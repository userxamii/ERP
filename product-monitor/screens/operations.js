function renderOperationsPage() {
    const queue = getRoleOperationalQueue(authState.role);
    const timeline = getRoleOperationsTimeline(authState.role);
    const insightItems = getRoleInsights(authState.role);
    const summaryCards = [
        { label: "Open tasks", value: String(queue.length), note: "live work items", color: "blue", icon: (typeof getStatCardIcon === "function" ? getStatCardIcon("Open tasks") : '<svg viewBox="0 0 24 24" fill="none"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/></svg>') },
        { label: "Urgent", value: String(queue.filter(task => task.status === "Priority").length), note: "priority actions", color: "orange", icon: (typeof getStatCardIcon === "function" ? getStatCardIcon("Urgent") : '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>') },
        { label: "Ready", value: String(queue.filter(task => task.status === "Ready").length), note: "ready to close", color: "green", icon: (typeof getStatCardIcon === "function" ? getStatCardIcon("Ready") : '<svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>') },
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

export function renderOperationsScreen() {
    return renderOperationsPage();
}
