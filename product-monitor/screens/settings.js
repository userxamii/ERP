function renderSettings() {
    const roleAccess = rolePermissions[authState.role] || [];

    return `
        <section class="panel settings-panel">
            <p class="eyebrow">STAFF WORKSPACE</p>
            <h2>Workspace settings</h2>
            <p>You're signed in as ${escapeHTML(authState.role)}. Accounts are saved in the warehouse database; the prototype inventory dataset is held in this browser session.</p>

            <div class="settings-row"><span class="settings-label"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Assigned role</span><strong>${escapeHTML(authState.role)}</strong></div>
            <div class="settings-row"><span class="settings-label"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg> Inventory records</span><strong>${inventory.length} sample batches</strong></div>
            <div class="settings-row"><span class="settings-label"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Alert window</span><strong>30 days before expiration</strong></div>
            <div class="settings-row"><span class="settings-label"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Inventory aging threshold</span><strong>More than 30 days</strong></div>
            <div class="settings-row"><span class="settings-label"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect width="7" height="9" x="3" y="3" rx="1.5"/><rect width="7" height="5" x="14" y="3" rx="1.5"/><rect width="7" height="9" x="14" y="12" rx="1.5"/><rect width="7" height="5" x="3" y="16" rx="1.5"/></svg> Access pages</span><strong>${roleAccess.join(", ")}</strong></div>
        </section>
    `;
}

export function renderSettingsScreen() {
    return renderSettings();
}
