export function renderOperationsScreen() {
    if (typeof window.renderOperationsPage === 'function') {
        return window.renderOperationsPage();
    }

    return '';
}
