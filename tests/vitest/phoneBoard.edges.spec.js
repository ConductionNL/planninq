/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the phone board helpers.
 *
 * @spec openspec/changes/archive/2026-09-30-platform-mobile-web/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { columnSwitcher, phoneColumnId } from '../../src/utils/phoneBoard.js'

describe('columnSwitcher edges', () => {
	it('is empty without columns', () => {
		expect(columnSwitcher(null, {})).toEqual([])
	})

	it('counts 0 for a column without a lane and without lanes at all', () => {
		const columns = [{ id: 'a', title: 'A' }, { id: 'b', title: 'B' }]
		expect(columnSwitcher(columns, { a: [1, 2] })).toEqual([{ id: 'a', title: 'A', count: 2 }, { id: 'b', title: 'B', count: 0 }])
		expect(columnSwitcher(columns, null).map((c) => c.count)).toEqual([0, 0])
	})
})

describe('phoneColumnId edges', () => {
	it('is null for a board without columns', () => {
		expect(phoneColumnId(null, 'a')).toBeNull()
		expect(phoneColumnId([], null)).toBeNull()
	})

	it('falls back to the first column when the choice is gone or empty', () => {
		const columns = [{ id: 'a' }, { id: 'b' }]
		expect(phoneColumnId(columns, 'gone')).toBe('a')
		expect(phoneColumnId(columns, '')).toBe('a')
		expect(phoneColumnId(columns, 'b')).toBe('b')
	})
})
