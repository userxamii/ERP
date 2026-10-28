import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

const productMonitorGlobals = [
    'authState',
    'escapeHTML',
    'getActiveAlerts',
    'getAgeLabel',
    'getDateLabel',
    'getDaysRemaining',
    'getExpiryLabel',
    'getInventoryAge',
    'getPriorityClass',
    'getRecentActivity',
    'getRoleDashboardCards',
    'getRoleInsights',
    'getRoleOperationalQueue',
    'getRoleOperationsTimeline',
    'getRoleQuickActions',
    'getStatCardIcon',
    'getStatusClass',
    'getWarehouseDockSchedule',
    'getWarehouseZoneOverview',
    'inventory',
    'readAlertIds',
    'renderAgeBadge',
    'renderAlertIcon',
    'renderChangePasswordPage',
    'renderDashboardAlert',
    'renderEmptyState',
    'renderLoginPage',
    'renderProductName',
    'renderSignupPage',
    'renderStatCard',
    'renderStatusBadge',
    'roleHighlights',
    'rolePermissions',
    'salesDistributionStaff',
    'statusOptions',
    'todayString',
].reduce((globals, name) => {
    globals[name] = 'readonly';

    return globals;
}, {});

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'], // Required for React 17+
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    {
        files: ['product-monitor/**/*.js'],
        languageOptions: {
            globals: productMonitorGlobals,
        },
    },
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js'],
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
