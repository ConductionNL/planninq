---
kind: code
---

# Share a read-only project board by link

## Why

You cannot show a project to someone without a Nextcloud account. A resident panel, a supplier or a council member who wants to follow progress needs an account, a place on the project and the rights of a member. Nothing in `src/` or `lib/` publishes a project: there is no public route in `appinfo/routes.php:10-38` and no `#[PublicPage]` controller. The only outside-facing surface is the contractor portal (`lib/Portal/PortalContributionProvider.php`), which serves named contractors, not an open link.

OpenProject makes a project public for anonymous visitors. Plane publishes a project behind a random link. Kanboard has "Enable public access" with a read-only board. Jira Data Center gives anonymous users browse rights on a project.

Parity rows: `prj-public-share` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because four competitors ship it and it sits in the core projects area.

## What changes

- A project manager creates a link to a read-only copy of the board: columns and cards, with titles, status, due dates and labels.
- The link has an end date, may have a password, and can be switched off at any time.
- Anyone with the link sees the board without signing in, and never sees who works on what.
- Every visit through the link is recorded, and the project's Share tab lists the live links.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-public-share`.

### `prj-public-share`: Share a read-only view of a project with people who have no account.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no public-link/anonymous-share code anywhere in src/ or lib/. The only external-facing surface is the contractor portal (prj-contractor-portal), which is scoped to named contractor references, not an anonymous public link."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/projects/project-settings/project-information/README.md:105 'Make a project public'; corpus: openproject/round4/M1-column.md 4.21 'a public project, which exposes the whole project to anonymous users' (read-only follows the Anonymous role's permissions) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/components/projects/row_actions_component.rb:177-178 'Make public' row action needs edit_project; app/models/project.rb:202 public_projects scope; :228 visible? is true for a public project to any user including anonymous, whose rights come from the Anonymous role, app/models/anonymous_user.rb:34"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §6 'DeployBoard publishes an entity behind a random anchor', TYPE_CHOICES include project, view, page; space viewsets set AllowAny (space/views/project.py:20) ; journeys.md siblings of intake 'got past authentication' ; source read at v1.4.2: apps/web/core/components/project/publish-project/modal.tsx:60,94 publishProject; apps/api/plane/app/urls/project.py:113 project-deploy-boards route; apps/api/plane/db/models/deploy_board.py:19-32 DeployBoard with random anchor; apps/api/plane/space/views/project.py:20 permission_classes AllowAny for the public read"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 6 'Enable public access ... the read-only board ... answered 200 without a session' ; menu-tree.md Public ; source read at v1.2.54: app/Template/project_view/share.php:19 'Enable public access', :10 'Public link'; app/ServiceProvider/AuthenticationProvider.php:153 BoardViewController readonly open to Role::APP_PUBLIC; app/Controller/BoardViewController.php:22 readonly; app/Model/ProjectModel.php:571 enablePublicAccess"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/allowing-anonymous-access-to-your-project-938847186.html): "corpus: jira-data-center/round4/M1-column.md 4.21 'you need to allow anonymous users to browse, search, and create issues in that project' (Allowing anonymous access to your project); browse without create is the read-only variant ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/allowing-anonymous-access-to-your-project-938847186.html 'you need to allow anonymous users to browse, search, and create issues in that project'; granting only Browse gives a read-only view without an account (read 2026-09-26)"

## Scope

### In scope

- Minting, listing and revoking read-only links on a project, built on OpenRegister's access links.
- A public board page in planninq that renders what the link serves.
- Property rules that keep people's names and descriptions out of anything a link serves.

### Out of scope

- Links that let the holder comment, upload or create tasks. OpenRegister supports `comment` and `upload` on a link; this change mints `read` only.
- Publishing the wiki, risks or log. Each can follow later as its own link subject.
- Making a project visible to every signed-in user of the instance. That is a group share to "everyone" in `projects-members-and-roles`.

## Impact

- Schema: `task` properties that name people or hold free text (`assignedTo`, `reporter`, `watchers`, `description`, `contractorRef`) get property-level read rules for signed-in users only.
- Planninq creates one OpenRegister view per shared project, selecting its columns and tasks.
- Controller and template: a `#[PublicPage]` route `/apps/planninq/public/{anchor}` that serves the public board shell; the data comes from OpenRegister's `GET /apps/openregister/api/public/links/{anchor}`.
- Views: a Share tab in the project settings sidebar, and a `PublicBoard` component.
- Extends the flat spec `openspec/specs/projects.md`.
- Depends on: `projects-members-and-roles` (the manager role that is offered the Share tab), `boards-configurable-columns` (lane A) for columns that are objects the view can select.

## Risks

### Risk 1: any reader can mint a link through the API

**Severity**: High
**Mitigation**: OpenRegister's mint guard asks whether the caller can read everything the view names (`lib/Service/Sharing/AccessLinkMintGuard.php:88-196`), so any project member could publish the project through `POST /apps/openregister/api/access-links`. Planninq offers the Share tab to managers only. The gap is named as a follow-up for openregister: a schema-level rule for who may mint. Until then the Share tab lists every live link on the project, whoever made it, so a manager sees and can revoke them.

### Risk 2: a link leaks people's names

**Severity**: High
**Mitigation**: OpenRegister's link reader filters every served object through property-level read rules (`lib/Service/Sharing/AccessLinkReader.php:349-367`). The people and free-text properties of `task` get a read rule for signed-in users, so a link holder never receives them, whatever the page renders. Task 1.2 asserts the raw JSON of a link answer.

### Risk 3: big boards are cut off

**Severity**: Low
**Mitigation**: A view link serves at most 200 objects per answer (`AccessLinkReader.php:95-100`). The public board says "Showing the first 200 cards" when the answer is full.
