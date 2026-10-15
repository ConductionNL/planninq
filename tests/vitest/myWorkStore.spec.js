/**
 * The projects store's fetchMyTasks (portfolio-my-work-dashboard), with the
 * object store replaced by one that pages like OpenRegister.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

let tasks = []
const reads = []
vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({
		registerObjectType: () => {},
		objectTypeRegistry: {},
		fetchCollection: async (schema, filters) => {
			reads.push({ schema, filters })
			const size = filters._limit
			return tasks.slice((filters._page - 1) * size, filters._page * size)
		},
	}),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

describe('fetchMyTasks', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		reads.length = 0
	})

	it('returns the tasks assigned to me and the ones shared with me, across projects and pages', async () => {
		tasks = [
			{ id: 't1', project: 'p1', assignedTo: 'anna', status: 'open' },
			{ id: 't2', project: 'p2', assignedTo: 'bram', sharedWith: ['anna'], status: 'open' },
			{ id: 't3', project: 'p2', assignedTo: 'bram', status: 'open' },
			{ id: 't4', project: 'p1', assignedTo: 'anna', status: 'done' },
		]
		const mine = await useProjectsStore().fetchMyTasks()
		expect(mine.map((task) => task.id)).toEqual(['t1', 't2', 't4'])
		expect(reads.every((read) => read.schema === 'task')).toBe(true)
	})

	it('follows the pages to the end', async () => {
		tasks = Array.from({ length: 1205 }, (_, i) => ({ id: `t${i}`, assignedTo: 'anna', status: 'open' }))
		const mine = await useProjectsStore().fetchMyTasks()
		expect(mine).toHaveLength(1205)
	})
})
