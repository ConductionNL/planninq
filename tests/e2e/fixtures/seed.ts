/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e fixture seeder.
 *
 * The four board/collaboration/label/reminder specs used to self-`test.skip()`
 * whenever the environment carried no pre-existing planix data — so on every
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
 * `tests/integration/planix.postman_collection.json`. Basic-auth API requests
 * bypass Nextcloud's session-cookie CSRF check, so we do not need the
 * `requesttoken` that the browser storage-state cookie jar lacks.
 *
 * Idempotency: every create is check-by-title-first / reuse-if-present, so
 * repeated runs against a persistent dev container never accumulate duplicate
 * fixture projects, columns, tasks or labels.
 */

import { request, type APIRequestContext } from '@playwright/test'

const REGISTER = 'planix'

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
 * @param baseURL Nextcloud base URL (e.g. http://localhost:8080)
 * @param opts    Optional admin credentials (default admin/admin)
 * @return true when seeding completed, false when planix/OpenRegister is not
 *         installed in this environment (specs then take their legitimate
 *         "app not installed" skip path).
 */
export async function seedFixtures(baseURL: string, opts: SeedOptions = {}): Promise<boolean> {
	const username = opts.username ?? process.env.NC_ADMIN_USER ?? 'admin'
	const password = opts.password ?? process.env.NC_ADMIN_PASS ?? 'admin'

	const ctx: APIRequestContext = await request.newContext({
		baseURL,
		httpCredentials: { username, password },
		extraHTTPHeaders: {
			'Content-Type': 'application/json',
			// OCS marker keeps NC from redirecting API calls to the login form.
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
		},
	})

	try {
		const objectsUrl = (schema: string) =>
			`/index.php/apps/openregister/api/objects/${REGISTER}/${schema}`

		// Probe: if the planix register/schema is absent, OR answers 4xx/5xx and
		// we bail so specs take the honest "app not installed" skip.
		const probe = await ctx.get(objectsUrl('project'), { failOnStatusCode: false })
		if (probe.status() >= 400) {
			// eslint-disable-next-line no-console
			console.log(`[seed] planix register not reachable (status ${probe.status()}); skipping fixture seed`)
			return false
		}

		/**
		 * GET a schema collection as a plain array (OR wraps results in { results }).
		 *
		 * @param schema OpenRegister schema slug
		 * @param query  optional query string (e.g. ?project=...)
		 * @return the collection rows
		 */
		const list = async (schema: string, query = ''): Promise<OrObject[]> => {
			const res = await ctx.get(objectsUrl(schema) + query, { failOnStatusCode: false })
			if (!res.ok()) {
				return []
			}
			const body = await res.json().catch(() => ({}))
			const rows = Array.isArray(body) ? body : (body.results ?? body.data ?? [])
			return Array.isArray(rows) ? rows : []
		}

		/**
		 * Find an existing object by exact title.
		 *
		 * @param rows  collection rows to search
		 * @param title exact title to match
		 * @return the matching object, or undefined
		 */
		const findByTitle = (rows: OrObject[], title: string): OrObject | undefined =>
			rows.find((r) => r.title === title)

		// ── Project (via the planix policy-enforcing create proxy) ──────────
		let project = findByTitle(await list('project'), FIXTURE.projectTitle)
		if (!project) {
			const res = await ctx.post('/index.php/apps/planix/api/projects', {
				data: {
					title: FIXTURE.projectTitle,
					description: 'Seeded by Playwright global-setup for real e2e assertions.',
					color: '#4376FC',
					icon: '🧪',
				},
				failOnStatusCode: false,
			})
			if (!res.ok()) {
				// eslint-disable-next-line no-console
				console.log(`[seed] project create failed (status ${res.status()}); skipping fixture seed`)
				return false
			}
			project = (await res.json()) as OrObject
		}
		const projectId = objId(project)
		if (!projectId) {
			// eslint-disable-next-line no-console
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
		const existingColumns = await list('column', `?project=${encodeURIComponent(projectId)}`)
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
				data: { title: FIXTURE.labelTitle, color: '#E4572E', description: 'Seeded label' },
				failOnStatusCode: false,
			})
			if (res.ok()) {
				label = (await res.json()) as OrObject
			}
		}
		const labelId = objId(label)

		// ── Tasks (assignee + priority + due-date spread) ───────────────────
		const existingTasks = await list('task', `?project=${encodeURIComponent(projectId)}`)
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

		// eslint-disable-next-line no-console
		console.log(`[seed] fixtures ready: project ${projectId}, ${Object.keys(columnIdByTitle).length} columns, label=${labelId ?? 'n/a'}`)
		return true
	} finally {
		await ctx.dispose()
	}
}
