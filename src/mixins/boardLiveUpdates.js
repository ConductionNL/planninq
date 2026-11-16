// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Live updates for the project board (board-live-updates).
 *
 * The board subscribes to the task collection through the live updates plugin
 * of the planninq object store. An event is a hint only: on it the board
 * reads its project's tasks again (every page, through the projects store) and
 * merges them in with mergeLiveTasks, so a colleague's moved card changes
 * lane, a new task appears and a deleted one disappears. The card being
 * dragged is held: a read during the drag leaves it where the pointer has it,
 * and the board reads again once it is dropped, so the colleague's change
 * shows then.
 *
 * The host component provides `tasks`, `draggingTask`, `accessDenied`,
 * `projectsStore` and the route's project id; it calls
 * `syncLiveSubscription(projectId)` once its tasks are loaded, and
 * `releaseLiveSubscription()` on leave.
 *
 * @spec openspec/changes/board-live-updates/tasks.md#task-1.1
 */
import { useObjectStore } from '../store/objectStore.js'
import { liveTaskId, mergeLiveTasks } from '../utils/boardLive.js'

/**
 * The object type the board subscribes to.
 *
 * @type {string}
 */
export const LIVE_TASK_TYPE = 'task'

export default {
	data() {
		return {
			/** @type {object|null} The live subscription handle of the task collection. */
			liveHandle: null,
			/** @type {boolean} Whether a subscribe is in flight. */
			liveSubscribing: false,
			/** @type {number} Bumped on release, so a late subscribe or read is dropped. */
			liveEpoch: 0,
			/** @type {(() => void)|null} Stops the watcher on the store's live events. */
			liveUnwatch: null,
			/** @type {string} The project the subscription reads the tasks of. */
			liveProjectId: '',
			/** @type {boolean} Whether an update for the dragged card waits for the drop. */
			liveHeldUpdate: false,
			/** @type {boolean} Whether a dropped card is being saved; reads wait until it is. */
			liveDropping: false,
		}
	},

	methods: {
		/**
		 * Subscribe the board to the task collection, once.
		 *
		 * @param {string} projectId The board's project
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/board-live-updates/tasks.md#task-1.1
		 */
		async syncLiveSubscription(projectId) {
			const objectStore = useObjectStore()
			if (!projectId || this.accessDenied || typeof objectStore.subscribe !== 'function') {
				return
			}
			if (this.liveHandle || this.liveSubscribing) {
				return
			}
			this.liveProjectId = String(projectId)
			const epoch = this.liveEpoch
			this.liveSubscribing = true
			try {
				// Registers the task type, with the slug hints the event key needs.
				this.projectsStore._objectStore()
				const handle = await objectStore.subscribe(LIVE_TASK_TYPE)
				if (this.liveEpoch !== epoch) {
					// Left the board while subscribing: drop the late subscription.
					objectStore.unsubscribe(handle)
					return
				}
				this.liveHandle = handle
				// Every live event stamps liveLastEventAt; the board's own reads do not,
				// so reading again on it never feeds itself.
				this.liveUnwatch = this.$watch(
					() => objectStore.liveLastEventAt,
					() => this.onLiveTasksHint(),
				)
			} catch (e) {
				this.liveHandle = null
				console.warn('[ProjectBoard] live subscription failed:', e?.message ?? e)
			} finally {
				if (this.liveEpoch === epoch) {
					this.liveSubscribing = false
				}
			}
		},

		/**
		 * Read the project's tasks again and merge them into the board, holding the dragged card.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/board-live-updates/tasks.md#task-1.1
		 * @spec openspec/changes/board-live-updates/tasks.md#task-1.2
		 */
		async onLiveTasksHint() {
			if (!this.liveHandle || !this.liveProjectId) {
				return
			}
			if (this.liveDropping) {
				// A read now could undo the move being saved: read once the drop is stored.
				this.liveHeldUpdate = true
				return
			}
			const epoch = this.liveEpoch
			const fresh = await this.projectsStore.fetchTasks(this.liveProjectId)
			if (this.liveEpoch !== epoch || !this.liveHandle) {
				return
			}
			if (this.liveDropping) {
				this.liveHeldUpdate = true
				return
			}
			const heldId = liveTaskId(this.draggingTask)
			const { tasks, held } = mergeLiveTasks(this.tasks, fresh, heldId)
			this.tasks = tasks
			if (held) {
				this.liveHeldUpdate = true
			} else if (!heldId) {
				this.liveHeldUpdate = false
			}
		},

		/**
		 * After a drop or a cancelled drag: read again when an update waited for the card.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/board-live-updates/tasks.md#task-1.2
		 */
		async flushHeldLiveUpdate() {
			if (!this.liveHeldUpdate || this.draggingTask || this.liveDropping) {
				return
			}
			this.liveHeldUpdate = false
			await this.onLiveTasksHint()
		},

		/**
		 * Save a drop with live reads held, then read what waited for it.
		 *
		 * @param {() => Promise<void>} save Saves the drop; its promise settles once the move is stored.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/board-live-updates/tasks.md#task-1.2
		 */
		async whileLiveDropping(save) {
			this.liveDropping = true
			try {
				await save()
			} finally {
				this.liveDropping = false
			}
			await this.flushHeldLiveUpdate()
		},

		/**
		 * Release the subscription and its watcher; a subscribe or read still in flight is dropped.
		 *
		 * @spec openspec/changes/board-live-updates/tasks.md#task-1.1
		 */
		releaseLiveSubscription() {
			this.liveEpoch += 1
			this.liveSubscribing = false
			this.liveHeldUpdate = false
			this.liveDropping = false
			if (this.liveUnwatch) {
				this.liveUnwatch()
				this.liveUnwatch = null
			}
			const objectStore = useObjectStore()
			if (this.liveHandle && typeof objectStore.unsubscribe === 'function') {
				objectStore.unsubscribe(this.liveHandle)
			}
			this.liveHandle = null
		},
	},
}
