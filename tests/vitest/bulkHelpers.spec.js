/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The backlog's selection and the patch each bulk action makes for a task.
 *
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.1
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
 */

import { describe, expect, it } from 'vitest'
import {
	allSelected,
	BULK_ACTIONS,
	bulkPatch,
	toggleAll,
	toggleSelection,
	visibleSelection,
} from '../../src/utils/bulkHelpers.js'

const rows = [{ id: 'a' }, { id: 'b' }, { id: 'c' }]

describe('backlog selection', () => {
	it('adds and removes one task', () => {
		expect(toggleSelection([], 'a')).toEqual(['a'])
		expect(toggleSelection(['a', 'b'], 'a')).toEqual(['b'])
	})

	it('selects every visible row, and clears them when all are selected', () => {
		expect(toggleAll(['a'], rows)).toEqual(['a', 'b', 'c'])
		expect(toggleAll(['a', 'b', 'c'], rows)).toEqual([])
		expect(allSelected(['a', 'b', 'c'], rows)).toBe(true)
		expect(allSelected(['a'], rows)).toBe(false)
		expect(allSelected([], [])).toBe(false)
	})

	it('keeps the selection to the visible rows', () => {
		expect(visibleSelection(['a', 'x', 'c'], rows)).toEqual(['a', 'c'])
	})
})

describe('bulk patch', () => {
	const task = { id: 'a', labels: ['l1', 'l2'] }

	it('offers status, assignee, priority and labels', () => {
		expect(BULK_ACTIONS).toEqual(['status', 'assign', 'priority', 'labelAdd', 'labelRemove'])
	})

	it('sets a known status or priority, and nothing for an unknown one', () => {
		expect(bulkPatch('status', 'done', task)).toEqual({ status: 'done' })
		expect(bulkPatch('status', 'archived', task)).toBeNull()
		expect(bulkPatch('priority', 'urgent', task)).toEqual({ priority: 'urgent' })
		expect(bulkPatch('priority', 'whenever', task)).toBeNull()
	})

	it('assigns a person, or unassigns', () => {
		expect(bulkPatch('assign', 'anne', task)).toEqual({ assignedTo: 'anne' })
		expect(bulkPatch('assign', '', task)).toEqual({ assignedTo: null })
	})

	it('adds or removes one label and keeps the others', () => {
		expect(bulkPatch('labelAdd', 'l3', task)).toEqual({ labels: ['l1', 'l2', 'l3'] })
		expect(bulkPatch('labelAdd', 'l1', task)).toBeNull()
		expect(bulkPatch('labelRemove', 'l1', task)).toEqual({ labels: ['l2'] })
		expect(bulkPatch('labelRemove', 'l9', task)).toBeNull()
		expect(bulkPatch('labelAdd', 'l1', { id: 'b' })).toEqual({ labels: ['l1'] })
	})

	it('changes nothing for an unknown action', () => {
		expect(bulkPatch('delete', 'x', task)).toBeNull()
	})
})
