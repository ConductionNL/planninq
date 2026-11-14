/**
 * Moving or copying a board column to another project
 * (tasks-move-between-projects, task 3.1): the helpers in
 * src/utils/taskMove.js and the store's moveColumnToProject and
 * copyColumnToProject, with only fetch and the Nextcloud globals stood in for.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { columnCopy, columnTargets, columnTaskPatch, columnTasks, lastColumnOrder, taskCopy } = await import('../../src/utils/taskMove.js')
const { useProjectsStore } = await import('../../src/store/projects.js')

const VERGUNNINGEN = { id: 'p-verg', title: 'Vergunningen', owner: 'anna', members: ['bram', 'cees'] }
const HANDHAVING = { id: 'p-hand', title: 'Handhaving', owner: 'anna', members: ['bram'] }
const MEMBER_ONLY = { id: 'p-mem', title: 'Afval', owner: 'dirk', members: ['anna'] }
const ARCHIEF = { id: 'p-arch', title: 'Archief', owner: 'anna', status: 'archived' }

const INTAKE = { id: 'c-intake', title: 'Intake', project: 'p-verg', order: 0, wipLimit: 5, color: '#00f', automation: [{ to: 'c-done' }] }
const TASKS = [
	{ id: 't-1', title: 'Check the drawings', project: 'p-verg', column: 'c-intake', columnOrder: 0, assignedTo: 'cees', sharedWith: ['bram'], priority: 'high', labels: ['l-1'], checklist: [{ id: 'i-1', text: 'Stamp', done: true }], dueDate: '2026-12-01', status: 'in_progress' },
	{ id: 't-2', title: 'Call the applicant', project: 'p-verg', column: 'c-intake', columnOrder: 1, assignedTo: 'bram' },
	{ id: 't-3', title: 'Elsewhere', project: 'p-verg', column: 'c-other' },
	{ id: 's-1', title: 'Sub of t-1 in the backlog', project: 'p-verg', column: null, parent: 't-1', assignedTo: 'cees' },
]

describe('column move helpers', () => {
	it('offers only the open projects the person owns, except the column\'s own', () => {
		expect(columnTargets([VERGUNNINGEN, HANDHAVING, MEMBER_ONLY, ARCHIEF], 'p-verg', 'anna').map((project) => project.id)).toEqual(['p-hand'])
	})

	it('puts a column after the last one, or first on an empty board', () => {
		expect(lastColumnOrder([{ order: 0 }, { order: 4 }, { order: 2 }])).toBe(5)
		expect(lastColumnOrder([])).toBe(0)
		expect(lastColumnOrder([{ title: 'no order' }])).toBe(0)
	})

	it('splits the column\'s tasks from the subtasks that sit elsewhere', () => {
		const { inColumn, subtasks } = columnTasks(INTAKE, TASKS)

		expect(inColumn.map((task) => task.id)).toEqual(['t-1', 't-2'])
		expect(subtasks.map((task) => task.id)).toEqual(['s-1'])
	})

	it('keeps a moved task in its column and place, and clears people who are not on the target', () => {
		expect(columnTaskPatch(TASKS[0], HANDHAVING, 'c-intake')).toEqual({
			project: 'p-hand',
			column: 'c-intake',
			columnOrder: 0,
			assignedTo: null,
		})
	})

	it('copies the column settings but not its automation', () => {
		expect(columnCopy(INTAKE, HANDHAVING, 3)).toEqual({ title: 'Intake', project: 'p-hand', order: 3, wipLimit: 5, color: '#00f' })
	})

	it('copies a task open, without people, dates or ticked items', () => {
		expect(taskCopy(TASKS[0], HANDHAVING, 'c-new')).toEqual({
			title: 'Check the drawings',
			status: 'open',
			project: 'p-hand',
			column: 'c-new',
			columnOrder: 0,
			priority: 'high',
			labels: ['l-1'],
			checklist: [{ id: 'i-1', text: 'Stamp', done: false }],
		})
	})
})

describe('moveColumnToProject and copyColumnToProject', () => {
	let fetchMock

	beforeEach(() => {
		setActivePinia(createPinia())
		fetchMock = vi.fn()
		vi.stubGlobal('fetch', fetchMock)
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		vi.restoreAllMocks()
	})

	/**
	 * A fetch answer.
	 *
	 * @param {boolean} ok - whether the write succeeded
	 * @param {object} body - the answer body
	 * @return {object} the response
	 */
	function answer(ok, body) {
		return { ok, status: ok ? 200 : 403, json: async () => body, text: async () => JSON.stringify(body) }
	}

	/**
	 * The writes made, as [url, method, body].
	 *
	 * @return {Array<Array>}
	 */
	function writes() {
		return fetchMock.mock.calls.map(([url, init]) => [url, init.method, JSON.parse(init.body)])
	}

	it('moves the column last on the target board, then its tasks, then their subtasks', async () => {
		fetchMock.mockResolvedValue(answer(true, {}))

		const result = await useProjectsStore().moveColumnToProject(INTAKE, HANDHAVING, TASKS, 7)

		expect(result).toEqual({ ok: true, failed: [] })
		expect(writes()).toEqual([
			['/apps/openregister/api/objects/planninq/column/c-intake', 'PATCH', { project: 'p-hand', order: 7 }],
			['/apps/openregister/api/objects/planninq/task/t-1', 'PATCH', { project: 'p-hand', column: 'c-intake', columnOrder: 0, assignedTo: null }],
			['/apps/openregister/api/objects/planninq/task/t-2', 'PATCH', { project: 'p-hand', column: 'c-intake', columnOrder: 1 }],
			['/apps/openregister/api/objects/planninq/task/s-1', 'PATCH', { project: 'p-hand', column: null, columnOrder: null, assignedTo: null }],
		])
	})

	it('writes no task when the column move is refused', async () => {
		fetchMock.mockResolvedValue(answer(false, { message: 'Only the project owner can change the board columns.' }))

		const result = await useProjectsStore().moveColumnToProject(INTAKE, HANDHAVING, TASKS, 7)

		expect(result).toEqual({ ok: false, failed: [] })
		expect(fetchMock).toHaveBeenCalledTimes(1)
	})

	it('reports the tasks that could not move', async () => {
		fetchMock
			.mockResolvedValueOnce(answer(true, {}))
			.mockResolvedValueOnce(answer(true, {}))
			.mockResolvedValueOnce(answer(false, { message: 'no' }))
			.mockResolvedValueOnce(answer(true, {}))

		const result = await useProjectsStore().moveColumnToProject(INTAKE, HANDHAVING, TASKS, 7)

		expect(result).toEqual({ ok: true, failed: ['t-2'] })
	})

	it('copies the column last on the target board with an open copy of each of its tasks, and leaves the source alone', async () => {
		fetchMock
			.mockResolvedValueOnce(answer(true, { id: 'c-new', title: 'Intake' }))
			.mockResolvedValue(answer(true, { id: 'copy' }))

		const result = await useProjectsStore().copyColumnToProject(INTAKE, HANDHAVING, TASKS, 2)

		expect(result).toEqual({ ok: true, column: { id: 'c-new', title: 'Intake' }, failed: [] })
		const made = writes()
		expect(made[0]).toEqual(['/apps/openregister/api/objects/planninq/column', 'POST', { title: 'Intake', project: 'p-hand', order: 2, wipLimit: 5, color: '#00f' }])
		expect(made.slice(1).map(([url, method, body]) => [url, method, body.title, body.column, body.status, body.assignedTo])).toEqual([
			['/apps/openregister/api/objects/planninq/task', 'POST', 'Check the drawings', 'c-new', 'open', undefined],
			['/apps/openregister/api/objects/planninq/task', 'POST', 'Call the applicant', 'c-new', 'open', undefined],
		])
		expect(made.some(([, method]) => method === 'PATCH' || method === 'DELETE')).toBe(false)
	})

	it('copies no task when the new column cannot be created', async () => {
		fetchMock.mockResolvedValue(answer(false, {}))

		const result = await useProjectsStore().copyColumnToProject(INTAKE, HANDHAVING, TASKS, 2)

		expect(result).toEqual({ ok: false, failed: [] })
		expect(fetchMock).toHaveBeenCalledTimes(1)
	})
})
