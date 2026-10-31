/**
 * Pure helpers for the case bridge (integration-case-bridge): the query of the
 * "New project" link on a case or client page, the New project dialog's
 * prefill from that query, and when "Hand over to case" is offered.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-2.1
 */
import { isCaseHost } from '../integrations/projectScope.js'

/**
 * The query of the "New project" link from a host page.
 *
 * @param {string} objectId The host object.
 * @param {{register?: string, schema?: string}|undefined} host The host's register and schema.
 * @param {string} title The case title, when known.
 * @return {{[key: string]: string}}
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-1.2
 */
export function newProjectQuery(objectId, host, title) {
	if (!isCaseHost(host)) {
		return { new: '1', client: objectId }
	}
	const query = { new: '1', case: objectId }
	if (title) {
		query.title = title
	}
	return query
}

/**
 * The New project dialog's prefill from the route query, or null when the
 * query does not ask for the dialog.
 *
 * @param {{[key: string]: string}|undefined} query The route query.
 * @return {{caseReference?: string, client?: string, title?: string}|null}
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-2.1
 */
export function creationPrefill(query) {
	if (String(query?.new || '') !== '1') {
		return null
	}
	const prefill = {}
	if (query.case) {
		prefill.caseReference = String(query.case)
	}
	if (query.client) {
		prefill.client = String(query.client)
	}
	if (query.title) {
		prefill.title = String(query.title)
	}
	return prefill
}

/**
 * Whether "Hand over to case" is offered: the project has a case link, the
 * case app is installed, and the user is the owner or an admin. The server
 * applies the same rules.
 *
 * @param {object|null} project The project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The current user.
 * @param {boolean} caseAppAvailable What the server said about the case app.
 * @return {boolean}
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
 */
export function handoverOffered(project, user, caseAppAvailable) {
	if (!user || !caseAppAvailable || !project?.caseReference) {
		return false
	}
	return user.isAdmin === true || project.owner === user.uid
}
