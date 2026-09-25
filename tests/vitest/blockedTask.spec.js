/**
 * Vitest unit tests for the manual blocked-state helpers.
 *
 * Covers `isTaskBlocked` (the person-set `status === 'blocked'` signal, which is
 * deliberately NOT the dependency-derived `isBlocked`), `truncateBlockedReason`
 * (short reason, long reason, no reason) and `filterTasksByBlocked` (blocked
 * only, unblocked only, and composition with the label filter).
 *
 * @spec openspec/changes/blocked-task-reason-and-filter/specs/kanban-board/spec.md
 */
import { describe, expect, it } from 'vitest'
import { filterTasksByBlocked, isTaskBlocked, truncateBlockedReason } from '../../src/utils/taskHelpers.js'
import { filterTasksByLabel } from '../../src/utils/labelHelpers.js'

describe('isTaskBlocked', () => {
	it('is true when the status is blocked', () => {
		expect(isTaskBlocked({ id: 'A', status: 'blocked' })).toBe(true)
	})

	it('is false when the status is open', () => {
		expect(isTaskBlocked({ id: 'A', status: 'open' })).toBe(false)
	})

	it('is false when the status is missing', () => {
		expect(isTaskBlocked({ id: 'A' })).toBe(false)
	})

	it('is false for a null task', () => {
		expect(isTaskBlocked(null)).toBe(false)
	})

	it('does not treat a leftover reason on an unblocked task as blocked', () => {
		expect(isTaskBlocked({ id: 'A', status: 'done', blockedReason: 'waiting on design' })).toBe(false)
	})
})

describe('truncateBlockedReason', () => {
	it('returns a short reason unchanged', () => {
		expect(truncateBlockedReason('waiting on design')).toEqual({
			text: 'waiting on design',
			full: 'waiting on design',
			truncated: false,
		})
	})

	it('shortens a long reason and keeps the full text', () => {
		const long = 'waiting on the design team to finish the component library review before this can start'
		const line = truncateBlockedReason(long, 20)
		expect(line.truncated).toBe(true)
		expect(line.text).toBe('waiting on the desig…')
		expect(line.full).toBe(long)
	})

	it('returns an empty line when there is no reason', () => {
		expect(truncateBlockedReason(undefined)).toEqual({ text: '', full: '', truncated: false })
		expect(truncateBlockedReason('   ')).toEqual({ text: '', full: '', truncated: false })
	})
})

describe('filterTasksByBlocked', () => {
	const tasks = [
		{ id: 'A', status: 'blocked', labels: ['bug'] },
		{ id: 'B', status: 'open', labels: ['bug'] },
		{ id: 'C', status: 'blocked', labels: ['feature'] },
	]

	it('keeps only blocked tasks when the filter is on', () => {
		expect(filterTasksByBlocked(tasks, true).map((t) => t.id)).toEqual(['A', 'C'])
	})

	it('returns every task when the filter is off', () => {
		expect(filterTasksByBlocked(tasks, false).map((t) => t.id)).toEqual(['A', 'B', 'C'])
	})

	it('composes with the label filter', () => {
		const composed = filterTasksByBlocked(filterTasksByLabel(tasks, 'bug'), true)
		expect(composed.map((t) => t.id)).toEqual(['A'])
	})

	it('returns an empty list when nothing is blocked', () => {
		expect(filterTasksByBlocked([{ id: 'B', status: 'open' }], true)).toEqual([])
	})
})
