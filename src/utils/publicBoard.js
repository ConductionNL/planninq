/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public board's data (projects-public-share design decision 2): what
 * OpenRegister's access-link endpoint answers for a project's view, read into
 * columns and read-only cards. The endpoint already dropped the people and the
 * free text (decision 3); the card keeps only title, status, due date and labels.
 *
 * @spec openspec/changes/archive/2026-11-25-projects-public-share/tasks.md#task-2.1
 */

import { generateUrl } from '@nextcloud/router'

/** The most objects OpenRegister serves through one view link. */
export const PUBLIC_BOARD_LIMIT = 200

/** The header OpenRegister reads a link password from. */
export const PASSWORD_HEADER = 'X-OpenRegister-Link-Password'

/**
 * The anchor of the link this page was opened with, from `/apps/planninq/public/{anchor}`.
 *
 * @param {string} pathname The page's path.
 * @return {string|null} The anchor, or null when the path names none.
 * @spec openspec/changes/archive/2026-11-25-projects-public-share/tasks.md#task-2.1
 */
export function anchorFromPath(pathname) {
	const match = /\/apps\/planninq\/public\/([A-Za-z0-9]+)\/?$/.exec(String(pathname ?? ''))
	return match ? match[1] : null
}

/**
 * The URL that serves the link's data.
 *
 * @param {string} anchor The link's anchor.
 * @return {string} The URL.
 * @spec openspec/changes/archive/2026-11-25-projects-public-share/tasks.md#task-2.1
 */
export function linkDataUrl(anchor) {
	return generateUrl('/apps/openregister/api/public/links/{anchor}', { anchor })
}

/**
 * Whether a served object is a task (it names a column, an order in one or a priority).
 *
 * @param {object} object A served object.
 * @return {boolean}
 */
function isTask(object) {
	return ['column', 'columnOrder', 'priority', 'issueType', 'dueDate'].some((key) => key in object)
}

/**
 * A served task as a read-only card: title, status, due date and labels only.
 *
 * @param {object} task A served task.
 * @return {{id: string, title: string, status: string, dueDate: string, labels: string[]}}
 */
function toCard(task) {
	return {
		id: String(task.id ?? ''),
		title: String(task.title ?? ''),
		status: String(task.status ?? ''),
		dueDate: String(task.dueDate ?? ''),
		labels: Array.isArray(task.labels) ? task.labels.filter((label) => typeof label === 'string') : [],
	}
}

/**
 * The board an access-link answer holds: its columns in order, each with its
 * cards in board order, and the cards whose column was not served.
 *
 * @param {{results?: object[], total?: number}} body The endpoint's answer.
 * @return {{columns: Array<{id: string, title: string, cards: object[]}>, loose: object[], capped: boolean}}
 * @spec openspec/changes/archive/2026-11-25-projects-public-share/tasks.md#task-2.1
 */
export function boardFromLink(body) {
	const results = Array.isArray(body?.results) ? body.results.filter((row) => row && typeof row === 'object') : []
	const tasks = results.filter(isTask)
	const columns = results
		.filter((row) => !isTask(row))
		.slice()
		.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0))
		.map((column) => ({ id: String(column.id ?? ''), title: String(column.title ?? ''), cards: [] }))

	const byId = new Map(columns.map((column) => [column.id, column]))
	const loose = []
	const ordered = tasks.slice().sort((a, b) => (Number(a.columnOrder) || 0) - (Number(b.columnOrder) || 0))
	for (const task of ordered) {
		const column = byId.get(String(task.column ?? ''))
		if (column) {
			column.cards.push(toCard(task))
		} else {
			loose.push(toCard(task))
		}
	}

	return { columns, loose, capped: results.length >= PUBLIC_BOARD_LIMIT }
}

/**
 * What the page shows for a failed read: a password prompt, a wrong password, or a dead link.
 *
 * @param {number|undefined} status The HTTP status.
 * @param {boolean} triedPassword Whether this read sent a password.
 * @return {'password'|'wrongPassword'|'gone'}
 * @spec openspec/changes/archive/2026-11-25-projects-public-share/tasks.md#task-2.1
 */
export function failureState(status, triedPassword) {
	if (status === 401) {
		return triedPassword ? 'wrongPassword' : 'password'
	}
	return 'gone'
}
