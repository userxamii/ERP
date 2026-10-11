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
