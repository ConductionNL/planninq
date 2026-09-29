/**
 * Vitest unit tests for the Microsoft Project import (integration-msproject-import):
 * the file check before upload, the refusal reasons the dialog explains, the
 * order losses are listed in, the import result lines, and the related links
 * an import creates, which are drawn but never block a task.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
import { describe, expect, it } from 'vitest'
import {
	COUNT_KINDS,
	fileRefusal,
	lossList,
	refusalReason,
} from '../../src/utils/msprojectImport.js'
import { deriveBlockedTaskIds, isBlocked, openBlockerIds } from '../../src/utils/taskHelpers.js'
import { buildLayout, PX_PER_DAY, toScheduled } from '../../src/utils/timelineHelpers.js'

describe('fileRefusal (scenario: an mpp file is refused with a hint)', () => {
	it('refuses an .mpp file before it is uploaded', () => {
		expect(fileRefusal({ name: 'planning.mpp', size: 2048 })).toBe('mpp')
		expect(fileRefusal({ name: 'PLANNING.MPP', size: 2048 })).toBe('mpp')
	})

	it('refuses a file over 10 MB and a missing file', () => {
		expect(fileRefusal({ name: 'plan.xml', size: 10 * 1024 * 1024 + 1 })).toBe('tooLarge')
		expect(fileRefusal(null)).toBe('noFile')
	})

	it('accepts an XML plan', () => {
		expect(fileRefusal({ name: 'Renovatie stadhuis.xml', size: 4096 })).toBe('')
	})
})

describe('refusalReason', () => {
	it('reads the reason the server gives', () => {
		expect(refusalReason(422, { reason: 'unsafe' })).toBe('unsafe')
		expect(refusalReason(422, { reason: 'tooManyTasks' })).toBe('tooManyTasks')
		expect(refusalReason(422, { reason: 'mpp' })).toBe('mpp')
	})

	it('names a refused caller and anything else', () => {
		expect(refusalReason(403, { error: 'Only the project owner can import a plan.' })).toBe('forbidden')
		expect(refusalReason(422, { reason: 'somethingNew' })).toBe('other')
		expect(refusalReason(500, {})).toBe('other')
	})
})

describe('lossList (scenario: the owner previews a contractor plan)', () => {
	it('lists the losses in a fixed order and skips empty ones', () => {
		expect(lossList({ lags: 1, resources: 12, phaseLinks: 0, relatedLinks: 3 })).toEqual([
			{ code: 'resources', count: 12 },
			{ code: 'relatedLinks', count: 3 },
			{ code: 'lags', count: 1 },
		])
		expect(lossList(undefined)).toEqual([])
	})

	it('counts every kind the preview names', () => {
		expect(COUNT_KINDS).toEqual(['phases', 'tasks', 'subtasks', 'milestones', 'links'])
	})
})

describe('related links from an import', () => {
	const statusById = { a: 'open', b: 'open', c: 'open' }
	const edges = [
		{ blocker: 'a', blocked: 'b', type: 'relates' },
		{ blocker: 'b', blocked: 'c', type: 'blocks' },
	]

	it('never block their task', () => {
		expect(isBlocked('b', edges, statusById)).toBe(false)
		expect(isBlocked('c', edges, statusById)).toBe(true)
		expect(deriveBlockedTaskIds(edges, statusById)).toEqual(['c'])
		expect(openBlockerIds('b', edges, statusById)).toEqual([])
	})

	it('are drawn on the timeline as related lines, blocking ones as arrows', () => {
		const scheduled = toScheduled([
			{ id: 'a', title: 'A', startDate: '2027-03-01', dueDate: '2027-03-02' },
			{ id: 'b', title: 'B', startDate: '2027-03-03', dueDate: '2027-03-04' },
			{ id: 'c', title: 'C', startDate: '2027-03-05', dueDate: '2027-03-06' },
		])
		const layout = buildLayout(scheduled, edges.map((edge, i) => ({ id: `e${i}`, ...edge })), PX_PER_DAY.day)
		expect(layout.edgeLines.map((line) => [line.key, line.related])).toEqual([['e0', true], ['e1', false]])
	})
})
