/**
 * Vitest unit tests for subprojects (projects-grouping-hierarchy-fields,
 * section 2): the parent of a project, the programme's progress with its
 * subprojects, the indented project list with its toggle, and the parent
 * choices that cannot make a cycle.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { descendantsOf, parentIdOf, parentOptions, parentRefusal, rollupProgress, subprojectsOf, treeRows } from '../../src/utils/projectTree.js'

const prog = { id: 'prog', title: 'Programma Wonen' }
const a = { id: 'a', title: 'Woningbouw', parent: 'prog' }
const b = { id: 'b', title: 'Starterswoningen', parent: { id: 'prog' } }
const sub = { id: 'sub', title: 'Fase 1', parent: 'a' }
const loose = { id: 'loose', title: 'Los project' }
const all = [loose, prog, a, b, sub]

describe('the parent chain', () => {
	it('reads a parent as a uuid or a resolved object', () => {
		expect(parentIdOf(a)).toBe('prog')
		expect(parentIdOf(b)).toBe('prog')
		expect(parentIdOf(loose)).toBe('')
	})

	it('finds subprojects and every descendant', () => {
		expect(subprojectsOf(all, 'prog').map((p) => p.id)).toEqual(['a', 'b'])
		expect(descendantsOf(all, 'prog').map((p) => p.id)).toEqual(['a', 'b', 'sub'])
	})

	it('offers as parent every project but itself and its descendants (scenario: a cycle is refused)', () => {
		expect(parentOptions(all, prog).map((p) => p.id)).toEqual(['loose'])
		expect(parentOptions(all, sub).map((p) => p.id)).toEqual(['loose', 'prog', 'a', 'b'])
	})
})

describe('rollupProgress (scenario: a programme shows its subprojects)', () => {
	it('adds the subprojects\' tasks to a programme without tasks of its own', () => {
		expect(rollupProgress({ done: 0, total: 0 }, [{ done: 3, total: 10 }, { done: 5, total: 5 }])).toEqual({ done: 8, total: 15 })
	})
})

describe('treeRows (scenario: the project list shows subprojects under their parent)', () => {
	it('puts subprojects indented under their parent', () => {
		expect(treeRows(all, new Set()).map((row) => [row.project.id, row.depth, row.hasChildren, row.expanded])).toEqual([
			['loose', 0, false, true],
			['prog', 0, true, true],
			['a', 1, true, true],
			['sub', 2, false, true],
			['b', 1, false, true],
		])
	})

	it('hides the subprojects of a folded parent', () => {
		expect(treeRows(all, new Set(['prog'])).map((row) => row.project.id)).toEqual(['loose', 'prog'])
		expect(treeRows(all, new Set(['prog'])).find((row) => row.project.id === 'prog').expanded).toBe(false)
	})

	it('shows a subproject whose parent is not in the list as a top row', () => {
		expect(treeRows([a, sub], new Set()).map((row) => [row.project.id, row.depth])).toEqual([['a', 0], ['sub', 1]])
	})
})

describe('parentRefusal', () => {
	it('tells a cycle from a fourth level from any other failure', () => {
		expect(parentRefusal('A project cannot sit under one of its own subprojects.')).toBe('cycle')
		expect(parentRefusal('planninq-project-too-deep')).toBe('depth')
		expect(parentRefusal('update-error')).toBe('')
	})
})
