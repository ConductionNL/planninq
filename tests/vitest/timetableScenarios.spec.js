/**
 * Vitest tests for the timetable scenario page helpers (timetabling-generator, section 5.3).
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 */
import { describe, expect, it } from 'vitest'
import { brokenSoftRows, isRunning, progressPercent, unplacedRows } from '../../src/utils/timetableScenarios.js'

const scenario = {
	status: 'running',
	metrics: { secondsSpent: 150, budgetSeconds: 600 },
	input: {
		wishes: [
			{ id: 'w-klaas', appliesTo: 'teacher', reference: 'klaas', kind: 'unavailable', periods: ['wed-5', 'wed-6'], strength: 'hard' },
			{ id: 'w-noor', appliesTo: 'teacher', reference: 'noor', kind: 'avoid', periods: ['fri-8'], strength: 'soft', weight: 1 },
			{ id: 'w-3a', appliesTo: 'group', reference: '3A', kind: 'noGaps', strength: 'soft', weight: 3 },
		],
	},
	unplaced: [
		{ lesson: '3A:English:3', wish: 'w-klaas', reason: 'hardWishPeriods' },
		{ lesson: '3B:Art:1', wish: null, reason: 'noFreePlace' },
	],
	brokenWishes: [
		{ wish: 'w-noor', strength: 'soft', weight: 1, lessons: ['3B:Maths:1', '3B:Maths:2'] },
		{ wish: 'w-3a', strength: 'soft', weight: 3, lessons: ['3A:English:1'] },
	],
}

describe('the run', () => {
	it('is going while queued or running', () => {
		expect(isRunning(scenario)).toBe(true)
		expect(isRunning({ status: 'queued' })).toBe(true)
		expect(isRunning({ status: 'done' })).toBe(false)
		expect(isRunning(null)).toBe(false)
	})

	it('reports the share of the time budget spent', () => {
		expect(progressPercent(scenario)).toBe(25)
		expect(progressPercent({ status: 'running', metrics: { secondsSpent: 900, budgetSeconds: 600 } })).toBe(100)
		expect(progressPercent({ status: 'done', metrics: {} })).toBe(100)
		expect(progressPercent({ status: 'queued' })).toBe(0)
	})
})

describe('the lists (scenario: a lesson that cannot be placed is listed with its reason)', () => {
	it('names the hard wish that blocked each unplaced lesson', () => {
		expect(unplacedRows(scenario)).toEqual([
			{ lesson: '3A:English:3', wish: { appliesTo: 'teacher', reference: 'klaas', kind: 'unavailable', periods: ['wed-5', 'wed-6'] }, reason: 'hardWishPeriods' },
			{ lesson: '3B:Art:1', wish: null, reason: 'noFreePlace' },
		])
	})

	it('lists broken soft wishes heaviest first with their weight', () => {
		const rows = brokenSoftRows(scenario)
		expect(rows.map((row) => [row.wish.reference, row.weight, row.lessons.length])).toEqual([['3A', 3, 1], ['noor', 1, 2]])
	})

	it('keeps a wish that is no longer in the input readable by its id', () => {
		expect(unplacedRows({ unplaced: [{ lesson: 'x', wish: 'gone' }] })[0].wish.reference).toBe('gone')
	})
})
