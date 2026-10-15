---
kind: code
---

# Link tasks to commits and merge requests in a code forge

## Why

A developer cannot see, on a planninq task, the commits, branches and merge requests that work on it. No schema in `lib/Settings/planninq_register.json` has a forge, commit or merge request reference, and nothing in `src/` or `lib/` mentions one. That is a notable gap for an app whose own description targets dev and IT teams (`openspec/config.yaml` context, and the matrix note on the row).

The integration design was decided before this pass: `docs/ARCHITECTURE.md` section 5, question 5, "GitHub/GitLab sync: via OpenConnector in V1. Planninq owns no GitHub/GitLab API code. OpenConnector handles the external API mapping." OpenConnector is now integriq. `docs/FEATURES.md` lists "GitHub/GitLab sync (via OpenConnector)" as V1. This change follows that answer: integriq receives the forge's events and writes link objects; planninq stores the links, resolves which task they belong to, and shows them.

OpenProject's GitHub and GitLab modules receive the forges' events and show pull and merge requests on work packages. Jira links commits, branches and pull requests to issues through keys in commit messages ("smart commits").

Parity rows: `int-git` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because two competitors link work items to forge activity and planninq's target users are dev and IT teams.

This change extends the flat spec `openspec/specs/tasks.md` through a new capability, `code-forge-links`.

## What changes

- A task shows a "Code" section with its linked commits, branches and merge requests, each with its repository, title and state.
- A project member can paste a link to a commit or merge request onto a task by hand, with or without any integration.
- When an admin connects GitHub or GitLab through integriq, a commit, branch or merge request that mentions a task key such as `VC-12` is linked to that task automatically, and a merge request's state follows the forge.
- Planninq contains no forge API code. It ships the integriq configuration and a guide in Beheer.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `int-git`.

### `int-git`: Link tasks to commits and merge requests in a code forge.

- Area `integration`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no forge, commit or merge-request reference in any schema of lib/Settings/planninq_register.json or in src/ and lib/"
- Note: "info.xml targets dev and IT teams, which makes this gap notable."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 12.22 'the GitHub and GitLab modules receive inbound events (modules/github_integration/, modules/gitlab_integration/)' ; source read at v17.8.0: modules/github_integration/lib/open_project/github_integration/engine.rb:94-96 registers the inbound 'github' hook and :82 a GitHub tab on the work package; modules/github_integration/app/models/github_pull_request.rb; modules/gitlab_integration/app/models/gitlab_merge_request.rb and modules/gitlab_integration/lib/open_project/gitlab_integration/engine.rb:73 GitLab tab"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/processing-issues-with-smart-commits-938845602.html, https://confluence.atlassian.com/adminjiraserver/configuring-workflow-triggers-938847513.html): "corpus: jira-data-center/round4/M1-column.md 3.9 'workflow triggers from development tools' ('Fisheye/Crucible, Bitbucket and GitHub', 8.9); 12.5 'Jira's Subversion integration lets you see Subversion commit information relevant to each issue' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/processing-issues-with-smart-commits-938845602.html 'If you use Bitbucket or GitHub ... you can process your Jira Software issues using special commands, called Smart Commits'; https://confluence.atlassian.com/adminjiraserver/configuring-workflow-triggers-938847513.html triggers from 'Fisheye/Crucible, Bitbucket and GitHub' (read 2026-09-26)"


## Scope

### In scope

- A `forgeLink` schema, one object per linked commit, branch, merge request or issue.
- A pre-save listener that resolves a task key to the task and its project.
- The "Code" section on the task page, with manual add and remove.
- An integriq configuration for GitHub and GitLab webhooks, shipped in `lib/Settings/`, and a Beheer section that explains how to import it.

### Out of scope

- Creating branches or merge requests from planninq, and changing task status from commit keywords (Jira's "smart commit" transitions).
- Syncing tasks to forge issues both ways. `docs/FEATURES.md` "GitHub/GitLab sync" in full is larger than this row, which asks for links.
- Any forge API call made by planninq.
- Forge links on board cards (`boards-card-display` decides what a card shows).

## Impact

- Schema: new `forgeLink` (`task`, `project`, `taskKey`, `kind`, `url`, `title`, `repository`, `externalId`, `state`, `author`, `occurredAt`, `source`).
- Backend: a new `lib/Listener/ForgeLinkResolveListener.php` on `ObjectCreatingEvent`, and the integriq configuration file `lib/Settings/integriq/planninq-code-forge.json`.
- Views: a new `src/components/TaskForgeLinks.vue` on `TaskDetail`; a Beheer section "Code forges" in `src/views/settings/Settings.vue`.
- Specs and tests: the exact schema count in `openspec/specs/project-delivery/spec.md:68-72` and `tests/unit/Settings/PlanninqRegisterSchemaTest.php:370` moves up by one.
- Depends on: `tasks-readable-keys` (the task keys a commit message names).

## Risks

### Risk 1: links leak repository activity to people outside the project
**Severity**: Medium
**Mitigation**: a `forgeLink` is readable only by members of the task's project, through the same membership lookup the task schema uses.

### Risk 2: the same event arrives twice
**Severity**: Low
**Mitigation**: the integriq mapping updates the existing link that carries the same `externalId`, and the listener refuses to create a second link with the same `externalId` on the same task, so a webhook retry never shows a duplicate.

### Risk 3: integriq's configuration shape moves
**Severity**: Low
**Mitigation**: the configuration file goes through integriq's own import with preview, and a PHPUnit test asserts that it parses and targets only the `forgeLink` schema and fields planninq declares; the planninq side (schema, listener, section) does not depend on it.
