import { renderDashboardScreen, renderProcurementDashboardScreen } from './screens/dashboard.js';
import { renderInventoryScreen } from './screens/inventory.js';
import { renderOperationsScreen } from './screens/operations.js';
import { renderAlertsScreen } from './screens/alerts.js';
import { renderSettingsScreen } from './screens/settings.js';
import { renderLoginScreen, renderSignupScreen } from './screens/auth.js';
import { salesDistributionStaff } from './screens/sales-and-distribution-staff.js';

window.salesDistributionStaff = salesDistributionStaff;
window.renderLoginPage = renderLoginScreen;
window.renderSignupPage = renderSignupScreen;

const pageHandlers = {
    dashboard: () => {
        if (window.authState && window.authState.role === 'Procurement Staff') {
            return renderProcurementDashboardScreen();
        }

        return renderDashboardScreen();
    },
    inventory: () => renderInventoryScreen('inventory'),
    aging: () => renderInventoryScreen('aging'),
    expiring: () => renderInventoryScreen('expiring'),
    operations: () => renderOperationsScreen(),
    alerts: () => renderAlertsScreen(),
    settings: () => renderSettingsScreen(),
};

function applyScreenRouting() {
    if (typeof window.renderPage !== 'function') {
        return;
    }

    window.renderPage = function renderPage(page) {
        window.setCurrentPage(page);
        const handler = pageHandlers[page] || pageHandlers.dashboard;
        const content = handler();
        const target = window.appView || document.getElementById('appView');

        if (target && typeof content === 'string') {
            target.innerHTML = content;

            if (['inventory', 'aging', 'expiring'].includes(page)) {
                window.renderInventoryRows(page);
            }

            return;
        }

        return window.__legacyRenderPage(page);
    };

    if (typeof window.showAuthMode === 'function') {
        const originalAuthMode = window.showAuthMode;
        window.showAuthMode = function showAuthMode(mode, message = '', email = '') {
            if (mode === 'signup') {
                const content = renderSignupScreen(message);
                const target = window.appView || document.getElementById('appView');

                if (target && typeof content === 'string') {
                    target.innerHTML = content;
                    return;
                }
            }

            const content = renderLoginScreen(message, email);
            const target = window.appView || document.getElementById('appView');

            if (target && typeof content === 'string') {
                target.innerHTML = content;
                return;
            }

            return originalAuthMode(mode, message, email);
        };
    }
}

window.addEventListener('DOMContentLoaded', () => {
    if (typeof window.renderPage === 'function' && !window.__legacyRenderPage) {
        window.__legacyRenderPage = window.renderPage.bind(window);
    }

    applyScreenRouting();
    window.applyAuthState();
    window.initializeAuthentication();
});
