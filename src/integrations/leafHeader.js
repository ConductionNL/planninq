// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// What the projects leaf reports to a host that draws its card (the library
// card, nextcloud-vue integration-leaf-card): the count beside the title, the
// actions in the title row, the budget as a note and the empty state. Kept out
// of the SFC so the node test runner can cover it.

/**
 * Build the header the leaf hands to the host's `onHeader`.
 *
 * The count and the empty state wait until the list has settled, and stay away
 * after a failed read: a zero that means "could not read" is the defect this
 * leaf exists to remove.
 *
 * @param {object} state The leaf's state.
 * @param {boolean} state.loading Whether the list is still loading.
 * @param {string} state.error The read error, or ''.
 * @param {number} state.total How many projects there are.
 * @param {string} state.emptyLabel The empty state, phrased for the surface.
 * @param {string} state.note The budget line, or ''.
 * @param {Array<{label: string, run: () => void}>} state.actions The title-row actions.
 *
 * @return {{actions: Array, note: string, count?: number, empty?: {name: string}}} The header.
 *
 * @spec openspec/changes/projects-leaf-library-card/specs/projects-leaf-library-card/spec.md#requirement-the-projects-leaf-takes-the-library-card
 */
export function leafHeader({ loading, error, total, emptyLabel, note, actions }) {
	const header = { actions, note: note || '' }
	if (loading === true || error !== '') {
		return header
	}
	header.count = total
	if (total === 0) {
		header.empty = { name: emptyLabel }
	}
	return header
}

/**
 * Build the summary the leaf hands to a "linked" card's `onSummary`
 * (nextcloud-vue linked-integrations-card): how many projects there are, the
 * noun for the "nothing linked" line, a row per listed project and the
 * actions. Nothing is reported while loading or after a failed read, so the
 * card never says "nothing linked" when it could not tell.
 *
 * @param {object} state The leaf's state.
 * @param {boolean} state.loading Whether the list is still loading.
 * @param {string} state.error The read error, or ''.
 * @param {number} state.total How many projects there are.
 * @param {string} state.noun The plural noun, lower case ("projects").
 * @param {Array<{label: string, href?: string}>} state.rows A row per listed project.
 * @param {Array<{label: string, run: () => void}>} state.actions The actions.
 *
 * @return {{count: number, noun: string, rows: Array, actions: Array}|null} The summary, or null.
 *
 * @spec openspec/changes/projects-leaf-summary/specs/projects-leaf-summary/spec.md#requirement-the-projects-leaf-reports-a-summary-to-a-linked-card
 */
export function leafSummary({ loading, error, total, noun, rows, actions }) {
	if (loading === true || error !== '') {
		return null
	}
	return { count: total, noun, rows: total > 0 ? rows : [], actions }
}
