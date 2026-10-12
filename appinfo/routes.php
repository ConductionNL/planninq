<?php

declare(strict_types=1);

// AppHost canonical route table (ADR-040): dashboard#page + #catchAll, the
// settings#index/create/load API, the per-user preferences#* endpoints, and the
// observability metrics#index / health#index routes are all provided by
// \OCA\OpenRegister\AppHost\Routes::standard(). Planninq's domain routes are
// appended as $extra (inserted before the SPA catch-all so they keep priority).
return \OCA\OpenRegister\AppHost\Routes::standard([
    // Per-user notification settings — planninq-specific, NOT in the canonical set.
    // First-time setup wizard (ADR-042) - the standard CnSetupWizard contract.
    ['name' => 'setup#status',    'url' => '/api/setup/status',            'verb' => 'GET'],
    ['name' => 'setup#runAction', 'url' => '/api/setup/action/{actionId}', 'verb' => 'POST', 'requirements' => ['actionId' => '[a-z0-9\\-]+']],
    ['name' => 'setup#saveConfig', 'url' => '/api/setup/config',           'verb' => 'POST'],
    ['name' => 'settings#updateUser', 'url' => '/api/settings/user', 'verb' => 'POST'],

    // Project creation policy check — enforces allow_project_creation server-side.
    ['name' => 'project#checkCreatePolicy', 'url' => '/api/projects/check-create-policy', 'verb' => 'GET'],
    // Project create proxy — C1: enforces policy then calls OR ObjectService server-side.
    ['name' => 'project#keyAvailable', 'url' => '/api/projects/key-available', 'verb' => 'GET'],
    ['name' => 'project#create', 'url' => '/api/projects', 'verb' => 'POST'],
    // Leave-project proxy — C3: allows non-owner members to remove themselves (_rbac: false).
    ['name' => 'project#leaveProject', 'url' => '/api/projects/{projectId}/leave', 'verb' => 'POST', 'requirements' => ['projectId' => '[^/]+']],

    // Label management (admin-only) — usage listing + cascade delete.
    // Admin posture enforced by NC SecurityMiddleware (no #[NoAdminRequired]
    // on the controller methods) plus an explicit isCurrentUserAdmin() check.
    ['name' => 'label#index', 'url' => '/api/labels', 'verb' => 'GET'],
    ['name' => 'label#destroy', 'url' => '/api/labels/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '[^/]+']],

    // Admin: log in to the task intake mailbox and report its message count (tasks-create-by-email).
    ['name' => 'mailIntake#test', 'url' => '/api/settings/mail-test', 'verb' => 'POST'],

    // Admin: rename a work type and optionally the time entries that carry it (time-timer-and-work-type).
    ['name' => 'workType#rename', 'url' => '/api/settings/work-types/rename', 'verb' => 'POST'],

    // Dependency edge create — server-side cycle/self/duplicate/cross-project validation.
    ['name' => 'dependency#create', 'url' => '/api/dependencies', 'verb' => 'POST'],
    // Dependency edge delete — project-member guarded.
    ['name' => 'dependency#destroy', 'url' => '/api/dependencies/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '[^/]+']],

    // Copy a project, or start one from a template (owner, manager, admin; any creator for a template).
    ['name' => 'projectCopy#create', 'url' => '/api/projects/{id}/copy', 'verb' => 'POST', 'requirements' => ['id' => '[^/]+']],

    // Microsoft Project plan import: preview (writes nothing), then import. Owner or admin, checked per project.
    ['name' => 'projectImport#preview', 'url' => '/api/projects/{projectId}/import/msproject/preview', 'verb' => 'POST', 'requirements' => ['projectId' => '[^/]+']],
    ['name' => 'projectImport#import', 'url' => '/api/projects/{projectId}/import/msproject', 'verb' => 'POST', 'requirements' => ['projectId' => '[^/]+']],

    // Case handover: copy a project's files and metadata to its linked case. Owner or admin, checked per project.
    ['name' => 'caseHandover#status', 'url' => '/api/projects/{projectId}/case-handover', 'verb' => 'GET', 'requirements' => ['projectId' => '[^/]+']],
    ['name' => 'caseHandover#handOver', 'url' => '/api/projects/{projectId}/case-handover', 'verb' => 'POST', 'requirements' => ['projectId' => '[^/]+']],

    // Read-only per-project timeline (Gantt) — RBAC-scoped through OR ObjectService.
    ['name' => 'timeline#forProject', 'url' => '/api/projects/{projectId}/timeline', 'verb' => 'GET', 'requirements' => ['projectId' => '[^/]+']],
    // Several projects on one axis (portfolio timeline), same RBAC-scoped read per project.
    ['name' => 'timeline#forProjects', 'url' => '/api/timeline', 'verb' => 'GET'],
    // Cumulative flow and lead/cycle time, replayed from the audit trail (portfolio-flow-reports).
    ['name' => 'flow#forProject', 'url' => '/api/projects/{projectId}/flow', 'verb' => 'GET', 'requirements' => ['projectId' => '[^/]+']],
    ['name' => 'flow#forPortfolio', 'url' => '/api/portfolios/{portfolioId}/flow', 'verb' => 'GET', 'requirements' => ['portfolioId' => '[^/]+']],

    // School timetable (school-timetable-target, decision D10): signed-in users
    // read a cohort's, group's or teacher's sessions; admins upsert a batch by hand.
    ['name' => 'timetable#sessions', 'url' => '/api/timetable/sessions', 'verb' => 'GET'],
    ['name' => 'timetable#upsert', 'url' => '/api/timetable/sessions/upsert', 'verb' => 'POST'],
    // Admins publish a source's draft lessons in a window (timetable-draft-review).
    ['name' => 'timetable#publish', 'url' => '/api/timetable/sessions/publish', 'verb' => 'POST'],
    // Admins upload the activities and rooms sheets for the timetable generator (timetabling-generator 2.2).
    ['name' => 'timetableInput#upload', 'url' => '/api/timetable/input/upload', 'verb' => 'POST'],
    // Admins start a generator run for a timetable scenario (timetabling-generator 5.2).
    ['name' => 'timetableScenario#generate', 'url' => '/api/timetable/scenarios/{id}/generate', 'verb' => 'POST'],
    // Admins take the current timetable into an imported scenario (timetabling-generator 6.1).
    ['name' => 'timetableScenario#importCurrent', 'url' => '/api/timetable/scenarios/{id}/import', 'verb' => 'POST'],
    // Admins publish a scenario as draft lessons for its window (timetabling-generator 8.1).
    ['name' => 'timetableScenario#publishDrafts', 'url' => '/api/timetable/scenarios/{id}/publish-drafts', 'verb' => 'POST'],
]);
