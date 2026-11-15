/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the project tree: duplicates, folded parents, orphans and
 * the parent refusal texts.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.3
 */
import { describe, expect, it } from 'vitest'
import { parentRefusal, treeRows } from '../../src/utils/projectTree.js'

const shape = (rows) => rows.map((row) => [row.project.id, row.depth, row.hasChildren, row.expanded])

describe('treeRows edges', () => {
	it('is empty for a missing list', () => {
		expect(treeRows(null)).toEqual([])
	})

	it('lists a project whose parent is not in the list as a top row', () => {
		expect(shape(treeRows([{ id: 'a', parent: 'elsewhere' }]))).toEqual([['a', 0, false, true]])
	})

	it('places a duplicated id once', () => {
		expect(shape(treeRows([{ id: 'a' }, { id: 'a' }]))).toEqual([['a', 0, false, true]])
	})

	it('hides every descendant of a folded parent, also when listed before it', () => {
		const projects = [{ id: 'gc', parent: 'c' }, { id: 'p' }, { id: 'c', parent: 'p' }, { id: 'q' }]
		expect(shape(treeRows(projects, new Set(['p'])))).toEqual([['p', 0, true, false], ['q', 0, false, true]])
		expect(shape(treeRows(projects))).toEqual([['p', 0, true, true], ['c', 1, true, true], ['gc', 2, false, true], ['q', 0, false, true]])
	})
})

describe('parentRefusal edges', () => {
	it('reads the cycle and depth refusals by code or by text, else nothing', () => {
		expect(parentRefusal('planninq-project-cycle')).toBe('cycle')
		expect(parentRefusal('cannot be one of its own subprojects')).toBe('cycle')
		expect(parentRefusal('planninq-project-too-deep')).toBe('depth')
		expect(parentRefusal('at most three levels')).toBe('depth')
		expect(parentRefusal(null)).toBe('')
		expect(parentRefusal('other')).toBe('')
	})
})
