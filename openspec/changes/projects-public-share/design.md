# Design: share a read-only project board by link

## Context

What exists at `de35541`:

- **Routes.** `appinfo/routes.php:10-38` appends planninq's API routes to the AppHost standard table. None is public, and no controller in `lib/Controller/` carries `#[PublicPage]`. `templates/` holds `index.php` and the settings template only.
- **Board.** `src/views/ProjectBoard.vue` renders the board for a signed-in member, reading the project and its tasks through the object store (`src/store/projects.js:242-268`, `:775-785`).
- **Task fields.** `task` carries people in `assignedTo`, `reporter` and `watchers`, a contractor in `contractorRef`, and free text in `description` (`lib/Settings/planninq_register.json:177-390`). None has a property-level rule; the object rule decides everything.
- **Contractor portal.** `lib/Portal/PortalContributionProvider.php` serves named contractors through the portal app. It is not an anonymous link.

What OpenRegister offers, read at `ConductionNL/openregister` development `c53dd0685`:

- **Access links.** `AccessLinkService` mints, resolves and revokes links whose principal is the link itself (`lib/Service/Sharing/AccessLinkService.php`). A mint without an end date is refused, a password is optional, and every use is recorded in the audit trail. A link's subject is an object, a view or a file (`lib/Db/AccessLink.php:87-108`), and its capabilities are `read`, `comment` and `upload` (`:115-140`).
- **Routes.** Mint, list, update and revoke at `/api/access-links` for signed-in users; the data behind a link at `GET /api/public/links/{anchor}` and a generic page at `/links/{anchor}` (`appinfo/routes.php:1052-1071`).
- **Mint guard.** `AccessLinkMintGuard::mayMint` allows a mint when the caller can read the subject, and for a view, everything the view names (`lib/Service/Sharing/AccessLinkMintGuard.php:88-196`). It does not ask for update rights.
- **Reader.** A view link serves at most 200 objects (`lib/Service/Sharing/AccessLinkReader.php:95-100`), and every served object passes through `PropertyRbacHandler::filterReadableProperties` (`:349-367`).

## Goals / non-goals

Goals:

- A manager publishes a read-only board to people without an account, for a limited time.
- The link never carries who works on what.
- The manager can see and revoke every live link.

Non-goals:

- Write access through a link.
- A public wiki, risk register or log.

## Decisions

### Decision 1: the link is an OpenRegister access link on a view

Sharing creates two things. Planninq creates an OpenRegister view that selects the project's `column` and `task` objects by `project`, named after the project. It then mints an access link with subject type `view` and the capability `read`, with the end date and optional password the manager chose.

OpenRegister then owns what matters for security: the anchor, the end date, the password check, the uniform 404 for a revoked or expired link, and the record of every use. Planninq writes no token table and no public data controller.

Alternative considered: a planninq token on the project and a `#[PublicPage]` data controller that reads the board with `_rbac: false`. It would rebuild everything the access link service already does, and a bug in it would publish data with the rules off.

Alternative considered: an object link on the project. It serves the one project object and not its tasks, so the page would show a title and nothing else.

### Decision 2: planninq renders the board, OpenRegister serves the data

The public URL is `/apps/planninq/public/{anchor}`, a `#[PublicPage]` template route that loads a small `PublicBoard` bundle with no Nextcloud chrome that needs a session. The bundle reads `GET /apps/openregister/api/public/links/{anchor}`, asks for the password when the answer says one is needed, and draws the columns and cards read-only: title, status, due date and labels. The generic page at `/links/{anchor}` stays available but shows a list, not a board.

The page has no forms except the password field, sets `noindex`, and says who shared it only as the project's name.

### Decision 3: people and free text never leave through a link

`assignedTo`, `reporter`, `watchers`, `contractorRef` and `description` on `task` get a property-level read rule for the `authenticated` group. For project members nothing changes, because the object rule already limits them to members. A link holder is not a signed-in user, so the link reader drops those properties before it answers. This is declared on the schema (ADR-031) and holds whatever a future page renders.

Labels stay visible: they are app-wide vocabulary (`docs/ARCHITECTURE.md:226`), not personal data.

### Decision 4: the Share tab is for managers, and it lists every link

The project settings sidebar gets a Share tab for owners and managers. It lists every live link on the project's view, with its end date, whether it has a password, who made it and how often it was used, and a "Switch off" button per link. "Create link" asks for an end date (default 30 days, at most one year) and an optional password, then shows the link with a copy button.

Because the mint guard asks only for read rights, a member could still mint through the API. Listing every link, whoever made it, keeps that visible to the managers. A schema-level mint rule in openregister is the proper fix and is named as a follow-up.

## Risks / trade-offs

- [A member mints a link through the API] -> Every link is listed and revocable by managers; follow-up in openregister for a mint policy.
- [The view drifts from the project] -> The view filters by `project` only, so new columns and tasks appear on the public board without any update.
- [The project is deleted while links are live] -> Deleting a project also revokes its links and deletes its view, in the same cascade as `deleteProject` (`src/store/projects.js:530`).

## Open questions

- Should openregister accept a per-schema rule for who may mint links, so planninq can limit minting to managers on the server? Proposed as an openregister change.
