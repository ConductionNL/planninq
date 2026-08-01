# Planix — Flow-based kanban project and task management for Nextcloud dev and IT teams

## Overview

Planix is a Kanban-based project and task management app for Nextcloud, built as a thin client on OpenRegister. It manages projects, tasks, kanban boards with WIP limits, backlogs, and time entries for internal dev and IT teams.

## Architecture

- **Type**: Nextcloud App (PHP backend + Vue 3 frontend)
- **Data layer**: OpenRegister (all data stored as register objects)
- **Pattern**: Thin client — Planix provides UI/UX, OpenRegister handles persistence
- **License**: EUPL-1.2

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.1+, Nextcloud AppFramework |
| Frontend | Vue 3.5, Pinia, @nextcloud/vue 9 |
| Data | OpenRegister (JSON object store) |
| Testing | PHPUnit (unit + integration), Newman (API) |
| Quality | PHPCS, PHPMD, Psalm, PHPStan, ESLint, Stylelint |

## Key Files

| File | Purpose |
|------|---------|
| `lib/AppInfo/Application.php` | App bootstrap, listener + repair registration |
| `lib/Controller/SettingsController.php` | Settings API endpoints |
| `lib/Service/SettingsService.php` | Settings business logic, OpenRegister integration |
| `lib/Listener/DeepLinkRegistrationListener.php` | Registers deep link patterns with OpenRegister search |
| `lib/Repair/InitializeSettings.php` | Import register on install/upgrade |
| `lib/Settings/planix_register.json` | OpenAPI 3.0 register schema definition |
| `src/App.vue` | App shell (navigation + routing) |
| `src/navigation/MainMenu.vue` | App navigation sidebar |
| `src/views/settings/UserSettings.vue` | User settings dialog |
| `openspec/config.yaml` | OpenSpec project configuration |

## Development Setup

See the workspace-level `.claude/docs/` for:
- `commands.md` — available Claude commands
- `testing.md` — testing workflows
- `app-lifecycle.md` — full development lifecycle

## Standards

This app follows all Conduction app standards (16 company-wide ADRs covering OpenRegister, REST API, NL Design, i18n, security, testing, and documentation).
