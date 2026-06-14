<?php

declare(strict_types=1);

return [
    'routes' => [
        // Dashboard + Settings.
        ['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#index', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#create', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'settings#updateUser', 'url' => '/api/settings/user', 'verb' => 'POST'],
        ['name' => 'settings#load',  'url' => '/api/settings/load', 'verb' => 'POST'],

        // Project creation policy check — enforces allow_project_creation server-side.
        ['name' => 'project#checkCreatePolicy', 'url' => '/api/projects/check-create-policy', 'verb' => 'GET'],
        // Project create proxy — C1: enforces policy then calls OR ObjectService server-side.
        ['name' => 'project#create', 'url' => '/api/projects', 'verb' => 'POST'],
        // Leave-project proxy — C3: allows non-owner members to remove themselves (_rbac: false).
        ['name' => 'project#leaveProject', 'url' => '/api/projects/{projectId}/leave', 'verb' => 'POST', 'requirements' => ['projectId' => '[^/]+']],

        // Label management (admin-only) — usage listing + cascade delete.
        // Admin posture enforced by NC SecurityMiddleware (no #[NoAdminRequired]
        // on the controller methods) plus an explicit isCurrentUserAdmin() check.
        ['name' => 'label#index', 'url' => '/api/labels', 'verb' => 'GET'],
        ['name' => 'label#destroy', 'url' => '/api/labels/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '[^/]+']],

        // Dependency edge create — server-side cycle/self/duplicate/cross-project validation.
        ['name' => 'dependency#create', 'url' => '/api/dependencies', 'verb' => 'POST'],
        // Dependency edge delete — project-member guarded.
        ['name' => 'dependency#destroy', 'url' => '/api/dependencies/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '[^/]+']],

        // Prometheus metrics endpoint.
        ['name' => 'metrics#index', 'url' => '/api/metrics', 'verb' => 'GET'],
        // Health check endpoint.
        ['name' => 'health#index', 'url' => '/api/health', 'verb' => 'GET'],

        // SPA catch-all — same controller as the index route; must use a distinct route name
        // (duplicate names replace the earlier route in Symfony, which breaks GET /).
        ['name' => 'dashboard#catchAll', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'defaults' => ['path' => '']],
    ],
];
