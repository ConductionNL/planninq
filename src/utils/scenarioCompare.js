/**
 * Helpers for comparing timetable scenarios (timetabling-generator, section 7).
 *
 * The metrics are the ones the scorer stored on each scenario (design decision 5),
 * so a generated and an imported scenario are read the same way.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

/**
 * The lines of the comparison, in order, and which way is better. `null` marks no best value.
 */
export const METRIC_LINES = [
	{ key: 'placed', better: 'high' },
	{ key: 'unplaced', better: 'low' },
	{ key: 'clashes', better: 'low' },
	{ key: 'hardWishesBroken', better: 'low' },
	{ key: 'softWishesBroken', better: 'low' },
	{ key: 'softPenalty', better: 'low' },
	{ key: 'teacherGaps', better: 'low' },
	{ key: 'teacherGapsWorst', better: 'low' },
	{ key: 'lessonsPerDayWorst', better: 'low' },
	{ key: 'roomUse', better: null },
]

/**
 * The most scenarios compared at once.
 */
export const MAX_COMPARED = 3

/**
 * One row per metric line with each scenario's value and the positions holding the best value.
 * A missing value is null and never the best; when every value is equal no value is marked.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
 * @param {object[]} scenarios Two or three scenarios.
 * @return {Array<{key: string, values: Array<number|null>, best: number[]}>} The rows.
 */
export function compareRows(scenarios) {
	return METRIC_LINES.map((line) => {
		const values = scenarios.map((scenario) => {
			const value = scenario?.metrics?.[line.key]
			return typeof value === 'number' ? value : null
		})
		return { key: line.key, values, best: bestPositions(values, line.better) }
	})
}

/**
 * The positions of the best value, or none when there is no direction or no difference.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
 * @param {Array<number|null>} values The values.
 * @param {string|null} better `low`, `high` or null.
 * @return {number[]} The positions.
 */
export function bestPositions(values, better) {
	const known = values.filter((value) => value !== null)
	if (better === null || known.length < 2 || new Set(known).size === 1) {
		return []
	}
	const target = better === 'low' ? Math.min(...known) : Math.max(...known)
	return values.flatMap((value, index) => (value === target ? [index] : []))
}

/**
 * The lessons whose period or room differs between the scenarios, in lesson order; a lesson a
 * scenario did not place shows null in that scenario's cell.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-7.1
 * @param {object[]} scenarios Two or three scenarios.
 * @return {Array<{lesson: string, cells: Array<{period: string, room: string}|null>}>} The differing lessons.
 */
export function lessonDiff(scenarios) {
	const maps = scenarios.map((scenario) => new Map((scenario?.placements ?? []).map((row) => [row.lesson, { period: row.period, room: row.room }])))
	const lessons = [...new Set(maps.flatMap((map) => [...map.keys()]))].sort()
	return lessons
		.map((lesson) => ({ lesson, cells: maps.map((map) => map.get(lesson) ?? null) }))
		.filter((row) => new Set(row.cells.map((cell) => (cell === null ? '' : `${cell.period} ${cell.room}`))).size > 1)
}
