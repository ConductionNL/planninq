/**
 * Display names of Nextcloud users, for pages that list people by name.
 *
 * Reads Nextcloud's own autocomplete endpoint, which every signed-in user may
 * call, and falls back to the user id when a name does not resolve.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { generateOcsUrl } from '@nextcloud/router'

const cache = new Map()

/**
 * The display name of one user.
 *
 * @param {string} uid The user id.
 * @return {Promise<string>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
export async function displayName(uid) {
	if (!uid) {
		return ''
	}
	if (!cache.has(uid)) {
		cache.set(uid, (async () => {
			try {
				const url = generateOcsUrl('/core/autocomplete/get') + '?' + new URLSearchParams({ search: uid, itemType: '', itemId: '', 'shareTypes[]': '0', limit: '10', format: 'json' })
				const response = await fetch(url, { headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } })
				const body = response.ok ? await response.json() : null
				const match = (body?.ocs?.data || []).find((user) => user?.id === uid)
				return match?.label || uid
			} catch {
				return uid
			}
		})())
	}
	return cache.get(uid)
}

/**
 * Display names for a list of users, keyed by user id.
 *
 * @param {Array<string>} uids The user ids.
 * @return {Promise<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
export async function displayNames(uids = []) {
	const names = await Promise.all((uids || []).map((uid) => displayName(uid)))
	return Object.fromEntries((uids || []).map((uid, i) => [uid, names[i]]))
}
