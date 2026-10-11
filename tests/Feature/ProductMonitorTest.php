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
