<?php

declare(strict_types=1);

return [
    'routes' => [
        // Dashboard + Settings.
        ['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#index', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#create', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'settings#load',  'url' => '/api/settings/load', 'verb' => 'POST'],

        // Project CRUD.
        ['name' => 'project#index',   'url' => '/api/projects',      'verb' => 'GET'],
        ['name' => 'project#show',    'url' => '/api/projects/{id}',  'verb' => 'GET'],
        ['name' => 'project#create',  'url' => '/api/projects',      'verb' => 'POST'],
        ['name' => 'project#update',  'url' => '/api/projects/{id}',  'verb' => 'PUT'],
        ['name' => 'project#destroy', 'url' => '/api/projects/{id}',  'verb' => 'DELETE'],

        // Prometheus metrics endpoint.
        ['name' => 'metrics#index', 'url' => '/api/metrics', 'verb' => 'GET'],
        // Health check endpoint.
        ['name' => 'health#index', 'url' => '/api/health', 'verb' => 'GET'],

        // SPA catch-all — same controller as the index route; must use a distinct route name
        // (duplicate names replace the earlier route in Symfony, which breaks GET /).
        ['name' => 'dashboard#catchAll', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'defaults' => ['path' => '']],
    ],
];
