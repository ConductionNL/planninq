# AGENTS.md

Guide for coding agents working in this repository. Every command below is defined in
`composer.json`, `package.json` or the `Makefile` in this repo.

## What this app is

Planninq is a kanban-based project and task management app for Nextcloud dev and IT teams.
It manages projects, tasks, kanban boards with WIP limits, backlogs and time entries, and is
built as a thin client on OpenRegister.

## Setup

- Install dependencies: `composer install` and `npm ci`.
- Development environment: start the OpenRegister stack with
  `docker compose -f ../openregister/docker-compose.yml up -d` (needs an `openregister`
  checkout next to this one), then `make dev-link`, `npm run build` and
  `docker exec nextcloud php occ app:enable planninq`. See `README.md`, "Development".
- Evaluation stack: `docker compose -f docker-compose.yml up -d --build`, then open
  `http://localhost:8080/apps/planninq/`.
- Demo stack: `docker compose -f planninq-compose.yaml up -d`.

## Build

- `npm run build` builds the production frontend bundle with webpack. The `js/` output is
  not committed; build before enabling the app or the UI is blank.
- `npm run dev` builds a development bundle; `npm run watch` rebuilds on change.

## Test

- PHP unit tests (PHPUnit): `composer test:unit`
- PHP coverage report: `composer test:coverage`
- JavaScript unit tests (Vitest, `tests/vitest`): `npm run test`
- End-to-end tests (Playwright, `tests/e2e`): `npm run test:e2e`. Install the browser once
  with `npm run test:e2e:install`.

## Lint and quality

- Full PHP gate: `composer check:strict` (lint, phpcs, phpmd, psalm, phpstan, tests).
  `composer check` is the quicker subset.
- Single tools: `composer phpcs`, `composer phpmd`, `composer psalm`, `composer phpstan`;
  auto-fix with `composer phpcs:fix` or `composer cs:fix`.
- Frontend: `npm run lint` (ESLint) and `npm run stylelint`.
- Manifest and translation checks: `npm run check:manifest`, `npm run check:l10n`.

## OpenSpec flow

Work is spec-driven (`openspec/config.yaml`).

1. Propose a change in `openspec/changes/<change-name>/` with a proposal, design, tasks and
   delta specs.
2. When the change is implemented, its requirements land in `openspec/specs/<capability>/spec.md`.
3. The finished change moves to `openspec/changes/archive/`.

## Architecture decisions

ADRs for this app live in `openspec/architecture/`. Standards research and data model
decisions are in `docs/ARCHITECTURE.md`.

## Repo conventions

- Planninq owns no database tables. All data is stored as OpenRegister objects, with the
  register defined in `lib/Settings/planninq_register.json`.
- Pages are manifest config in `src/manifest.json`.

## Commits

Use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/), for example
`feat(board): ...` or `fix(task): ...`. See `CONTRIBUTING.md`.
