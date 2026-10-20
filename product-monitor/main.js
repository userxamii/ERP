import { renderDashboardScreen, renderProcurementDashboardScreen } from './screens/dashboard.js';
import { renderInventoryScreen } from './screens/inventory.js';
import { renderOperationsScreen } from './screens/operations.js';
import { renderAlertsScreen } from './screens/alerts.js';
import { renderSettingsScreen } from './screens/settings.js';
import { renderLoginScreen, renderSignupScreen } from './screens/auth.js';
import { salesDistributionStaff } from './screens/sales-and-distribution-staff.js';
import { systemAdministrator } from './screens/system-administrator.js?v=admin-functions-3';
import { warehouseStaff } from './screens/warehouse-staff.js';
import { procurementStaff } from './screens/procurement-staff.js?v=procurement-functions-3';

window.salesDistributionStaff = salesDistributionStaff;
window.renderLoginPage = renderLoginScreen;
window.renderSignupPage = renderSignupScreen;

const pageHandlers = {
    dashboard: () => {
        if (window.authState && window.authState.role === 'Procurement Staff') {
            return renderProcurementDashboardScreen();
        }

        if (window.authState && window.authState.role === 'Sales & Distribution Staff') {
            return salesDistributionStaff.renderDashboard();
        }

        if (window.authState && window.authState.role === 'Warehouse Staff') {
            return warehouseStaff.renderDashboard();
        }

        if (window.authState && window.authState.role === 'System Administrator') {
            return systemAdministrator.renderDashboard();
        }

        return renderDashboardScreen();
    },
    'admin-users': () => systemAdministrator.renderScreen('admin-users'),
    'procurement-materials': () => procurementStaff.renderScreen('procurement-materials'),
    'procurement-requests': () => procurementStaff.renderScreen('procurement-requests'),
    'procurement-suppliers': () => procurementStaff.renderScreen('procurement-suppliers'),
    'procurement-deliveries': () => procurementStaff.renderScreen('procurement-deliveries'),
    'procurement-reports': () => procurementStaff.renderScreen('procurement-reports'),
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
        const safePage = Object.hasOwn(pageHandlers, page) && window.isPageAllowed(page)
            ? page
            : 'dashboard';

        if (safePage !== page) {
            window.location.hash = safePage;
        }

        window.setCurrentPage(safePage);
        const handler = pageHandlers[safePage];
        const content = handler();
        const target = window.appView || document.getElementById('appView');

        if (target && typeof content === 'string') {
            target.innerHTML = content;

            if (['inventory', 'aging', 'expiring'].includes(safePage)) {
                window.renderInventoryRows(safePage);
            }

            if (safePage === 'admin-users') {
                systemAdministrator.loadUsers(target);
            }

            if (safePage === 'dashboard' && window.authState?.role === 'System Administrator') {
                systemAdministrator.loadDashboard(target);
            }

            if (safePage === 'dashboard' && window.authState?.role === 'Procurement Staff') {
                procurementStaff.loadScreen('dashboard', target);
            } else if (safePage.startsWith('procurement-')) {
                procurementStaff.loadScreen(safePage, target);
            }

            return;
        }

        return window.__legacyRenderPage(safePage);
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
