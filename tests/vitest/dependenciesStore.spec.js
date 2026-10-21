/**
 * The dependencies store reads every page of edges, not only the first.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const pages = []
const fetchCollection = vi.fn(async (schema, filters) => pages[filters._page - 1] || [])
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, fetchCollection }) }))

const { useDependenciesStore } = await import('../../src/store/dependencies.js')

describe('dependencies store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		pages.length = 0
		fetchCollection.mockClear()
	})

	it('follows the pages past the 1000-edge cap', async () => {
		pages.push(Array.from({ length: 1000 }, (_, i) => ({ id: `e${i}`, blocker: 'a', blocked: `b${i}` })))
		pages.push([{ id: 'e1000', blocker: 'a', blocked: 'c', type: 'relates' }])

		const edges = await useDependenciesStore().fetchEdges()

		expect(edges).toHaveLength(1001)
		expect(edges.at(-1)).toEqual({ id: 'e1000', blocker: 'a', blocked: 'c', type: 'relates' })
		expect(fetchCollection.mock.calls.map(([schema, filters]) => [schema, filters._page, filters._limit])).toEqual([['dependency', 1, 1000], ['dependency', 2, 1000]])
	})
})
