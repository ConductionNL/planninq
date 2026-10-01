// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Pure helpers for the Flow tab (portfolio-flow-reports section 2): the
 * stacked bands of the cumulative flow diagram, its table rows, and the
 * scatter of finished tasks. No DOM; the views draw the SVG.
 *
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */

/**
 * One band per column in board order: for every day the band's lower and
 * upper edge, stacked with the first column at the top of the pile.
 *
 * @param {Array<{id: string, title: string}>} columns Columns in board order
 * @param {Array<{date: string, counts: object}>} days Days from the flow endpoint
 * @return {Array<{id: string, title: string, points: Array<{date: string, low: number, high: number}>}>}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function stackBands(columns, days) {
	const list = columns || []
	const bands = list.map((column) => ({ id: column.id, title: column.title, points: [] }))
	for (const day of days || []) {
		let base = 0
		// Last column at the bottom, so "Done" grows from the axis up.
		for (let i = list.length - 1; i >= 0; i--) {
			const value = Number((day.counts || {})[list[i].id] || 0)
			bands[i].points.push({ date: day.date, low: base, high: base + value })
			base += value
		}
	}
	return bands
}

/**
 * The highest stack of any day, at least 1 so a scale never divides by 0.
 *
 * @param {Array<{counts: object}>} days Days from the flow endpoint
 * @return {number}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function stackMax(days) {
	return Math.max(1, ...(days || []).map((day) => Object.values(day.counts || {}).reduce((sum, n) => sum + Number(n || 0), 0)))
}

/**
 * An SVG path for one band: along the upper edge, back along the lower.
 *
 * @param {Array<{low: number, high: number}>} points The band's points
 * @param {number} width Chart width
 * @param {number} height Chart height
 * @param {number} max The highest stack
 * @return {string}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function bandPath(points, width, height, max) {
	if (!points || points.length === 0) {
		return ''
	}
	const step = points.length > 1 ? width / (points.length - 1) : 0
	const y = (v) => Math.round((height - (v / max) * height) * 10) / 10
	const x = (i) => Math.round(i * step * 10) / 10
	const top = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(p.high)}`)
	const bottom = points.map((p, i) => `L${x(i)},${y(p.low)}`).reverse()
	return [...top, ...bottom, 'Z'].join(' ')
}

/**
 * The table view: one row per day, one cell per column in board order.
 *
 * @param {Array<{id: string}>} columns Columns in board order
 * @param {Array<{date: string, counts: object}>} days Days
 * @return {Array<{date: string, cells: number[]}>}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function flowTable(columns, days) {
	return (days || []).map((day) => ({
		date: day.date,
		cells: (columns || []).map((column) => Number((day.counts || {})[column.id] || 0)),
	}))
}

/**
 * Average and 85th percentile (nearest rank) of a list of day counts, the
 * same rule the server uses, for recalculating a filtered portfolio.
 *
 * @param {number[]} values Days
 * @return {{average: number, p85: number}}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
 */
export function averageAndP85(values) {
	const list = (values || []).map(Number).sort((a, b) => a - b)
	if (list.length === 0) {
		return { average: 0, p85: 0 }
	}
	const round = (v) => Math.round(v * 100) / 100
	const index = Math.max(0, Math.ceil(0.85 * list.length) - 1)
	return { average: round(list.reduce((s, v) => s + v, 0) / list.length), p85: round(list[index]) }
}

/**
 * Finished tasks as scatter points: x by finish time across the period,
 * y by cycle time (0 at the bottom).
 *
 * @param {Array<{id: string, finishedAt: string, cycleDays: number}>} finished Finished tasks
 * @param {number} width Chart width
 * @param {number} height Chart height
 * @return {Array<{id: string, x: number, y: number}>}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function scatterPoints(finished, width, height) {
	const list = finished || []
	if (list.length === 0) {
		return []
	}
	const times = list.map((row) => Date.parse(row.finishedAt))
	const first = Math.min(...times)
	const span = Math.max(1, Math.max(...times) - first)
	const top = Math.max(1, ...list.map((row) => Number(row.cycleDays)))
	const round = (v) => Math.round(v * 10) / 10
	return list.map((row, i) => ({
		id: row.id,
		x: round(list.length === 1 ? width / 2 : ((times[i] - first) / span) * width),
		y: round(height - (Number(row.cycleDays) / top) * height),
	}))
}

/** The period choices of the flow pages, in days. */
export const FLOW_PERIOD_DAYS = [14, 30, 90, 180]

/**
 * The period options with their labels.
 *
 * @param {(app: string, text: string, vars: object) => string} t The translator
 * @return {Array<{id: number, days: number, label: string}>}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function flowPeriods(t) {
	return FLOW_PERIOD_DAYS.map((days) => ({ id: days, days, label: t('planninq', 'Last {days} days', { days }) }))
}

/**
 * The from and to dates (YYYY-MM-DD, UTC) of the last `days` days up to today.
 *
 * @param {number} days Days in the period, today included
 * @param {Date} today Today
 * @return {{from: string, to: string}}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.1
 */
export function periodWindow(days, today) {
	const end = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()))
	const start = new Date(end.getTime() - (days - 1) * 86400000)
	const iso = (d) => d.toISOString().slice(0, 10)
	return { from: iso(start), to: iso(end) }
}

/**
 * Several projects' flows added up: columns by title in the first project's
 * order (projects have their own column ids), counts summed per day, and
 * the finished tasks pooled for one average and 85th percentile.
 *
 * @param {Array<object>} projects Per-project flows from the portfolio endpoint
 * @return {{columns: Array, days: Array, finished: Array, summary: object, withoutHistory: number}}
 * @spec openspec/changes/portfolio-flow-reports/tasks.md#task-2.2
 */
export function combineProjectFlows(projects) {
	const titles = []
	const byDate = new Map()
	let withoutHistory = 0
	const finished = []
	for (const project of projects || []) {
		const titleOf = new Map((project.columns || []).map((column) => [column.id, column.title]))
		for (const column of project.columns || []) {
			if (!titles.includes(column.title)) {
				titles.push(column.title)
			}
		}
		for (const day of project.days || []) {
			const counts = byDate.get(day.date) || {}
			for (const [id, value] of Object.entries(day.counts || {})) {
				const title = titleOf.get(id) ?? id
				counts[title] = (counts[title] || 0) + Number(value || 0)
			}
			byDate.set(day.date, counts)
		}
		withoutHistory += Number(project.withoutHistory || 0)
		finished.push(...(project.finished || []))
	}
	const slowest = [...finished].sort((a, b) => b.cycleDays - a.cycleDays).slice(0, 10)
	return {
		columns: titles.map((title) => ({ id: title, title })),
		days: [...byDate.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([date, counts]) => ({ date, counts })),
		finished,
		summary: {
			finished: finished.length,
			estimated: finished.filter((row) => row.estimated).length,
			lead: averageAndP85(finished.map((row) => row.leadDays)),
			cycle: averageAndP85(finished.map((row) => row.cycleDays)),
			slowest,
		},
		withoutHistory,
	}
}
