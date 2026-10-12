function renderSettings() {
    const roleAccess = rolePermissions[authState.role] || [];

    return `
        <section class="panel settings-panel">
            <p class="eyebrow">STAFF WORKSPACE</p>
            <h2>Workspace settings</h2>
            <p>You're signed in as ${escapeHTML(authState.role)}. Accounts are saved in the warehouse database; the prototype inventory dataset is held in this browser session.</p>

            <div class="settings-row"><span>Assigned role</span><strong>${escapeHTML(authState.role)}</strong></div>
            <div class="settings-row"><span>Inventory records</span><strong>${inventory.length} sample batches</strong></div>
            <div class="settings-row"><span>Alert window</span><strong>30 days before expiration</strong></div>
            <div class="settings-row"><span>Inventory aging threshold</span><strong>More than 30 days</strong></div>
            <div class="settings-row"><span>Access pages</span><strong>${roleAccess.join(", ")}</strong></div>
        </section>
    `;
}

export function renderSettingsScreen() {
    return renderSettings();
}
