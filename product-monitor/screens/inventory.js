export function renderInventoryScreen(page = 'inventory') {
    if (typeof window.renderTablePage === 'function') {
        return window.renderTablePage(page);
    }

    return '';
}
