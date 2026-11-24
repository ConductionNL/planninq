/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * The admin's retention category per schema (retention-on-planninq-records
 * task 2.1): reading and saving GET/PUT /api/settings/retention, and the
 * picker options built from OpenRegister's selection list. The HTTP client is
 * passed in, so these stay plain functions.
 *
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */

/**
 * The schemas whose records carry a retention category, in display order.
 */
export const RETENTION_SCHEMAS = ['project', 'task', 'plannedTimeEntry']

/**
 * A pick with every schema present, '' for none.
 *
 * @param {object} [picks] Schema to category, as the server answers it
 * @return {object}
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
export function normalisePicks(picks) {
	const result = {}
	for (const schema of RETENTION_SCHEMAS) {
		const value = picks?.[schema]
		result[schema] = typeof value === 'string' ? value.trim() : ''
	}
	return result
}

/**
 * One option per category, labelled with its description and period.
 *
 * @param {Array<{category: string, description?: string, period?: string}>} categories The selection list's categories
 * @return {Array<{id: string, label: string}>}
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
export function categoryOptions(categories) {
	return (Array.isArray(categories) ? categories : [])
		.filter((row) => typeof row?.category === 'string' && row.category !== '')
		.map((row) => {
			const parts = [row.category]
			if (row.description) {
				parts.push(row.description)
			}
			const label = parts.join(' – ')
			return { id: row.category, label: row.period ? `${label} (${row.period})` : label }
		})
}

/**
 * The categories and the current pick.
 *
 * @param {{get: function(string): Promise<{data: object}>}} http The HTTP client
 * @param {string} url The endpoint
 * @return {Promise<{categories: Array<object>, picks: object}>}
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
export async function loadRetention(http, url) {
	const { data } = await http.get(url)
	return {
		categories: Array.isArray(data?.categories) ? data.categories : [],
		picks: normalisePicks(data?.picks),
	}
}

/**
 * Save the pick; the server's stored pick comes back, or the refusal is thrown.
 *
 * @param {{put: function(string, object): Promise<{data: object}>}} http The HTTP client
 * @param {string} url The endpoint
 * @param {object} picks Schema to category
 * @return {Promise<object>} The stored pick
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
export async function saveRetention(http, url, picks) {
	const { data } = await http.put(url, { picks: normalisePicks(picks) })
	return normalisePicks(data?.picks)
}
