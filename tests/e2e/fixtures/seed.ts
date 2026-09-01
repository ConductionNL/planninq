/*
 * SPDX-FileCopyrightText: 2026 Planninq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e fixture seeder.
 *
 * The four board/collaboration/label/reminder specs used to self-`test.skip()`
 * whenever the environment carried no pre-existing Planninq data — so on every
 * CI run they reported *skipped* (non-failing) instead of exercising a real
 * assertion. That is the textbook "phantom green" failure mode: gate-19 sees a
 * real `@e2e` file reference and is satisfied, but the scenario never runs.
 *
 * This module creates the minimum fixture set those specs need — one project
 * with the admin as a member, its default columns, an assignee/priority/due-date
 * spread of tasks (one approaching, one overdue), and a label attached to a task
 * — so the tightened `expect(...).not.toHaveCount(0)` guards resolve on a fresh
 * container. It is invoked from `global-setup.ts` after the browser login.
 *
 * Auth: HTTP Basic (admin/admin by default), mirroring
 * `tests/integration/planninq.postman_collection.json`. Basic-auth API requests
 * bypass Nextcloud's session-cookie CSRF check, so we do not need the
 * `requesttoken` that the browser storage-state cookie jar lacks.
 *
 * Idempotency: every create is check-by-title-first / reuse-if-present, so
 * repeated runs against a persistent dev container never accumulate duplicate
 * fixture projects, columns, tasks or labels.
 */

import type { APIRequestContext } from '@playwright/test'

import { request } from '@playwright/test'

// The OpenRegister register SLUG, not the app id. It moved from `planix` to
// `planninq` together with the MigrateRegisterSlug repair step, which renames
// the register ROW: OR resolves a register by slug and by nothing else, so the
// literal and the row move in the same release or neither resolves.
const REGISTER = 'planninq'

/** Stable titles used for check-then-create idempotency. */
export const FIXTURE = {
	projectTitle: 'E2E Fixture Board',
	labelTitle: 'E2E Bug',
	tasks: {
		approaching: 'E2E Due Soon Task',
		overdue: 'E2E Overdue Task',
		normal: 'E2E Normal Task',
	},
} as const

interface SeedOptions {
	username?: string
	password?: string
}

interface OrObject {
	id?: string
	uuid?: string
	title?: string
	'@self'?: { id?: string }
	[key: string]: unknown
}

/**
 * Extract the canonical object id from an OpenRegister object payload.
 *
 * @param o OpenRegister object payload
 * @return the object id, or undefined when none is resolvable
 */
function objId(o: OrObject | null | undefined): string | undefined {
	if (!o) {
		return undefined
	}
	return o.id ?? o['@self']?.id ?? o.uuid
}

/**
 * ISO date (YYYY-MM-DD) offset from today by `days`.
 *
 * @param days number of days from today (negative for the past)
 * @return the offset date as a YYYY-MM-DD string
 */
function isoDate(days: number): string {
	const d = new Date()
	d.setDate(d.getDate() + days)
	return d.toISOString().slice(0, 10)
}

/**
 * Seed the fixtures required by the board / collaboration / label / reminder
 * e2e specs. Safe to call repeatedly (idempotent).
 *
 * @param baseURL Nextcloud base URL, resolved by tests/e2e/base-url.ts
 * @param opts    Optional admin credentials (default admin/admin)
 * @return true when seeding completed, false when Planninq/OpenRegister is not
 *         installed in this environment (specs then take their legitimate
 *         "app not installed" skip path).
 */
export async function seedFixtures(
	baseURL: string,
	opts: SeedOptions = {},
): Promise<boolean> {
	// ADMIN_USER / ADMIN_PASSWORD are what the shared Conduction/.github quality
	// workflow exports; NC_ADMIN_* is the local convention.
	const username
		= opts.username
			?? process.env.NC_ADMIN_USER
			?? process.env.ADMIN_USER
			?? 'admin'
	const password
		= opts.password
			?? process.env.NC_ADMIN_PASS
			?? process.env.ADMIN_PASSWORD
			?? 'admin'

	const ctx: APIRequestContext = await request.newContext({
		baseURL,
		// `send: 'always'` is load-bearing, not a tidy-up.
		//
		// Playwright's default is `send: 'unauthorized'`: it withholds the
		// Authorization header until the server answers 401 *with* a
		// `WWW-Authenticate` challenge. Nextcloud's app routes return a bare
		// 401 with no such header, so Playwright never learned the scheme and
		// never retried — the very first write (`POST /apps/planninq/api/projects`)
		// came back 401, `seedFixtures` bailed with "project create failed
		// (status 401)", and every spec then ran against an EMPTY instance.
		// That is why the whole 15-test regression suite failed on a fresh
		// container while the same credentials worked from curl. It only ever
		// looked green where a shared instance already happened to hold data.
		httpCredentials: { username, password, send: 'always' },
		extraHTTPHeaders: {
			'Content-Type': 'application/json',
			// OCS marker keeps NC from redirecting API calls to the login form.
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
		},
	})

	try {
		// Reset the admin settings the specs treat as preconditions.
		//
		// `due-date-reminder-settings.spec.ts` asserts the lead-time field shows
		// its default of 24 and then SAVES 48 — an app config value that
		// survives the run. On a persistent instance the very next run therefore
		// failed on its own leftovers ("expected 24, received 48"), so the test
		// passed exactly once per container. Preconditions belong to the
		// harness, not to whichever spec ran last.
		const settingsReset = await ctx.post(
			'/index.php/apps/planninq/api/settings',
			{
				data: { due_reminder_lead_hours: '24' },
				failOnStatusCode: false,
			},
		)
		if (!settingsReset.ok()) {
			console.log(`[seed] could not reset admin settings (status ${settingsReset.status()})`)
		}

		const objectsUrl = (schema: string) => `/index.php/apps/openregister/api/objects/${REGISTER}/${schema}`

		// Probe the register — and tell "not installed" apart from "installed and
		// broken", because those must NOT produce the same outcome.
		//
		// This used to `return false` on any 4xx/5xx, so every spec then took its
		// "app not installed" skip path and the suite reported SKIPPED. That is
		// the phantom green this file exists to prevent, one layer up: gate-19
		// sees real `@e2e` references, the run is not red, and nothing ran.
		//
		// Observed 2026-08-24: `/apps/planninq/api/settings` answered 200 — the
		// app installed, migrated and healthy — while
		// `/apps/openregister/api/objects/planix/project` answered
		// `404 {"message":"Register not found: 'planix'"}` for a register whose
		// row and magic tables both exist (openregister#2820). The entire suite
		// silently skipped against a working app.
		//
		// `settingsReset` above is the discriminator: it hits the app's OWN route,
		// so a 404 there means the app is genuinely absent and skipping is honest.
		// Anything else means the app is there and its data layer is not, which is
		// a failure and has to be loud.
		//
		// NOTE: REGISTER is the OpenRegister register SLUG, which is frozen at
		// `planix` by fleet policy and does NOT follow the planix -> planninq app
		// rename. A 404 here is not a stale-slug bug; do not "fix" it by renaming.
		const appIsInstalled = settingsReset.status() !== 404
		const probe = await ctx.get(objectsUrl('project'), {
			failOnStatusCode: false,
		})
		if (probe.status() >= 400) {
			if (appIsInstalled) {
				throw new Error(`[seed] Planninq is installed (settings route answered ${settingsReset.status()}) `
					+ `but its OpenRegister register is unreachable: GET ${objectsUrl('project')} `
					+ `returned ${probe.status()}. Refusing to skip — skipping here would report the `
					+ 'whole suite as passing-by-omission against a working app. See openregister#2820.')
			}

			console.log('[seed] Planninq not installed (settings route 404); skipping fixture seed')
			return false
		}

		/**
		 * GET a schema collection as a plain array (OR wraps results in { results }).
		 *
		 * @param schema OpenRegister schema slug
		 * @param query  optional query string (e.g. ?project=...)
		 * @return the collection rows
		 */
		const list = async (
			schema: string,
			query = '',
		): Promise<OrObject[]> => {
			// `_limit` is load-bearing, and the underscore is too.
			//
			// OpenRegister pages at 20 by default, and every "does the fixture
			// already exist?" check below is a findByTitle() over THIS list. Once
			// the instance held more than 20 projects the seeded board fell off
			// page one, the check said "absent", and each run created ANOTHER
			// copy — four "E2E Fixture Board" projects on this instance before it
			// was noticed. The specs then failed on `toHaveCount(1)`, which reads
			// like a UI bug and is actually a seeding bug.
			//
			// A bare `limit=` would be worse than useless: OpenRegister treats an
			// unprefixed query key as a PROPERTY FILTER, so it silently matches
			// nothing and returns an empty page — the same "absent" answer, with
			// no error to notice.
			const sep = query.includes('?') ? '&' : '?'
			const res = await ctx.get(
				`${objectsUrl(schema)}${query}${sep}_limit=500`,
				{ failOnStatusCode: false },
			)
			if (!res.ok()) {
				return []
			}
			const body = await res.json().catch(() => ({}))
			const rows = Array.isArray(body)
				? body
				: (body.results ?? body.data ?? [])
			return Array.isArray(rows) ? rows : []
		}

		/**
		 * Find an existing object by exact title.
		 *
		 * @param rows  collection rows to search
		 * @param title exact title to match
		 * @return the matching object, or undefined
		 */
		const findByTitle = (
			rows: OrObject[],
			title: string,
		): OrObject | undefined => rows.find((r) => r.title === title)

		// ── Project (via the Planninq policy-enforcing create proxy) ────────
		let project = findByTitle(await list('project'), FIXTURE.projectTitle)
		if (!project) {
			const res = await ctx.post(
				'/index.php/apps/planninq/api/projects',
				{
					data: {
						title: FIXTURE.projectTitle,
						description:
							'Seeded by Playwright global-setup for real e2e assertions.',
						color: '#4376FC',
						icon: '🧪',
					},
					failOnStatusCode: false,
				},
			)
			if (!res.ok()) {
				console.log(`[seed] project create failed (status ${res.status()}); skipping fixture seed`)
				return false
			}
			project = (await res.json()) as OrObject
		}
		const projectId = objId(project)
		if (!projectId) {
			console.log('[seed] could not resolve seeded project id; skipping remaining fixtures')
			return false
		}

		// ── Columns (default set) ───────────────────────────────────────────
		const wantedColumns = [
			{ title: 'To Do', order: 0, wipLimit: null, type: 'active' },
			{ title: 'In Progress', order: 1, wipLimit: 3, type: 'active' },
			{ title: 'Review', order: 2, wipLimit: 2, type: 'active' },
			{ title: 'Done', order: 3, wipLimit: null, type: 'done' },
		]
		const existingColumns = await list(
			'column',
			`?project=${encodeURIComponent(projectId)}`,
		)
		const columnIdByTitle: Record<string, string> = {}
		for (const col of wantedColumns) {
			let existing = findByTitle(existingColumns, col.title)
			if (!existing) {
				const res = await ctx.post(objectsUrl('column'), {
					data: { ...col, project: projectId },
					failOnStatusCode: false,
				})
				if (res.ok()) {
					existing = (await res.json()) as OrObject
				}
			}
			const id = objId(existing)
			if (id) {
				columnIdByTitle[col.title] = id
			}
		}

		// ── Label (attached to a task below) ────────────────────────────────
		let label = findByTitle(await list('label'), FIXTURE.labelTitle)
		if (!label) {
			const res = await ctx.post(objectsUrl('label'), {
				data: {
					title: FIXTURE.labelTitle,
					color: '#E4572E',
					description: 'Seeded label',
				},
				failOnStatusCode: false,
			})
			if (res.ok()) {
				label = (await res.json()) as OrObject
			}
		}
		const labelId = objId(label)

		// ── Tasks (assignee + priority + due-date spread) ───────────────────
		const existingTasks = await list(
			'task',
			`?project=${encodeURIComponent(projectId)}`,
		)
		const ensureTask = async (
			title: string,
			extra: Record<string, unknown>,
		): Promise<void> => {
			if (findByTitle(existingTasks, title)) {
				return
			}
			await ctx.post(objectsUrl('task'), {
				data: {
					title,
					status: 'open',
					project: projectId,
					column: columnIdByTitle['To Do'],
					assignedTo: username,
					...extra,
				},
				failOnStatusCode: false,
			})
		}

		// Approaching: due tomorrow → "Due soon" badge; carries the label.
		await ensureTask(FIXTURE.tasks.approaching, {
			priority: 'high',
			dueDate: isoDate(1),
			labels: labelId ? [labelId] : [],
			column: columnIdByTitle['In Progress'] ?? columnIdByTitle['To Do'],
			columnOrder: 0,
		})
		// Overdue: due yesterday → "Overdue" badge.
		await ensureTask(FIXTURE.tasks.overdue, {
			priority: 'urgent',
			dueDate: isoDate(-1),
			column: columnIdByTitle['To Do'],
			columnOrder: 1,
		})
		// Normal: far-future due date → no warning badge.
		await ensureTask(FIXTURE.tasks.normal, {
			priority: 'normal',
			dueDate: isoDate(60),
			column: columnIdByTitle['To Do'],
			columnOrder: 2,
		})

		console.log(`[seed] fixtures ready: project ${projectId}, ${Object.keys(columnIdByTitle).length} columns, label=${labelId ?? 'n/a'}`)
		return true
	} finally {
		await ctx.dispose()
	}
}
