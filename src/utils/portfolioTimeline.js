/**
 * Pure layout for the portfolio timeline (portfolio-status-overview,
 * section 3): one summary bar per project on a shared axis, sorted by start
 * date, a project opened into its phases and tasks, and dependency lines
 * between task bars of any project when both ends are drawn.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { BAR_HEIGHT, parseDay, PX_PER_DAY, ROW_GAP, statusColor, toScheduled } from './timelineHelpers.js'

/**
 * Projects by summary start date, then title; projects without dates last.
 *
 * @param {Array<object>} projects Projects from GET /api/timeline.
 * @return {Array<object>}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
export function sortBySpan(projects = []) {
	const start = (project) => parseDay(project?.spanStart || project?.spanEnd)
	return [...(projects || [])].sort((a, b) => {
		const one = start(a)
		const two = start(b)
		if (one === null || two === null) {
			return (one === null) - (two === null) || byTitle(a, b)
		}
		return one - two || byTitle(a, b)
	})
}

/**
 * Two projects in title order.
 *
 * @param {object} a One project.
 * @param {object} b The other.
 * @return {number}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
function byTitle(a, b) {
	return String(a?.title ?? '').localeCompare(String(b?.title ?? ''))
}

/**
 * The rows of one project: its summary row, and when open its dated phases and tasks.
 *
 * @param {object} project The project.
 * @param {boolean} open Whether the project is opened.
 * @return {Array<object>} Rows with kind, id, projectId, title, status, startDay, endDay.
 */
function projectRows(project, open) {
	const span = scheduledSpan(project.spanStart, project.spanEnd)
	const rows = [rowOf({ kind: 'project', projectId: project.id }, { ...project, startDay: span?.startDay ?? null, endDay: span?.endDay ?? null })]
	if (!open) {
		return rows
	}
	return [...rows, ...phaseRows(project), ...toScheduled(project.tasks || []).map((task) => rowOf({ kind: 'task', projectId: project.id }, task))]
}

/**
 * The dated phases of a project as rows.
 *
 * @param {object} project The project.
 * @return {Array<object>}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
function phaseRows(project) {
	const rows = []
	for (const phase of project.phases || []) {
		const span = scheduledSpan(phase.startDate, phase.endDate)
		if (span) {
			rows.push(rowOf({ kind: 'phase', projectId: project.id }, { ...phase, startDay: span.startDay, endDay: span.endDay }))
		}
	}
	return rows
}

/**
 * The days a start and end date cover, either standing in for the other, or null when neither is a date.
 *
 * @param {string|undefined} startDate The start.
 * @param {string|undefined} dueDate The end.
 * @return {{startDay: number, endDay: number}|null}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
function scheduledSpan(startDate, dueDate) {
	return toScheduled([{ startDate, dueDate }])[0] ?? null
}

/**
 * One timeline row of a project, phase or task.
 *
 * @param {{kind: string, projectId: string}} where The row kind and its project.
 * @param {object} item The item, with startDay and endDay.
 * @return {object} Row with kind, id, projectId, title, status, startDay, endDay.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
function rowOf(where, item) {
	return { kind: where.kind, id: item.id, projectId: where.projectId, title: item.title || '', status: item.status || '', startDay: item.startDay, endDay: item.endDay }
}

/**
 * Lay out the portfolio timeline.
 *
 * @param {Array<object>} projects Projects from GET /api/timeline.
 * @param {Array<string>} openIds The ids of the opened projects.
 * @param {Array<object>} dependencies Stored edges ({id, blocker, blocked}).
 * @param {number} pxPerDay Pixels per day for the active zoom.
 * @return {{rows: Array<object>, edgeLines: Array<object>, minDay: number, dayCount: number, chartWidth: number, barsHeight: number, dated: boolean}}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
export function buildPortfolioLayout(projects, openIds, dependencies, pxPerDay = PX_PER_DAY.week) {
	const open = new Set(openIds || [])
	const rows = sortBySpan(projects).flatMap((project) => projectRows(project, open.has(project.id)))
	const span = daySpan(rows)
	const placed = rows.map((row, index) => placeRow(row, { index, minDay: span.minDay, pxPerDay }))
	return {
		rows: placed,
		edgeLines: edgeLinesBetween(placed, dependencies),
		minDay: span.minDay,
		dayCount: span.dayCount,
		chartWidth: span.dayCount * pxPerDay,
		barsHeight: Math.max(1, placed.length) * (BAR_HEIGHT + ROW_GAP) + ROW_GAP,
		dated: span.dated,
	}
}

/**
 * The first day and the number of days the dated rows cover.
 *
 * @param {Array<object>} rows Rows with startDay and endDay, null when undated.
 * @return {{minDay: number, dayCount: number, dated: boolean}}
 */
function daySpan(rows) {
	const dated = rows.filter((row) => row.startDay !== null)
	if (!dated.length) {
		return { minDay: 0, dayCount: 1, dated: false }
	}
	const minDay = Math.min(...dated.map((row) => row.startDay))
	const maxDay = Math.max(...dated.map((row) => row.endDay))
	return { minDay, dayCount: Math.max(1, maxDay - minDay + 1), dated: true }
}

/**
 * One row with its bar position; an undated row gets no bar.
 *
 * @param {object} row The row.
 * @param {{index: number, minDay: number, pxPerDay: number}} at Its row index, the axis start and the zoom.
 * @return {object}
 */
function placeRow(row, at) {
	const dated = row.startDay !== null
	return {
		...row,
		dated,
		left: dated ? (row.startDay - at.minDay) * at.pxPerDay : 0,
		width: dated ? Math.max(at.pxPerDay, (row.endDay - row.startDay + 1) * at.pxPerDay) : 0,
		top: ROW_GAP + at.index * (BAR_HEIGHT + ROW_GAP),
		color: row.kind === 'task' ? statusColor(row.status) : '',
	}
}

/**
 * Dependency lines between task bars, only when both ends are drawn.
 *
 * @param {Array<object>} placed The placed rows.
 * @param {Array<object>} dependencies Stored edges ({id, blocker, blocked}).
 * @return {Array<object>}
 */
function edgeLinesBetween(placed, dependencies) {
	const taskBars = new Map(placed.filter((row) => row.kind === 'task').map((row) => [row.id, row]))
	const edgeLines = []
	for (const [index, edge] of (dependencies || []).entries()) {
		const from = taskBars.get(edge.blocker)
		const to = taskBars.get(edge.blocked)
		if (from && to) {
			edgeLines.push({ key: edgeKey(edge, index), x1: from.left + from.width, y1: from.top + BAR_HEIGHT / 2, x2: to.left, y2: to.top + BAR_HEIGHT / 2 })
		}
	}
	return edgeLines
}

/**
 * The key of a dependency line: the edge id, or its position when it has none.
 *
 * @param {object} edge The edge.
 * @param {number} index Its position in the list.
 * @return {string}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
function edgeKey(edge, index) {
	return edge.id || `edge-${index}`
}
