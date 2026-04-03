# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-04-03

### Added
- Define task schema with all properties (title, description, status, priority, project, etc.)
- Define project schema with all properties (title, description, status, color, icon, members, etc.)
- Define column schema for kanban boards (title, project, order, wipLimit, color, type)
- Define timeEntry schema for time tracking (task, user, duration, date, description)
- Define label schema for categorization (title, color, description)
- Add seed data: 5 labels (Bug, Feature, Docs, Design, Infrastructure)
- Add seed data: 3 projects (Client Portal v2, Infrastructure Migration, Onboarding Automation)
- Add seed data: 12 columns (4 per project: To Do, In Progress, Review, Done)
- Add seed data: 5 tasks with realistic assignments and priorities
- Add seed data: 3 time entries referencing task seeds
- Register repair step for automatic schema import on app install/upgrade
- Bump register version to 0.2.0

### Changed
- Remove placeholder example schema from planix_register.json
- Remove example schema references from DeepLinkRegistrationListener
