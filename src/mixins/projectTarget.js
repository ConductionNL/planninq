/**
 * The target-project state of the move and copy dialogs: the projects the
 * object may go to, the picked one, and the write that moves it there.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
import { idOf } from '../utils/taskMove.js'

export default {
	data() {
		return {
			loading: true,
			targets: [],
			target: null,
			busy: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the target projects as select options.
		 */
		targetOptions() {
			return this.targets.map((project) => ({ id: idOf(project), label: project.title || idOf(project) }))
		},

		/**
		 * @spec exclude Trivial getter: the chosen target project.
		 */
		targetProject() {
			return this.targets.find((project) => idOf(project) === this.target?.id) || null
		},
	},

	methods: {
		/**
		 * Run the move to the picked project: nothing without a pick, the
		 * buttons disabled while it runs, the last error cleared first, and the
		 * failure text shown when the write is refused.
		 *
		 * @param {function(object): Promise<{ok: boolean}>} write The write, given the target project.
		 * @param {string} failure The translated text shown when the write is refused.
		 * @return {Promise<object|null>} The write's result when it went through, else null.
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
		 */
		async runMove(write, failure) {
			if (!this.targetProject) {
				return null
			}
			this.busy = true
			this.submitError = ''
			const result = await write(this.targetProject)
			this.busy = false
			if (!result.ok) {
				this.submitError = failure
				return null
			}
			return result
		},
	},
}
