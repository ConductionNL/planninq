/**
 * Pure derived-state helpers for the personal timesheet view.
 *
 * Kept free of Vue/DOM so the grouping + totals logic can be unit-tested in a
 * bare node environment (tests/vitest/timesheet.spec.js).
 *
 * SPDX-FileCopyrightText: 2026 Planninq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/time-tracking.md
 */

/**
 * Group time entries by their `date` (YYYY-MM-DD), newest date first, each
 * group carrying a daily total (sum of durations in minutes). Entries within a
 * day preserve input order.
 *
 * @param {Array<{date: string, duration: number}>} entries Time entries.
 * @return {Array<{date: string, entries: Array, total: number}>} Date groups.
 *
 * @spec openspec/specs/time-tracking.md
 */
export function groupEntriesByDate(entries = []) {
	const byDate = new Map()
	for (const entry of entries) {
		const date = entry.date || ''
		if (!byDate.has(date)) {
			byDate.set(date, [])
		}
		byDate.get(date).push(entry)
	}
	return [...byDate.keys()]
		.sort((a, b) => (a < b ? 1 : a > b ? -1 : 0)) // newest date first
		.map((date) => ({
			date,
			entries: byDate.get(date),
			total: sumDuration(byDate.get(date)),
		}))
}

/**
 * Sum the `duration` (minutes) of a list of time entries.
 *
 * @param {Array<{duration: number}>} entries Time entries.
 * @return {number} Total minutes.
 *
 * @spec openspec/specs/time-tracking.md
 */
export function sumDuration(entries = []) {
	return entries.reduce((acc, e) => acc + (Number(e.duration) || 0), 0)
}

/**
 * Keep only entries whose `date` falls within [from, to] inclusive (both
 * YYYY-MM-DD strings; either bound may be null to leave that side open).
 *
 * @param {Array<{date: string}>} entries Time entries.
 * @param {string|null} from Inclusive lower bound (YYYY-MM-DD) or null.
 * @param {string|null} to Inclusive upper bound (YYYY-MM-DD) or null.
 * @return {Array} Filtered entries.
 *
 * @spec openspec/specs/time-tracking.md
 */
export function filterByRange(entries = [], from = null, to = null) {
	return entries.filter((e) => {
		const d = e.date || ''
		if (from && d < from) {
			return false
		}
		if (to && d > to) {
			return false
		}
		return true
	})
}

/**
 * The Monday…Sunday range containing `ref` (default: today), as YYYY-MM-DD
 * strings — the "This week" default filter.
 *
 * @param {Date} [ref] Reference date (defaults to now).
 * @return {{from: string, to: string}} Week bounds.
 *
 * @spec openspec/specs/time-tracking.md
 */
export function currentWeekRange(ref = new Date()) {
	const d = new Date(ref.getFullYear(), ref.getMonth(), ref.getDate())
	const dow = (d.getDay() + 6) % 7 // 0 = Monday
	const monday = new Date(d)
	monday.setDate(d.getDate() - dow)
	const sunday = new Date(monday)
	sunday.setDate(monday.getDate() + 6)
	// Local Y-M-D (avoid toISOString, which converts to UTC and can shift the day).
	const iso = (x) => {
		const y = x.getFullYear()
		const mo = String(x.getMonth() + 1).padStart(2, '0')
		const da = String(x.getDate()).padStart(2, '0')
		return `${y}-${mo}-${da}`
	}
	return { from: iso(monday), to: iso(sunday) }
}
