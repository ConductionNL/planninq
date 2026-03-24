# Planix — OpenSpec

This folder contains the configuration and specifications for Planix.

## Goal

Planix is a Kanban-based project and task management app for Nextcloud, built as a thin client on OpenRegister. It manages projects, tasks, kanban boards with WIP limits, backlogs, and time entries — giving internal dev and IT teams a focused workflow tool built directly into their Nextcloud environment. Unlike Nextcloud Deck (which lacks backlog management, time tracking, and WIP limits), Planix closes the gap between Deck's simplicity and Jira's complexity.

## Structure

| File / Folder | Purpose |
|---|---|
| `app-config.json` | Core app configuration — all choices from `/opsx:app-create` and `/opsx:app-explore` |
| `config.yaml` | OpenSpec project config — rules, context, standards |
| `specs/` | Feature specifications |
| `changes/` | In-progress and archived OpenSpec changes |

## Commands

- `/opsx:app-explore planix` — Think through and update app configuration interactively
- `/opsx:app-apply planix` — Apply `app-config.json` changes to the actual app files
- `/opsx:ff {feature-name}` — Implement a planned feature from `specs/`
