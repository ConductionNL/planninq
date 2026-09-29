/**
 * Vitest tests for card colours and swimlanes (boards-card-display).
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { cardEdge, epicTitles, groupTasksBySwimlane, normaliseView, swimlanePatch } from '../../src/utils/boardView.js'

const tasks = [
	{ id: 'a1', assignedTo: 'anna', priority: 'high', epic: 'e1' },
	{ id: 'a2', assignedTo: 'anna', priority: 'urgent' },
	{ id: 'a3', assignedTo: 'anna' },
	{ id: 'b1', assignedTo: 'bram', priority: 'low', epic: { id: 'e1' } },
	{ id: 'b2', assignedTo: 'bram', priority: 'high' },
	{ id: 'n1', assignedTo: '' },
]

describe('cardEdge (scenario: colour by label)', () => {
	it('takes the first label\'s colour in label mode', () => {
		expect(cardEdge(tasks[0], [{ title: 'Juridisch', color: '#c00' }, { color: '#00c' }], 'label')).toBe('#c00')
		expect(cardEdge(tasks[0], [], 'label')).toBeNull()
	})

	it('uses a token for urgent and high only in priority mode', () => {
		expect(cardEdge({ priority: 'urgent' }, [], 'priority')).toBe('var(--color-error)')
		expect(cardEdge({ priority: 'high' }, [], 'priority')).toBe('var(--color-warning)')
		expect(cardEdge({ priority: 'normal' }, [], 'priority')).toBeNull()
		expect(cardEdge({ priority: 'urgent' }, [{ color: '#c00' }], 'none')).toBeNull()
	})
})

describe('groupTasksBySwimlane', () => {
	it('groups by assignee with the no-value row last (scenario: swimlanes by assignee)', () => {
		const groups = groupTasksBySwimlane(tasks, 'assignee', { anna: 'Anna', bram: 'Bram' })
		expect(groups.map((g) => [g.name, g.tasks.length])).toEqual([['Anna', 3], ['Bram', 2], ['', 1]])
		expect(groups[2].key).toBe('')
	})

	it('orders priority rows most urgent first, a missing priority counting as normal', () => {
		expect(groupTasksBySwimlane(tasks, 'priority').map((g) => [g.key, g.tasks.length])).toEqual([['urgent', 1], ['high', 2], ['normal', 2], ['low', 1]])
	})

	it('groups by epic, reading a reference object too', () => {
		const groups = groupTasksBySwimlane(tasks, 'epic', { e1: 'Self-service export' })
		expect(groups.map((g) => [g.name, g.tasks.map((t) => t.id)])).toEqual([['Self-service export', ['a1', 'b1']], ['', ['a2', 'a3', 'b2', 'n1']]])
	})

	it('names epic rows after the epic tasks', () => {
		expect(epicTitles([{ id: 'e1', issueType: 'epic', title: 'Self-service export' }, { id: 't1', issueType: 'task', title: 'x' }])).toEqual({ e1: 'Self-service export' })
	})

	it('is one row with every task without grouping', () => {
		expect(groupTasksBySwimlane(tasks, 'none')).toEqual([{ key: '', name: '', tasks }])
	})
})

describe('swimlanePatch', () => {
	it('hands a task to the person of the row it is dropped in (scenario: hand a task to a colleague)', () => {
		expect(swimlanePatch(tasks[0], 'assignee', 'bram')).toEqual({ ok: true, patch: { assignedTo: 'bram' } })
		expect(swimlanePatch(tasks[0], 'assignee', '')).toEqual({ ok: true, patch: { assignedTo: '' } })
		expect(swimlanePatch({ assignedTo: 'anna', sharedWith: ['bram'] }, 'assignee', 'bram')).toEqual({ ok: true, patch: { assignedTo: 'bram', sharedWith: [] } })
		expect(swimlanePatch(tasks[0], 'assignee', 'anna')).toEqual({ ok: true, patch: {} })
	})

	it('changes the priority, and refuses another epic row', () => {
		expect(swimlanePatch(tasks[0], 'priority', 'urgent')).toEqual({ ok: true, patch: { priority: 'urgent' } })
		expect(swimlanePatch(tasks[0], 'epic', '')).toEqual({ ok: false, reason: 'epic' })
		expect(swimlanePatch(tasks[0], 'epic', 'e1')).toEqual({ ok: true, patch: {} })
	})
})

describe('normaliseView (scenario: come back to a grouped board)', () => {
	it('keeps a valid choice and drops anything else', () => {
		expect(normaliseView({ colour: 'label', group: 'priority' })).toEqual({ colour: 'label', group: 'priority' })
		expect(normaliseView({ colour: 'rainbow', group: 7 })).toEqual({ colour: 'none', group: 'none' })
		expect(normaliseView(null)).toEqual({ colour: 'none', group: 'none' })
	})
})
