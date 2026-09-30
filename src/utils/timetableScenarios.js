/**
 * Helpers for the timetable scenario page (timetabling-generator, section 5.3).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

/**
 * Whether a run is still going, so the page keeps reading the scenario.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 * @param {object|null} scenario The scenario.
 * @return {boolean} True while queued or running.
 */
export function isRunning(scenario) {
	return ['queued', 'running'].includes(scenario?.status)
}

/**
 * The share of the time budget spent, from 0 to 100.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 * @param {object|null} scenario The scenario.
 * @return {number} The percentage, whole.
 */
export function progressPercent(scenario) {
	if (scenario?.status === 'done' || scenario?.status === 'published') {
		return 100
	}
	const budget = Number(scenario?.metrics?.budgetSeconds ?? 0)
	const spent = Number(scenario?.metrics?.secondsSpent ?? 0)
	if (budget <= 0) {
		return 0
	}
	return Math.min(100, Math.round((spent / budget) * 100))
}

/**
 * A short description of a wish, from the scenario's own input snapshot.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 * @param {object|null} wish The wish as the input keeps it.
 * @return {{appliesTo: string, reference: string, kind: string, periods: string[]}|null} The parts, or null when unknown.
 */
export function wishSummary(wish) {
	if (!wish) {
		return null
	}
	return {
		appliesTo: wish.appliesTo ?? '',
		reference: wish.reference ?? '',
		kind: wish.kind ?? '',
		periods: Array.isArray(wish.periods) ? wish.periods : [],
	}
}

/**
 * The unplaced lessons with the hard wish that blocked each.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 * @param {object|null} scenario The scenario.
 * @return {Array<{lesson: string, wish: object|null, reason: string}>} One row per lesson.
 */
export function unplacedRows(scenario) {
	const wishes = new Map((scenario?.input?.wishes ?? []).map((wish) => [String(wish.id), wish]))
	return (scenario?.unplaced ?? []).map((row) => ({
		lesson: row.lesson,
		wish: wishSummary(row.wish ? wishes.get(String(row.wish)) ?? { reference: String(row.wish) } : null),
		reason: row.reason ?? (row.wish ? 'hardWish' : 'noFreePlace'),
	}))
}

/**
 * The broken soft wishes, heaviest first, with the lessons that break them.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 * @param {object|null} scenario The scenario.
 * @return {Array<{wish: object|null, weight: number, lessons: string[]}>} One row per wish.
 */
export function brokenSoftRows(scenario) {
	const wishes = new Map((scenario?.input?.wishes ?? []).map((wish) => [String(wish.id), wish]))
	return (scenario?.brokenWishes ?? [])
		.filter((row) => row.strength !== 'hard' && row.weight !== null)
		.map((row) => ({ wish: wishSummary(wishes.get(String(row.wish)) ?? { reference: String(row.wish) }), weight: Number(row.weight ?? 1), lessons: row.lessons ?? [] }))
		.sort((a, b) => (b.weight * b.lessons.length) - (a.weight * a.lessons.length))
}
