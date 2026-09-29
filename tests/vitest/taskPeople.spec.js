/**
 * Vitest unit tests for people, priority and labels on a task (tasks-assignment-priority-labels).
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	labelsPatch,
	memberOptions,
	peopleOf,
	PRIORITIES,
	priorityPatch,
	responsiblePatch,
	sharedWithPatch,
} from '../../src/utils/taskPeople.js'

describe('memberOptions (scenario: only members are offered)', () => {
	it('offers the project members only, with display names, sorted by name', () => {
		const project = { members: ['bram', 'anna'], owner: 'anna' }
		expect(memberOptions(project, { anna: 'Anna de Vries', bram: 'Bram Jansen', carla: 'Carla' })).toEqual([
			{ id: 'anna', label: 'Anna de Vries' },
			{ id: 'bram', label: 'Bram Jansen' },
		])
	})

	it('adds the owner when the members list lacks them, and falls back to the uid', () => {
		expect(memberOptions({ members: ['bram'], owner: 'anna' }, {}).map((option) => option.id)).toEqual(['anna', 'bram'])
		expect(memberOptions(null, {})).toEqual([])
	})
})

describe('peopleOf (scenario: two people on one task)', () => {
	it('lists the responsible person first, then the others, without duplicates', () => {
		expect(peopleOf({ assignedTo: 'bram', sharedWith: ['anna', 'bram', 'anna'] })).toEqual(['bram', 'anna'])
		expect(peopleOf({ sharedWith: ['anna'] })).toEqual(['anna'])
		expect(peopleOf({})).toEqual([])
	})
})

describe('responsiblePatch and sharedWithPatch (scenario: assign a task on the task page)', () => {
	it('sets the responsible person and drops them from the shared list', () => {
		expect(responsiblePatch({ assignedTo: '', sharedWith: ['bram', 'anna'] }, 'bram')).toEqual({ assignedTo: 'bram', sharedWith: ['anna'] })
		expect(responsiblePatch({ assignedTo: 'bram', sharedWith: [] }, 'bram')).toEqual({})
		expect(responsiblePatch({ assignedTo: 'bram' }, null)).toEqual({ assignedTo: '' })
	})

	it('keeps the responsible person out of the shared list', () => {
		expect(sharedWithPatch({ assignedTo: 'bram', sharedWith: [] }, ['anna', 'bram'])).toEqual({ sharedWith: ['anna'] })
		expect(sharedWithPatch({ assignedTo: 'bram', sharedWith: ['anna'] }, ['anna'])).toEqual({})
	})
})

describe('priorityPatch (scenario: raise a priority from the board)', () => {
	it('offers four levels and patches only a change', () => {
		expect(PRIORITIES).toEqual(['urgent', 'high', 'normal', 'low'])
		expect(priorityPatch({ priority: 'normal' }, 'urgent')).toEqual({ priority: 'urgent' })
		expect(priorityPatch({ priority: 'urgent' }, 'urgent')).toEqual({})
		expect(priorityPatch({ priority: 'normal' }, 'critical')).toEqual({})
	})
})

describe('labelsPatch (scenario: attach a label)', () => {
	it('writes the chosen label ids, deduplicated, and nothing when unchanged', () => {
		expect(labelsPatch({ labels: [] }, ['l-1', 'l-1', 'l-2'])).toEqual({ labels: ['l-1', 'l-2'] })
		expect(labelsPatch({ labels: ['l-2', 'l-1'] }, ['l-1', 'l-2'])).toEqual({})
		expect(labelsPatch({ labels: ['l-1'] }, [])).toEqual({ labels: [] })
	})
})

describe('the board card menu carries a Priority submenu (task 3.1)', () => {
	const board = readFileSync(new URL('../../src/views/ProjectBoard.vue', import.meta.url), 'utf8')

	it('lists the four levels from PRIORITIES and saves through setPriority', () => {
		expect(board).toMatch(/v-for="level in priorityLevels"/)
		expect(board).toMatch(/@click="setPriority\(task, level\.id\)"/)
	})
})
