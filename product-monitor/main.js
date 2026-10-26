import { renderDashboardScreen, renderProcurementDashboardScreen } from './screens/dashboard.js';
import { renderInventoryScreen } from './screens/inventory.js';
import { renderOperationsScreen } from './screens/operations.js';
import { renderAlertsScreen } from './screens/alerts.js?v=alerts-unread-count-2';
import { renderSettingsScreen } from './screens/settings.js';
import { renderChangePasswordScreen, renderLoginScreen } from './screens/auth.js?v=temporary-password-required-1';
import { salesDistributionStaff } from './screens/sales-and-distribution-staff.js';
import { systemAdministrator } from './screens/system-administrator.js?v=temporary-password-required-1';
import { warehouseStaff } from './screens/warehouse-staff.js';

window.salesDistributionStaff = salesDistributionStaff;
window.renderLoginPage = renderLoginScreen;
window.renderChangePasswordPage = renderChangePasswordScreen;

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
            window.updateAlertCounts();

            if (window.authState && window.authState.role === 'System Administrator') {
                if (safePage === 'dashboard') {
                    systemAdministrator.loadDashboard(target);
                } else if (safePage === 'admin-users') {
                    systemAdministrator.loadUsers(target);
                }
            }

            if (['inventory', 'aging', 'expiring'].includes(safePage)) {
                window.renderInventoryRows(safePage);
            }

            if (safePage === 'admin-users') {
                systemAdministrator.loadUsers(target);
            }

            if (safePage === 'dashboard' && window.authState?.role === 'System Administrator') {
                systemAdministrator.loadDashboard(target);
            }

            return;
        }

        return window.__legacyRenderPage(safePage);
    };

    if (typeof window.showAuthMode === 'function') {
        const originalAuthMode = window.showAuthMode;
        window.showAuthMode = function showAuthMode(message = '', email = '') {
            const content = renderLoginScreen(message, email);
            const target = window.appView || document.getElementById('appView');

            if (target && typeof content === 'string') {
                target.innerHTML = content;
                return;
            }

            return originalAuthMode(message, email);
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
