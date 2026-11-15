/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the timetable scenario helpers: missing metrics, wishes that
 * are not in the input, default reasons and weights.
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-5.3
 */
import { describe, expect, it } from 'vitest'
import {
	brokenHardRows,
	brokenSoftRows,
	canPublishDrafts,
	canTakeCurrentTimetable,
	isRunning,
	progressPercent,
	unplacedRows,
	wishSummary,
} from '../../src/utils/timetableScenarios.js'

describe('progressPercent edges', () => {
	it('is 100 for done and published, 0 without a budget', () => {
		expect(progressPercent({ status: 'done' })).toBe(100)
		expect(progressPercent({ status: 'published' })).toBe(100)
		expect(progressPercent({ status: 'running' })).toBe(0)
		expect(progressPercent(null)).toBe(0)
		expect(progressPercent({ status: 'running', metrics: { budgetSeconds: 0, secondsSpent: 5 } })).toBe(0)
	})

	it('rounds and caps at 100', () => {
		expect(progressPercent({ status: 'running', metrics: { budgetSeconds: 3, secondsSpent: 1 } })).toBe(33)
		expect(progressPercent({ status: 'running', metrics: { budgetSeconds: 10, secondsSpent: 50 } })).toBe(100)
	})
})

describe('wishSummary edges', () => {
	it('is null without a wish and fills missing fields', () => {
		expect(wishSummary(null)).toBeNull()
		expect(wishSummary({ periods: 'mo1' })).toEqual({ appliesTo: '', reference: '', kind: '', periods: [] })
	})
})

describe('unplacedRows edges', () => {
	it('copes with a scenario without input or unplaced rows', () => {
		expect(unplacedRows(null)).toEqual([])
		expect(unplacedRows({ unplaced: [] })).toEqual([])
	})

	it('names a wish not in the input by its reference, and defaults the reason', () => {
		const rows = unplacedRows({
			input: { wishes: [{ id: 7, appliesTo: 'teacher', reference: 'JdV', kind: 'unavailable', periods: ['mo1'] }] },
			unplaced: [
				{ lesson: 'L1', wish: '7' },
				{ lesson: 'L2', wish: 'missing' },
				{ lesson: 'L3' },
				{ lesson: 'L4', reason: 'custom' },
			],
		})
		expect(rows).toEqual([
			{ lesson: 'L1', wish: { appliesTo: 'teacher', reference: 'JdV', kind: 'unavailable', periods: ['mo1'] }, reason: 'hardWish' },
			{ lesson: 'L2', wish: { appliesTo: '', reference: 'missing', kind: '', periods: [] }, reason: 'hardWish' },
			{ lesson: 'L3', wish: null, reason: 'noFreePlace' },
			{ lesson: 'L4', wish: null, reason: 'custom' },
		])
	})
})

describe('broken wish rows edges', () => {
	const scenario = {
		input: { wishes: [{ id: 'w1', reference: 'A' }] },
		brokenWishes: [
			{ wish: 'w1', strength: 'soft', weight: 2, lessons: ['x'] },
			{ wish: 'w2', strength: 'soft', lessons: ['y', 'z', 'q'] },
			{ wish: 'w3', strength: 'soft', weight: null, lessons: ['n'] },
			{ wish: 'w4', strength: 'hard' },
			{ wish: 'w1', strength: 'hard', lessons: ['h'] },
		],
	}

	it('lists soft rows with a default weight of 1, most weight times lessons first, skipping null weights', () => {
		const rows = brokenSoftRows(scenario)
		expect(rows.map((row) => [row.wish.reference, row.weight, row.lessons.length])).toEqual([['w2', 1, 3], ['A', 2, 1]])
	})

	it('lists hard rows with empty lessons by default', () => {
		expect(brokenHardRows(scenario)).toEqual([
			{ wish: { appliesTo: '', reference: 'w4', kind: '', periods: [] }, lessons: [] },
			{ wish: { appliesTo: '', reference: 'A', kind: '', periods: [] }, lessons: ['h'] },
		])
	})

	it('is empty without broken wishes', () => {
		expect(brokenSoftRows(null)).toEqual([])
		expect(brokenHardRows({})).toEqual([])
	})
})

describe('scenario permissions edges', () => {
	it('knows which statuses run', () => {
		expect(isRunning({ status: 'queued' })).toBe(true)
		expect(isRunning({ status: 'done' })).toBe(false)
		expect(isRunning(null)).toBe(false)
	})

	it('takes the current timetable only into an idle, unpublished imported scenario', () => {
		expect(canTakeCurrentTimetable({ source: 'imported', status: 'done' })).toBe(true)
		expect(canTakeCurrentTimetable({ source: 'imported', status: 'running' })).toBe(false)
		expect(canTakeCurrentTimetable({ source: 'imported', status: 'published' })).toBe(false)
		expect(canTakeCurrentTimetable({ source: 'generated', status: 'done' })).toBe(false)
	})

	it('publishes drafts only from a finished generated scenario with placements', () => {
		expect(canPublishDrafts({ source: 'generated', status: 'done', placements: [{}] })).toBe(true)
		expect(canPublishDrafts({ source: 'generated', status: 'done' })).toBe(false)
		expect(canPublishDrafts({ source: 'generated', status: 'running', placements: [{}] })).toBe(false)
		expect(canPublishDrafts({ source: 'imported', status: 'done', placements: [{}] })).toBe(false)
	})
})
