export function renderSettingsScreen() {
    if (typeof window.renderSettings === 'function') {
        return window.renderSettings();
    }

    return '';
}
