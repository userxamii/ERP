export function renderAlertsScreen() {
    if (typeof window.renderAlertsPage === 'function') {
        return window.renderAlertsPage();
    }

    return '';
}
