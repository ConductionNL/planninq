/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * The live project board (board-live-updates): the board subscribes to the
 * task collection, reads its project's tasks again on a live event, and holds
 * the card being dragged until it is dropped. The mixin runs as plain
 * component options against a fake object store (the live updates plugin's
 * surface) and a fake projects store; only those two are doubles.
 *
 * @spec openspec/changes/board-live-updates/tasks.md#task-1.1
 * @spec openspec/changes/board-live-updates/tasks.md#task-1.2
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { liveTaskId, mergeLiveTasks } from '../../src/utils/boardLive.js'
import { groupTasksByColumn } from '../../src/utils/columnHelpers.js'

const objectStore = {
	liveLastEventAt: null,
	subscribe: vi.fn(),
	unsubscribe: vi.fn(),
}

vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => objectStore }))

const { default: boardLiveUpdates, LIVE_TASK_TYPE } = await import('../../src/mixins/boardLiveUpdates.js')

const COLUMNS = [
	{ id: 'todo', title: 'To do', order: 1 },
	{ id: 'doing', title: 'Doing', order: 2 },
]

/**
 * A board instance of the mixin, with the state ProjectBoard supplies.
 *
 * @param {Array<object>} tasks The cards on the board
 * @param {Function} fetchTasks The projects store's fetchTasks double
 * @return {object}
 */
function board(tasks, fetchTasks) {
	const vm = {
		...boardLiveUpdates.data(),
		tasks,
		draggingTask: null,
		accessDenied: false,
		watchers: [],
		projectsStore: {
			_objectStore: vi.fn(),
			fetchTasks,
		},
		$watch(source, callback) {
			const entry = { source, callback, stopped: false }
			this.watchers.push(entry)
			return () => {
				entry.stopped = true
			}
		},
	}
	for (const [name, method] of Object.entries(boardLiveUpdates.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

/**
 * Deliver a live event: stamp liveLastEventAt and run the watchers that watch it.
 *
 * @param {object} vm The board
 * @return {Promise<void>}
 */
async function pushEvent(vm) {
	objectStore.liveLastEventAt = new Date()
	await Promise.all(vm.watchers
		.filter((entry) => !entry.stopped && entry.source() === objectStore.liveLastEventAt)
		.map((entry) => entry.callback()))
}

/**
 * The titles of the cards in one lane.
 *
 * @param {Array<object>} tasks The board's cards
 * @param {string} columnId The lane
 * @return {Array<string>}
 */
function lane(tasks, columnId) {
	return groupTasksByColumn(tasks, COLUMNS)[columnId].map((task) => task.title)
}

const card = (id, title, column) => ({ id, title, column, project: 'p1' })

describe('mergeLiveTasks', () => {
	it('takes the fresh read as the board when no card is held', () => {
		const fresh = [card('t1', 'A', 'doing'), card('t3', 'C', 'todo')]
		const result = mergeLiveTasks([card('t1', 'A', 'todo'), card('t2', 'B', 'todo')], fresh, '')
		expect(result.tasks).toBe(fresh)
		expect(result.held).toBe(false)
	})

	it('keeps the held card as it is and in its place while the others follow the read', () => {
		const held = card('t1', 'Old', 'todo')
		const result = mergeLiveTasks([held, card('t2', 'B', 'todo')], [card('t1', 'New', 'doing'), card('t2', 'B', 'doing')], 't1')
		expect(result.tasks[0]).toBe(held)
		expect(result.tasks[1]).toEqual(card('t2', 'B', 'doing'))
		expect(result.held).toBe(true)
	})

	it('keeps a held card the read no longer has until it is dropped', () => {
		const held = card('t1', 'A', 'todo')
		const result = mergeLiveTasks([held], [card('t2', 'B', 'todo')], 't1')
		expect(result.tasks.map(liveTaskId)).toEqual(['t2', 't1'])
		expect(result.held).toBe(true)
	})

	it('ignores a held id that is not on the board', () => {
		const fresh = [card('t2', 'B', 'todo')]
		expect(mergeLiveTasks([], fresh, 't1')).toEqual({ tasks: fresh, held: false })
	})

	it('reads ids from id, uuid or @self', () => {
		expect(liveTaskId({ id: 5 })).toBe('5')
		expect(liveTaskId({ uuid: 'u' })).toBe('u')
		expect(liveTaskId({ '@self': { id: 's' } })).toBe('s')
		expect(liveTaskId(null)).toBe('')
	})
})

describe('boardLiveUpdates', () => {
	beforeEach(() => {
		objectStore.liveLastEventAt = null
		objectStore.subscribe.mockReset().mockResolvedValue({ handle: 'h1' })
		objectStore.unsubscribe.mockReset()
	})

	it('subscribes to the task collection once and unsubscribes on leave', async () => {
		const vm = board([], vi.fn())
		await vm.syncLiveSubscription('p1')
		await vm.syncLiveSubscription('p1')

		expect(objectStore.subscribe).toHaveBeenCalledTimes(1)
		expect(objectStore.subscribe).toHaveBeenCalledWith(LIVE_TASK_TYPE)
		expect(vm.projectsStore._objectStore).toHaveBeenCalled()
		expect(vm.liveHandle).toEqual({ handle: 'h1' })

		vm.releaseLiveSubscription()
		expect(objectStore.unsubscribe).toHaveBeenCalledWith({ handle: 'h1' })
		expect(vm.liveHandle).toBeNull()
		expect(vm.watchers[0].stopped).toBe(true)
	})

	it('does not subscribe a board the user may not see', async () => {
		const vm = board([], vi.fn())
		vm.accessDenied = true
		await vm.syncLiveSubscription('p1')
		expect(objectStore.subscribe).not.toHaveBeenCalled()
	})

	it('drops a subscription that resolves after the board was left', async () => {
		let resolve
		objectStore.subscribe.mockReturnValue(new Promise((done) => {
			resolve = done
		}))
		const vm = board([], vi.fn())
		const pending = vm.syncLiveSubscription('p1')
		vm.releaseLiveSubscription()
		resolve({ handle: 'late' })
		await pending

		expect(objectStore.unsubscribe).toHaveBeenCalledWith({ handle: 'late' })
		expect(vm.liveHandle).toBeNull()
		expect(vm.watchers).toHaveLength(0)
	})

	it('a pushed task update moves the card to its new lane', async () => {
		const fetchTasks = vi.fn().mockResolvedValue([card('t1', 'Bouwtekening controleren', 'doing'), card('t3', 'Nieuw', 'todo')])
		const vm = board([card('t1', 'Bouwtekening controleren', 'todo'), card('t2', 'Weg', 'todo')], fetchTasks)
		await vm.syncLiveSubscription('p1')
		expect(lane(vm.tasks, 'doing')).toEqual([])

		await pushEvent(vm)

		expect(fetchTasks).toHaveBeenCalledWith('p1')
		expect(lane(vm.tasks, 'doing')).toEqual(['Bouwtekening controleren'])
		// A new task appears and a deleted one disappears.
		expect(lane(vm.tasks, 'todo')).toEqual(['Nieuw'])
	})

	it('a dragged card is not moved by a live update', async () => {
		const dragged = card('t1', 'Bouwtekening controleren', 'todo')
		const fetchTasks = vi.fn().mockResolvedValue([card('t1', 'Tekening controleren', 'todo'), card('t2', 'B', 'doing')])
		const vm = board([dragged, card('t2', 'B', 'todo')], fetchTasks)
		await vm.syncLiveSubscription('p1')
		vm.draggingTask = dragged

		await pushEvent(vm)

		expect(vm.tasks.find((task) => task.id === 't1')).toBe(dragged)
		expect(lane(vm.tasks, 'doing')).toEqual(['B'])
		expect(vm.liveHeldUpdate).toBe(true)

		// The drop: the move is saved first, then the waiting update shows.
		const saved = []
		fetchTasks.mockResolvedValue([card('t1', 'Tekening controleren', 'doing'), card('t2', 'B', 'doing')])
		vm.draggingTask = null
		await vm.whileLiveDropping(async () => {
			saved.push(fetchTasks.mock.calls.length)
			vm.tasks = vm.tasks.map((task) => (task.id === 't1' ? { ...task, column: 'doing' } : task))
		})

		expect(saved).toEqual([1])
		expect(fetchTasks).toHaveBeenCalledTimes(2)
		expect(lane(vm.tasks, 'doing')).toEqual(['Tekening controleren', 'B'])
		expect(vm.liveHeldUpdate).toBe(false)
	})

	it('holds a read that arrives while a drop is being saved until the save is done', async () => {
		const fetchTasks = vi.fn().mockResolvedValue([card('t1', 'A', 'doing')])
		const vm = board([card('t1', 'A', 'todo')], fetchTasks)
		await vm.syncLiveSubscription('p1')

		await vm.whileLiveDropping(async () => {
			await pushEvent(vm)
			expect(fetchTasks).not.toHaveBeenCalled()
			expect(vm.liveHeldUpdate).toBe(true)
		})

		expect(fetchTasks).toHaveBeenCalledTimes(1)
		expect(lane(vm.tasks, 'doing')).toEqual(['A'])
	})

	it('reads nothing on a cancelled drag when no update waited', async () => {
		const fetchTasks = vi.fn()
		const vm = board([card('t1', 'A', 'todo')], fetchTasks)
		await vm.syncLiveSubscription('p1')
		await vm.flushHeldLiveUpdate()
		expect(fetchTasks).not.toHaveBeenCalled()
	})

	it('ignores a read that returns after the board was left', async () => {
		let resolve
		const fetchTasks = vi.fn().mockReturnValue(new Promise((done) => {
			resolve = done
		}))
		const tasks = [card('t1', 'A', 'todo')]
		const vm = board(tasks, fetchTasks)
		await vm.syncLiveSubscription('p1')
		const pending = pushEvent(vm)
		vm.releaseLiveSubscription()
		resolve([card('t1', 'A', 'doing')])
		await pending

		expect(vm.tasks).toBe(tasks)
	})
})
