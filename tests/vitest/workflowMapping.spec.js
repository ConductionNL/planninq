/**
 * Vitest unit tests for moving a project onto a shared workflow
 * (projects-templates-shared-workflow 2.5): the title match and the count of
 * tasks that fall back to the first column.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
 */
import { describe, expect, it } from 'vitest'
import { keptColumnIds, mappingPreview } from '../../src/utils/workflowMapping.js'

const flow = [
	{ key: 'intake', title: 'Intake' },
	{ key: 'review', title: 'Beoordeling' },
	{ key: 'decision', title: 'Besluit' },
]
const columns = [
	{ id: 'c1', title: 'To Do' },
	{ id: 'c2', title: 'Intake' },
	{ id: 'c3', title: 'Done' },
]
const tasks = [
	...Array.from({ length: 2 }, (_, i) => ({ id: 'a' + i, column: 'c1' })),
	...Array.from({ length: 5 }, (_, i) => ({ id: 'b' + i, column: 'c2' })),
	...Array.from({ length: 2 }, (_, i) => ({ id: 'c' + i, column: 'c3' })),
]

describe('mappingPreview (scenario: moving a project onto a workflow)', () => {
	it('moves the tasks of columns the workflow lacks to its first column', () => {
		expect(mappingPreview(columns, tasks, flow)).toEqual({ moved: 4, target: 'Intake' })
	})

	it('matches titles regardless of case and spaces', () => {
		const kept = keptColumnIds([{ id: 'x', title: '  intake ' }], flow)
		expect([...kept]).toEqual(['x'])
	})

	it('keeps a column that already follows a workflow column by key, whatever its title', () => {
		const kept = keptColumnIds([{ id: 'x', title: 'Instroom', workflowKey: 'intake' }, { id: 'y', title: 'Intake' }], flow)
		expect([...kept]).toEqual(['x'])
	})

	it('leaves backlog tasks and an empty project alone', () => {
		expect(mappingPreview(columns, [{ id: 't', column: null }, { id: 'u' }], flow).moved).toBe(0)
		expect(mappingPreview([], [], flow)).toEqual({ moved: 0, target: 'Intake' })
	})
})
