/**
 * The phone board's column switcher (platform-mobile-web task 1.1): every
 * column named with its card count, and the column the phone shows.
 *
 * @spec openspec/changes/archive/2026-09-30-platform-mobile-web/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { columnSwitcher, PHONE_MAX_WIDTH, phoneColumnId } from '../../src/utils/phoneBoard.js'

const columns = [
	{ id: 'c1', title: 'To do' },
	{ id: 'c2', title: 'In progress' },
	{ id: 'c3', title: 'Done' },
]
const lanes = { c1: [{ id: 't1' }], c2: [{ id: 't2' }, { id: 't3' }, { id: 't4' }], c3: [] }

describe('phoneBoard', () => {
	it('names every column with its card count, in board order', () => {
		expect(columnSwitcher(columns, lanes)).toEqual([
			{ id: 'c1', title: 'To do', count: 1 },
			{ id: 'c2', title: 'In progress', count: 3 },
			{ id: 'c3', title: 'Done', count: 0 },
		])
	})

	it('counts a column missing from the lanes as empty', () => {
		expect(columnSwitcher([{ id: 'x', title: 'New' }], {})).toEqual([{ id: 'x', title: 'New', count: 0 }])
	})

	it('shows the chosen column, or the first when none is chosen or it was removed', () => {
		expect(phoneColumnId(columns, 'c2')).toBe('c2')
		expect(phoneColumnId(columns, null)).toBe('c1')
		expect(phoneColumnId(columns, 'gone')).toBe('c1')
		expect(phoneColumnId([], 'c2')).toBe(null)
	})

	it('switches to the phone layout under 600 pixels', () => {
		expect(PHONE_MAX_WIDTH).toBe(600)
	})
})
