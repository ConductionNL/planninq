/**
 * Vitest unit tests for the week-view day grouping helper.
 *
 * Covers the derivation the ProjectBoard week view relies on: seven Monday to
 * Sunday buckets for the week containing the passed-in start, a task in the
 * bucket of its due date, a task outside the week in no bucket, and a task
 * without a (parseable) due date in the `unscheduled` bucket rather than
 * silently dropped.
 *
 * @spec openspec/changes/add-week-view-to-planning-board/specs/kanban-board/spec.md
 */
import { describe, expect, it } from 'vitest'
import { groupTasksByDay, weekDayKeys } from '../../src/utils/taskHelpers.js'

// Wednesday 2026-06-17 — its Monday-to-Sunday week is 2026-06-15 … 2026-06-21.
const WEEK_START = new Date(2026, 5, 17)

describe('weekDayKeys', () => {
	it('returns the seven Monday to Sunday keys of the week', () => {
		expect(weekDayKeys(WEEK_START)).toEqual([
			'2026-06-15',
			'2026-06-16',
			'2026-06-17',
			'2026-06-18',
			'2026-06-19',
			'2026-06-20',
			'2026-06-21',
		])
	})

	it('resolves the same week from any day inside it', () => {
		expect(weekDayKeys(new Date(2026, 5, 15))).toEqual(weekDayKeys(WEEK_START))
		expect(weekDayKeys(new Date(2026, 5, 21))).toEqual(weekDayKeys(WEEK_START))
	})
})

describe('groupTasksByDay', () => {
	it('returns one empty bucket per day for an empty week', () => {
		const grouped = groupTasksByDay([], WEEK_START)
		expect(grouped.days.map((day) => day.date)).toEqual(weekDayKeys(WEEK_START))
		for (const day of grouped.days) {
			expect(day.tasks).toEqual([])
		}
		expect(grouped.unscheduled).toEqual([])
	})

	it('places a task on each day in the bucket of its due date', () => {
		const tasks = [
			{ id: 'mon', dueDate: '2026-06-15' },
			{ id: 'tue', dueDate: '2026-06-16' },
			{ id: 'wed', dueDate: '2026-06-17' },
			{ id: 'thu', dueDate: '2026-06-18' },
			{ id: 'fri', dueDate: '2026-06-19' },
			{ id: 'sat', dueDate: '2026-06-20' },
			{ id: 'sun', dueDate: '2026-06-21' },
		]
		const grouped = groupTasksByDay(tasks, WEEK_START)
		expect(grouped.days.map((day) => day.tasks.map((t) => t.id))).toEqual([
			['mon'],
			['tue'],
			['wed'],
			['thu'],
			['fri'],
			['sat'],
			['sun'],
		])
		expect(grouped.unscheduled).toEqual([])
	})

	it('keeps a task with a timestamped due date on its day', () => {
		const grouped = groupTasksByDay(
			[{ id: 'wed', dueDate: '2026-06-17T14:30:00+02:00' }],
			WEEK_START,
		)
		expect(grouped.days[2].tasks.map((t) => t.id)).toEqual(['wed'])
	})

	it('excludes a task outside the rendered week from every day column', () => {
		const grouped = groupTasksByDay(
			[
				{ id: 'next-week', dueDate: '2026-06-22' },
				{ id: 'last-week', dueDate: '2026-06-14' },
				{ id: 'in-week', dueDate: '2026-06-17' },
			],
			WEEK_START,
		)
		const placed = grouped.days.flatMap((day) => day.tasks.map((t) => t.id))
		expect(placed).toEqual(['in-week'])
		expect(grouped.unscheduled).toEqual([])
	})

	it('buckets a task without a due date as unscheduled', () => {
		const grouped = groupTasksByDay(
			[
				{ id: 'no-date' },
				{ id: 'empty-date', dueDate: '' },
				{ id: 'null-date', dueDate: null },
			],
			WEEK_START,
		)
		expect(grouped.unscheduled.map((t) => t.id)).toEqual([
			'no-date',
			'empty-date',
			'null-date',
		])
		expect(grouped.days.flatMap((day) => day.tasks)).toEqual([])
	})

	it('buckets a task with an unparseable due date as unscheduled', () => {
		const grouped = groupTasksByDay(
			[{ id: 'broken', dueDate: 'not-a-date' }],
			WEEK_START,
		)
		expect(grouped.unscheduled.map((t) => t.id)).toEqual(['broken'])
		expect(grouped.days.flatMap((day) => day.tasks)).toEqual([])
	})

	it('ignores null / undefined entries', () => {
		const grouped = groupTasksByDay(
			[null, undefined, { id: 'wed', dueDate: '2026-06-17' }],
			WEEK_START,
		)
		expect(grouped.days[2].tasks.map((t) => t.id)).toEqual(['wed'])
		expect(grouped.unscheduled).toEqual([])
	})

	it('preserves insertion order within a day', () => {
		const grouped = groupTasksByDay(
			[
				{ id: '1', dueDate: '2026-06-17' },
				{ id: '2', dueDate: '2026-06-17' },
				{ id: '3', dueDate: '2026-06-17' },
			],
			WEEK_START,
		)
		expect(grouped.days[2].tasks.map((t) => t.id)).toEqual(['1', '2', '3'])
	})
})
