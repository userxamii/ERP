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
});

test('includes the operations page in the warehouse workspace navigation', function () {
    expect(file_get_contents(base_path('product-monitor/index.html')))
        ->toContain('data-page="operations"')
        ->toContain('>Operations<');
});

test('serves separated warehouse screen modules', function () {
    $indexHtml = file_get_contents(base_path('product-monitor/index.html'));
    $administratorScreen = file_get_contents(base_path('product-monitor/screens/system-administrator.js'));

    expect($indexHtml)
        ->toContain('/product-monitor/screens/auth.js')
        ->toContain('/product-monitor/screens/sales-and-distribution-staff.js')
        ->toContain('/product-monitor/screens/system-administrator.js')
        ->toContain('/product-monitor/screens/warehouse-staff.js');

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
        ->toContain('data-page="settings"');

    expect(file_get_contents(base_path('product-monitor/screens/role-dashboard.js')))
        ->toContain('getStatCardIcon');

    expect(file_get_contents(base_path('product-monitor/screens/settings.js')))
        ->toContain('<svg');

    expect(file_get_contents(base_path('product-monitor/screens/alerts.js')))
        ->toContain('renderAlertIcon');
});
