<?php

declare(strict_types=1);

return [
    'routes' => [
        // Dashboard + Settings.
        ['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#index', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#create', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'settings#load',  'url' => '/api/settings/load', 'verb' => 'POST'],

        // Time entry CRUD endpoints.
        ['name' => 'time_entry#create',  'url' => '/api/time-entries',      'verb' => 'POST'],
        ['name' => 'time_entry#index',   'url' => '/api/time-entries',      'verb' => 'GET'],
        ['name' => 'time_entry#update',  'url' => '/api/time-entries/{id}', 'verb' => 'PUT'],
        ['name' => 'time_entry#destroy', 'url' => '/api/time-entries/{id}', 'verb' => 'DELETE'],

        // Prometheus metrics endpoint.
        ['name' => 'metrics#index', 'url' => '/api/metrics', 'verb' => 'GET'],
        // Health check endpoint.
        ['name' => 'health#index', 'url' => '/api/health', 'verb' => 'GET'],

        // SPA catch-all — same controller as the index route; must use a distinct route name
        // (duplicate names replace the earlier route in Symfony, which breaks GET /).
        ['name' => 'dashboard#catchAll', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'defaults' => ['path' => '']],
    ],
];
