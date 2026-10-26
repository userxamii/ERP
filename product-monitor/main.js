import { renderDashboardScreen, renderProcurementDashboardScreen } from './screens/dashboard.js';
import { renderInventoryScreen } from './screens/inventory.js';
import { renderOperationsScreen } from './screens/operations.js';
import { renderAlertsScreen } from './screens/alerts.js?v=alerts-unread-count-2';
import { renderSettingsScreen } from './screens/settings.js';
import { renderChangePasswordScreen, renderLoginScreen } from './screens/auth.js?v=temporary-password-required-1';
import { salesDistributionStaff } from './screens/sales-and-distribution-staff.js';
import { systemAdministrator } from './screens/system-administrator.js?v=temporary-password-required-1';
import { warehouseStaff } from './screens/warehouse-staff.js';
import { procurementStaff } from './screens/procurement-staff.js?v=procurement-functions-3';
import { productionStaff } from './screens/production-staff.js?v=production-functions-1';
import { managementWorkspace } from './screens/management.js?v=management-workspace-1';

window.salesDistributionStaff = salesDistributionStaff;
window.renderLoginPage = renderLoginScreen;
window.renderChangePasswordPage = renderChangePasswordScreen;

const pageHandlers = {
    dashboard: () => {
        if (window.authState && window.authState.role === 'Procurement Staff') {
            return renderProcurementDashboardScreen();
        }

        if (window.authState && window.authState.role === 'Production Staff') {
            return productionStaff.renderDashboard();
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

        if (window.authState && window.authState.role === 'Management') {
            return managementWorkspace.renderDashboard();
        }

        return renderDashboardScreen();
    },
    'management-workspace': () => managementWorkspace.renderDashboard(),
    'admin-users': () => systemAdministrator.renderScreen('admin-users'),
    'procurement-materials': () => procurementStaff.renderScreen('procurement-materials'),
    'procurement-requests': () => procurementStaff.renderScreen('procurement-requests'),
    'procurement-suppliers': () => procurementStaff.renderScreen('procurement-suppliers'),
    'procurement-deliveries': () => procurementStaff.renderScreen('procurement-deliveries'),
    'procurement-reports': () => procurementStaff.renderScreen('procurement-reports'),
    'production-materials': () => productionStaff.renderScreen('production-materials'),
    'production-requests': () => productionStaff.renderScreen('production-requests'),
    'production-issued': () => productionStaff.renderScreen('production-issued'),
    'production-batches': () => productionStaff.renderScreen('production-batches'),
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

            if (safePage === 'dashboard' && window.authState?.role === 'Procurement Staff') {
                procurementStaff.loadScreen('dashboard', target);
            } else if (safePage === 'dashboard' && window.authState?.role === 'Production Staff') {
                productionStaff.loadScreen('dashboard', target);
            } else if (safePage === 'dashboard' && window.authState?.role === 'Management') {
                managementWorkspace.loadScreen(target);
            } else if (safePage.startsWith('procurement-')) {
                procurementStaff.loadScreen(safePage, target);
            } else if (safePage.startsWith('production-')) {
                productionStaff.loadScreen(safePage, target);
            } else if (safePage === 'management-workspace') {
                managementWorkspace.loadScreen(target);
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
