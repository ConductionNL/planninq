// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
// Unit tests for task utility helpers.
// @spec openspec/changes/task-due-date-warning/tasks.md#task-4

import { dueDateStatus } from '../../../src/utils/taskHelpers.js'

describe('taskHelpers.dueDateStatus', () => {
	beforeEach(() => {
		// Mock the current date for consistent test results
		jest.useFakeTimers()
		jest.setSystemTime(new Date('2026-04-17').getTime())
	})

	afterEach(() => {
		jest.useRealTimers()
	})

	describe('no due date', () => {
		it('should return null for task without dueDate', () => {
			const task = {}
			expect(dueDateStatus(task)).toBe(null)
		})

		it('should return null for task with null dueDate', () => {
			const task = { dueDate: null }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('should return null for task with undefined dueDate', () => {
			const task = { dueDate: undefined }
			expect(dueDateStatus(task)).toBe(null)
		})
	})

	describe('future due dates', () => {
		it('should return null for due date 3 days away', () => {
			const task = { dueDate: '2026-04-20' }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('should return null for due date 5 days away', () => {
			const task = { dueDate: '2026-04-22' }
			expect(dueDateStatus(task)).toBe(null)
		})

		it('should return null for due date far in the future', () => {
			const task = { dueDate: '2026-12-31' }
			expect(dueDateStatus(task)).toBe(null)
		})
	})

	describe('approaching due dates (within 2 days)', () => {
		it('should return "approaching" for due date today', () => {
			const task = { dueDate: '2026-04-17' }
			expect(dueDateStatus(task)).toBe('approaching')
		})

		it('should return "approaching" for due date tomorrow', () => {
			const task = { dueDate: '2026-04-18' }
			expect(dueDateStatus(task)).toBe('approaching')
		})

		it('should return "approaching" for due date exactly 2 days away', () => {
			const task = { dueDate: '2026-04-19' }
			expect(dueDateStatus(task)).toBe('approaching')
		})
	})

	describe('overdue dates', () => {
		it('should return "overdue" for due date yesterday', () => {
			const task = { dueDate: '2026-04-16' }
			expect(dueDateStatus(task)).toBe('overdue')
		})

		it('should return "overdue" for due date 3 days ago', () => {
			const task = { dueDate: '2026-04-14' }
			expect(dueDateStatus(task)).toBe('overdue')
		})

		it('should return "overdue" for due date far in the past', () => {
			const task = { dueDate: '2025-01-01' }
			expect(dueDateStatus(task)).toBe('overdue')
		})
	})

	describe('boundary conditions', () => {
		it('should ignore time component in dueDate', () => {
			const task = { dueDate: '2026-04-18T23:59:59Z' }
			expect(dueDateStatus(task)).toBe('approaching')
		})

		it('should handle null task gracefully', () => {
			expect(dueDateStatus(null)).toBe(null)
		})

		it('should handle undefined task gracefully', () => {
			expect(dueDateStatus(undefined)).toBe(null)
		})

		it('should handle invalid date format', () => {
			const task = { dueDate: 'not-a-date' }
			// Invalid date should return null (NaN comparison is falsy)
			expect(dueDateStatus(task)).toBeNull()
		})
	})
})
