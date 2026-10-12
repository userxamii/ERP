export function renderDashboardScreen() {
    if (typeof window.renderDashboard === 'function') {
        return window.renderDashboard();
    }

    return '';
}

export function renderProcurementDashboardScreen() {
    if (typeof window.renderProcurementDashboard === 'function') {
        return window.renderProcurementDashboard();
    }

    return '';
}
