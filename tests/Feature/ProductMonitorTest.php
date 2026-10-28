<?php

test('renders the product monitor at the homepage', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'text/html; charset=utf-8');
});

test('serves the product monitor styles and scripts', function () {
    $this->get('/product-monitor/style.css')
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

    $this->get('/product-monitor/script.js')
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    $this->get(route('product-monitor.brand-logo'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('includes the operations page in the warehouse workspace navigation', function () {
    expect(file_get_contents(base_path('product-monitor/index.html')))
        ->toContain('data-page="operations"')
        ->toContain('>Operations<');
});

test('puts account details and account actions in the profile menu', function () {
    $indexHtml = file_get_contents(base_path('product-monitor/index.html'));
    $applicationScript = file_get_contents(base_path('product-monitor/script.js'));
    $stylesheet = file_get_contents(base_path('product-monitor/style.css'));

    expect($indexHtml)
        ->toContain('id="profileMenuButton"')
        ->toContain('aria-controls="profileMenu"')
        ->toContain('id="profileMenuEmail"')
        ->toContain('href="#settings" data-page="settings"')
        ->toContain('href="#logout" data-page="logout"')
        ->not->toContain('<div class="sidebar-footer">');

    expect($stylesheet)->not->toContain(".profile {\n        display: none;\n    }");

    expect($applicationScript)
        ->toContain('profileMenuButton.setAttribute("aria-expanded", String(!isExpanded));')
        ->toContain('profileMenuEmail.textContent = authState.email;')
        ->toContain('function closeProfileMenu()');
});

test('public login does not offer account creation and directs users to administrators', function () {
    $authScreen = file_get_contents(base_path('product-monitor/screens/auth.js'));
    $applicationScript = file_get_contents(base_path('product-monitor/script.js'));
    $screenRouter = file_get_contents(base_path('product-monitor/main.js'));
    $administratorScreen = file_get_contents(base_path('product-monitor/screens/system-administrator.js'));

    expect($authScreen)
        ->toContain('Accounts are issued by your system administrator.')
        ->not->toContain('signupForm')
        ->not->toContain('Create an account');

    expect($applicationScript)
        ->not->toContain('/warehouse/auth/register')
        ->not->toContain('signupForm')
        ->not->toContain('hasAccounts');

    expect($screenRouter)
        ->toContain('system-administrator.js')
        ->not->toContain('renderSignupScreen');

    expect($administratorScreen)
        ->toContain('data-admin-user-form="create"')
        ->toContain('Temporary password')
        ->toContain('They must choose a new password at first sign-in.')
        ->not->toContain('data-admin-action="reset-password"')
        ->not->toContain('Reset password')
        ->toContain('"/warehouse/admin/users"');
});

test('administrator dashboard omits its standalone heading and updated note', function () {
    $administratorScreen = file_get_contents(base_path('product-monitor/screens/system-administrator.js'));

    expect($administratorScreen)
        ->toContain('{ page: "dashboard", label: "Overview" }')
        ->toContain('${section.page === "dashboard" ? "" : renderAdminPageHeader(section)}')
        ->not->toContain('Admin Dashboard')
        ->not->toContain('System administration overview.')
        ->not->toContain('id="adminDashboardStatus"')
        ->not->toContain('Updated just now. Login counts cover the last 24 hours.');
});

test('management report tabs scroll to their sections without changing application routes', function () {
    $managementScreen = file_get_contents(base_path('product-monitor/screens/management.js'));

    expect($managementScreen)
        ->toContain('href="#management-analytics" data-management-section-link')
        ->toContain('href="#management-departments" data-management-section-link')
        ->toContain('href="#management-decisions" data-management-section-link')
        ->not->toContain('Report center')
        ->not->toContain('REPORT CENTER')
        ->not->toContain('data-management-download')
        ->toContain('event.preventDefault();')
        ->toContain('section.scrollIntoView(')
        ->toContain('sectionLink.setAttribute("aria-current", "location");')
        ->toContain('id="management-analytics"')
        ->toContain('id="management-departments"')
        ->toContain('id="management-decisions"')
        ->toContain('function renderMovementChart(rows, unit)')
        ->toContain('renderMovementAnalytics(data.movementTrendsByUnit)')
        ->toContain('currency: "PHP"')
        ->not->toContain('management-report-center');
});

test('new users are required to change their temporary password before using the workspace', function () {
    $authScreen = file_get_contents(base_path('product-monitor/screens/auth.js'));
    $applicationScript = file_get_contents(base_path('product-monitor/script.js'));
    $passwordMiddleware = file_get_contents(base_path('app/Http/Middleware/EnsurePasswordChanged.php'));

    expect($authScreen)
        ->toContain('id="changePasswordForm"')
        ->toContain('name="current_password"')
        ->toContain('Your administrator cannot see the new password.');

    expect($applicationScript)
        ->toContain('authState.mustChangePassword')
        ->toContain('/warehouse/auth/change-password');

    expect($passwordMiddleware)->toContain('Change your temporary password before continuing.');
});

test('animates the newly created user row in the administrator account list', function () {
    $administratorScreen = file_get_contents(base_path('product-monitor/screens/system-administrator.js'));
    $stylesheet = file_get_contents(base_path('product-monitor/style.css'));

    expect($administratorScreen)
        ->toContain('data-admin-user-id="${escapeHTML(user.id)}"')
        ->toContain('loadSystemAdministratorUsers(target, isEdit ? null : payload.user?.id ?? null)')
        ->toContain('createdUserRow?.classList.add("admin-user-added")')
        ->toContain('form.closest("details")?.removeAttribute("open")')
        ->toContain('successToast.textContent = "User added!"')
        ->toContain('successToast.hidden = true;')
        ->toContain('successToast.classList.add("visible")')
        ->toContain('class="admin-user-success-toast"');

    expect($stylesheet)
        ->toContain('.admin-user-added > td')
        ->toContain('@keyframes admin-user-added-highlight')
        ->toContain('@keyframes admin-user-success-float')
        ->toContain('@media (prefers-reduced-motion: reduce)');
});

test('keeps mark-as-read controls visible for every alert', function () {
    $alertsScreen = file_get_contents(base_path('product-monitor/screens/alerts.js'));
    $legacyAlertsScreen = file_get_contents(base_path('product-monitor/script.js'));
    $screenRouter = file_get_contents(base_path('product-monitor/main.js'));

    expect($alertsScreen)
        ->toContain('data-action="${isRead ? "mark-unread" : "mark-read"}"')
        ->toContain('data-action="mark-all-read"')
        ->toContain('${isRead ? "Undo read" : "Mark as read"}')
        ->toContain('data-alert-count-category="aging"')
        ->toContain('Unread aging alerts')
        ->not->toContain('${isRead ? "" :');

    expect($legacyAlertsScreen)
        ->toContain('data-action="${isRead ? "mark-unread" : "mark-read"}"')
        ->toContain('data-action="mark-all-read"')
        ->toContain('${isRead ? "Undo read" : "Mark as read"}')
        ->toContain('const unreadAlerts = activeAlerts.filter(alert => !readAlertIds.has(alert.id));')
        ->toContain('document.querySelectorAll("[data-alert-count-category]")')
        ->not->toContain('${isRead ? "" :');

    expect($legacyAlertsScreen)
        ->toContain('alertCard.classList.add("read")')
        ->toContain('unreadLabel.textContent = "Read"')
        ->toContain('actionButton.textContent = "Undo read"')
        ->toContain('readAlertIds.delete(alertId)')
        ->toContain('actionButton.dataset.action = "mark-unread";');

    expect($legacyAlertsScreen)
        ->toContain('window.updateAlertCounts = updateAlertCounts;');

    expect($screenRouter)
        ->toContain('target.innerHTML = content;')
        ->toContain('window.updateAlertCounts();');
});

test('serves separated warehouse screen modules', function () {
    $indexHtml = file_get_contents(base_path('product-monitor/index.html'));
    $administratorScreen = file_get_contents(base_path('product-monitor/screens/system-administrator.js'));

    expect($indexHtml)
        ->toContain('/product-monitor/screens/auth.js')
        ->toContain('/product-monitor/screens/sales-and-distribution-staff.js')
        ->toContain('/product-monitor/screens/system-administrator.js')
        ->toContain('/product-monitor/screens/warehouse-staff.js')
        ->toContain('/product-monitor/screens/procurement-staff.js');

    expect($administratorScreen)
        ->toContain('ACCOUNT DIRECTORY')
        ->toContain('Email / Username')
        ->not->toContain('admin-master-data')
        ->not->toContain('admin-warehouse-settings')
        ->not->toContain('admin-alert-settings')
        ->not->toContain('admin-audit-log')
        ->not->toContain('admin-system-settings');

    $this->get('/product-monitor/screens/sales-and-distribution-staff.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    $this->get('/product-monitor/screens/warehouse-staff.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    $this->get('/product-monitor/screens/role-dashboard.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    $this->get('/product-monitor/screens/system-administrator.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    $this->get('/product-monitor/screens/procurement-staff.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
});

test('includes screen icons across warehouse pages and navigation', function () {
    $indexHtml = file_get_contents(base_path('product-monitor/index.html'));

    expect($indexHtml)
        ->toContain('data-page="dashboard"')
        ->toContain('data-page="inventory"')
        ->toContain('data-page="aging"')
        ->toContain('data-page="expiring"')
        ->toContain('data-page="operations"')
        ->toContain('data-page="alerts"')
        ->toContain('data-page="settings"')
        ->toContain('data-page="procurement-materials"')
        ->toContain('data-page="procurement-requests"')
        ->toContain('data-page="procurement-suppliers"')
        ->toContain('data-page="procurement-deliveries"')
        ->toContain('data-page="procurement-reports"');

    expect(file_get_contents(base_path('product-monitor/screens/role-dashboard.js')))
        ->toContain('getStatCardIcon');

    expect(file_get_contents(base_path('product-monitor/screens/settings.js')))
        ->toContain('<svg');

    expect(file_get_contents(base_path('product-monitor/screens/alerts.js')))
        ->toContain('renderAlertIcon');
});
