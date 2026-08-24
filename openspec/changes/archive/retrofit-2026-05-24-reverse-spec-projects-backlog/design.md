# Design — Retrofit Project Backlog Route

**Retrofit change.** Tasks describe retroactive annotation, not new implementation work.

## Context

Phase 0/1/2 of the planix retrofit pipeline produced:
- `feature/i18n-complete-translations` baseline (closed prior PRs)
- planix#237 — direct-SQL violation fix in `SettingsService::ensureRegisterPublicAccess`
- planix#238 — Bucket 1 annotations across 48 methods / 13 REQs / 4 capabilities

The coverage scan flagged 2 Bucket 2a clusters (existing code, no REQ):

1. `register-schemas` → `lib/Settings/planix_register.json` — **skipped**; the
   scanner enumerates `.php`/`.vue`/`.js`/`.ts` files only, JSON manifests are
   informational. The coverage report's own note states this entry is
   "informational only" and the schema declaration is already covered by the
   Bucket 1 annotation on `lib/Listener/DeepLinkRegistrationListener.php::handle`
   (REQ-All-5-schemas-defined).

2. `projects` → `src/views/ProjectBacklog.vue` — addressed by this change.
   The Vue view, its router entry, and its mounted-hook side effect ship on
   `development` but no REQ in `openspec/specs/projects.md` mentions a backlog
   route.

## Why --extend, not --cluster

`projects` is an existing capability with 12 REQs. The Backlog route is part
of the project-detail surface (sibling to ProjectBoard) — it belongs in the
same spec. Minting a new `project-backlog` capability would imply a
larger surface that does not exist yet.

## Why one REQ, not more

The view does exactly three observable things:
1. Renders a breadcrumb chain back to the project board
2. Hydrates the projects store on direct deep-link
3. Shows a placeholder NcEmptyContent

These all express a single user-visible behavior — "the route exists as a
navigable shell" — and naturally compose into one REQ with three scenarios.
Splitting them into 2-3 REQs would inflate the spec without adding review
surface; collapsing them into one REQ with one scenario would lose the
direct-deep-link guarantee (which is the most likely thing to silently
regress).

## REQ ID convention

Slug-style (`REQ-Project-Backlog-Route`) to match planix's existing flat-file
spec convention. The SKILL.md prefers numbered IDs, but Phase 2 (#238) used
slug IDs across all 13 annotations to stay consistent with the existing
`projects.md` style. Mixing numbered and slug IDs inside one capability would
break the convention without buying anything; fleet consistency wins here.

## Frontmatter

Uses block YAML `retrofit_extensions:` to flag the cohort for Specter sync.
Single entry, but block-form scales as future retrofit deltas accumulate.

## Annotation scope

Two `@spec` tags on `ProjectBacklog.vue`:
- One in the file-level docblock (top of `<script>`)
- One on the `mounted()` hook (the only method with observable side effects)

`projectTitle` is a one-liner derived computed; annotating it adds noise
without value. Same for the `projectsStore` getter.

## What this change does NOT do

- No code-behavior changes — placeholder copy stays exactly as-is.
- No router or store changes.
- Does not promote the Backlog placeholder to a real backlog view (that
  belongs in a future `tasks` change after `tasks#REQ-Task-CRUD` lands).
- Does not retire any Bucket 3b REQ — leaves the planned-but-not-built REQs
  visible in the coverage report.

## Risk

Minimal. Spec-only change + 2 annotation lines on a placeholder Vue file.
The next coverage scan should match the Backlog view against the new REQ and
promote it from Bucket 2a to Bucket 1 (annotated).
