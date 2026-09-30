# Design: link tasks to commits and merge requests in a code forge

## Context

What exists at de35541:

- No forge concept anywhere: no schema property, no view, no service.
- The task schema has `key` (`lib/Settings/planninq_register.json:330-335`, "Human-readable unique work-item key (e.g. PLX-123)"); the lane A change `tasks-readable-keys` (merged on development after de35541) fills it as `{projectKey}-{n}` for new tasks and back-fills existing ones. The project key is two to ten characters, A-Z and 0-9, starting with a letter.
- The task schema's read rule is membership of the task's project (`:52-74`); `plannedTimeEntry` shows how a dependent object inherits it through a lookup (`:914-960`).
- Pre-save listeners on OpenRegister's `ObjectCreatingEvent` may reject a write or merge fields into it (OpenRegister at 63ddfd5, `lib/Db/MagicMapper.php:7160` for the create path); planninq already registers listeners on OpenRegister events in `lib/AppInfo/Application.php:536-563`.
- `docs/ARCHITECTURE.md` section 5 question 5: planninq owns no GitHub or GitLab API code; the integration app maps the external API. That app is integriq (formerly OpenConnector). Integriq imports configuration sets of sources, endpoints, mappings, rules and jobs as an OpenAPI document, with a preview and an explicit confirmation (integriq at 9d2b6ea8, `openspec/specs/configuration-export-import/spec.md`, REQ-003, REQ-007, REQ-008), and ships configuration sets as files in its own `lib/Settings/configurations/`.
- ADR-001 rule 5: integration plumbing lives in Beheer; results show inline on the object.

## Goals / non-goals

Goals:
- Links from a task to the forge activity about it, readable only by the project.
- Automatic links from forge events, with no forge code in planninq.
- Manual links for teams without an integration.

Non-goals:
- Status changes from commit messages, two-way issue sync, creating forge objects.

## Decisions

### Decision 1: one `forgeLink` object per linked item
Properties: `task` (`$ref: task`), `project` (`$ref: project`, denormalised for the read rule and filtering), `taskKey` (string, what the forge text named), `kind` (`commit`, `branch`, `mergeRequest`, `issue`, `link`), `url` (required), `title`, `repository`, `externalId` (for example `github:acme/portal#42` or a commit sha), `state` (`open`, `merged`, `closed`, or empty), `author`, `occurredAt`, `source` (`manual` or `integriq`). Read: members of `project` and admins. Create: members of `project` and admins. Update and delete: admins, and members of `project` for `source: manual`. Separate objects rather than an array on the task, so a webhook retry or two forges writing at once cannot overwrite each other, and the task's own audit trail is not flooded with forge events.

### Decision 2: planninq resolves the task, integriq only names the key
Integriq's mapping writes `taskKey` and the forge fields; it does not look anything up in planninq. `ForgeLinkResolveListener` on `ObjectCreatingEvent` for `forgeLink` finds the task whose `key` equals `taskKey` (case-insensitive), merges `task` and `project` into the object, and rejects the create when no task has that key or when a link with the same `externalId` already exists on that task. For a manual link the task page sends `task` directly and the listener fills `project` from it. Keeping the lookup in planninq means integriq needs no knowledge of planninq's schemas beyond the target, and the key format lives in one place. Amended at build time: OpenRegister merges a listener's modified data only after every pre-save listener has run, so `ProjectMemberAccessListener` cannot see the project this listener fills in. Both therefore resolve the task through `ProjectMembershipService::linkedTask()` (by `task` id, else by `taskKey`), and the membership gate always takes the task's project, never a `project` the client sent, so a link cannot claim the caller's own project while it names another project's task. `forgeLink` joins the scoped and gated schemas.

### Decision 3: planninq ships the integriq configuration, integriq runs it
`lib/Settings/integriq/planninq-code-forge.json` is an integriq configuration set: a webhook endpoint for GitHub and one for GitLab (with signature checks per integriq's webhook signing), mappings from push, branch and pull or merge request events to `forgeLink`, and a rule that emits one `forgeLink` per task key found in a commit message, branch name or merge request title (pattern `[A-Z][A-Z0-9]{1,9}-[0-9]+`). The mapping updates the link with the same `externalId` when a merge request changes state. The Beheer section "Code forges" in the admin settings explains the three steps (import the file in integriq, add the webhook URL and secret in the forge, name task keys in commits) and offers the file for download, or opens integriq's import with it when integriq is installed. Planninq never calls a forge.

Amended at build time (lane 20, 30 Sep), from integriq at development:
- Integriq's mapping is Twig with no function that cuts a pattern out of text (lib/Twig/MappingExtension.php), and no rule emits several objects from one event. So the mapping puts the forge text (pull request title and branch, or the head commit message and ref) in `taskKey`, and `ForgeLinkResolveListener` finds the first task key in it that belongs to a task (the fallback this design named: one link per event, the first key).
- Integriq cannot update by `externalId` either. A repeat of an integration link (same task, same `externalId`) therefore replaces the older link inside the create, so the state follows the forge and the forge never sees a refused delivery. A repeat added by hand is still refused.
- A push links its head commit; a pull request event links the pull request. A `ping` or a push without commits carries no key and is refused (422), which GitHub shows on the webhook page and which does no harm.
- GitHub only in this change. Integriq's `webhook_signature` rule knows the `github`, `openconnector`, `stripe` and `teams` schemes, not GitLab's `X-Gitlab-Token`. An unsigned GitLab endpoint would let anyone write links, so GitLab teams paste links by hand until integriq checks that token; the request to integriq is drafted in for-ruben/integriq-gitlab-webhook-token.md.
- The Beheer section offers the file for download and shows the webhook address; it does not open integriq's import, which has no deep link to prefill.

### Decision 4: the Code section on the task page
`TaskForgeLinks.vue` on `TaskDetail` lists the task's `forgeLink` objects, newest first: an icon and text for the kind, the title as a link to the forge (opens in a new tab, `rel="noopener"`), the repository, a state chip with text, and the date. "Add link" takes a pasted URL and derives kind, repository and number from GitHub, GitLab and Gitea URL patterns, otherwise `link`. A manual link can be removed by project members; integriq links only by admins, because the next event would recreate them. An empty section says "No code linked yet. Name VC-12 in a commit message or paste a link."

## Risks / trade-offs

- [Leaking repository activity] -> Project-scoped read rule (Decision 1).
- [Duplicates] -> `externalId` update in integriq, duplicate refusal in the listener (Decision 2).
- [A key that matches several projects' formats] -> Keys are unique across projects (`tasks-readable-keys` Decision 2), so one key resolves to one task.

## Open questions

- Integriq's mapping must be able to emit one target object per key found in a text and to update by `externalId`. If its rule set cannot split one event into several objects, the fallback is one `forgeLink` per event with `taskKey` holding the first key, and the task page states that only the first key is linked.
