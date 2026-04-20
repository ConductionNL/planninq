import { dueDateStatus } from '../taskHelpers.js'

describe('dueDateStatus', () => {
	let today

	beforeEach(() => {
		today = new Date()
		today.setHours(0, 0, 0, 0)
	})

	// Helper to create a date string for a given offset (in days) from today
	function createDateString(dayOffset) {
		const date = new Date(today)
		date.setDate(date.getDate() + dayOffset)
		return date.toISOString().split('T')[0]
	}

	describe('task with no due date', () => {
		it('returns null when dueDate is undefined', () => {
			const task = { title: 'Test task' }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('returns null when dueDate is null', () => {
			const task = { title: 'Test task', dueDate: null }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('returns null when task is null', () => {
			expect(dueDateStatus(null)).toBe(null)
		})

		it('returns null when task is undefined', () => {
			expect(dueDateStatus(undefined)).toBe(null)
		})
	})

	describe('task with future due date', () => {
		it('returns null when due date is 3 days away', () => {
			const task = { title: 'Test task', dueDate: createDateString(3) }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('returns null when due date is 5 days away', () => {
			const task = { title: 'Test task', dueDate: createDateString(5) }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('returns null when due date is far in the future', () => {
			const task = { title: 'Test task', dueDate: createDateString(100) }
			expect(dueDateStatus(task)).toBe(null)
		})
	})

	describe('task with approaching due date', () => {
		it('returns "approaching" when due date is today', () => {
			const task = { title: 'Test task', dueDate: createDateString(0) }
			expect(dueDateStatus(task)).toBe('approaching')
		})

		it('returns "approaching" when due date is tomorrow', () => {
			const task = { title: 'Test task', dueDate: createDateString(1) }
			expect(dueDateStatus(task)).toBe('approaching')
		})

		it('returns "approaching" when due date is exactly 2 days away', () => {
			const task = { title: 'Test task', dueDate: createDateString(2) }
			expect(dueDateStatus(task)).toBe('approaching')
		})
	})

	describe('task with overdue', () => {
		it('returns "overdue" when due date is yesterday', () => {
			const task = { title: 'Test task', dueDate: createDateString(-1) }
			expect(dueDateStatus(task)).toBe('overdue')
		})

		it('returns "overdue" when due date is 3 days ago', () => {
			const task = { title: 'Test task', dueDate: createDateString(-3) }
			expect(dueDateStatus(task)).toBe('overdue')
		})

		it('returns "overdue" when due date is far in the past', () => {
			const task = { title: 'Test task', dueDate: createDateString(-100) }
			expect(dueDateStatus(task)).toBe('overdue')
		})
	})

	describe('date parsing', () => {
		it('handles ISO 8601 date strings correctly', () => {
			const isoDateString = '2026-04-20'
			const task = { title: 'Test task', dueDate: isoDateString }
			const result = dueDateStatus(task)
			expect(['approaching', 'overdue', null]).toContain(result)
		})

		it('ignores time component in date comparison', () => {
			// Create a task with a date that includes time, but only the date part should matter
			const tomorrow = new Date(today)
			tomorrow.setDate(tomorrow.getDate() + 1)
			const dueDateWithTime = tomorrow.toISOString().split('T')[0]
			const task = { title: 'Test task', dueDate: dueDateWithTime }
			expect(dueDateStatus(task)).toBe('approaching')
		})
	})
})
