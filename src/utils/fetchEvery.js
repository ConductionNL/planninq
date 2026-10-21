/**
 * Reading a whole OpenRegister collection, page by page.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/projects.md
 */

/**
 * Largest page OpenRegister will return. Asking for more is silently capped.
 *
 * The underscore prefix is required: OpenRegister reads an unprefixed `limit`
 * as a property filter, which matches nothing and returns an empty collection.
 */
const MAX_PAGE = 1000

/**
 * Read an ENTIRE collection, following pages until it is exhausted.
 *
 * OpenRegister pages at 20 by default and caps a page at 1000, and this store
 * reads whole collections for three purposes that are all wrong when truncated:
 * rendering a board, COUNTING, and cascade-DELETING. A 21-task project rendered
 * 20 tasks and reported "20" as its total; worse, deleting it removed one page
 * of tasks and left the rest as orphans, while every call reported success.
 *
 * Raising the page size alone does not fix it — the largest project on our own
 * instance holds 2,015 tasks, so a single `_limit=1000` read still silently
 * dropped half of it. Only following `_page` to exhaustion is correct.
 *
 * @param {object} objectStore the shared OpenRegister object store
 * @param {string} schema      schema slug to read
 * @param {object} filters     property filters (no control params)
 * @return {Promise<Array>} every row in the collection
 *
 * @spec openspec/specs/projects.md
 */
export async function fetchEvery(objectStore, schema, filters = {}) {
	const out = []
	const seen = new Set()
	for (let page = 1; ; page++) {
		const batch = await objectStore.fetchCollection(schema, { ...filters, _limit: MAX_PAGE, _page: page })
		const rows = Array.isArray(batch) ? batch : []

		// Keep only rows we have not already collected. This is also the
		// termination guard: a server that ignored `_page` would hand back the
		// same full batch forever, and a `rows.length < MAX_PAGE` check alone
		// would loop until the tab died. Zero NEW rows means there is no more
		// collection to read, whatever the server thinks it is doing.
		let added = 0
		for (const row of rows) {
			const id = row?.id ?? row?.uuid ?? row?.['@self']?.id
			if (id !== undefined && seen.has(id)) {
				continue
			}
			if (id !== undefined) {
				seen.add(id)
			}
			out.push(row)
			added++
		}

		if (added === 0 || rows.length < MAX_PAGE) {
			break
		}
	}
	return out
}
