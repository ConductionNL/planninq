/**
 * The save step of an edit dialog: it marks the dialog as saving, clears
 * the last error, runs the write, and then shows the failure text when the
 * write returns nothing or emits `saved` with what it returned.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec exclude Shared save frame of the edit dialogs; each dialog's spec covers what it writes.
 */
export default {
	data() {
		return {
			saving: false,
			submitError: '',
		}
	},

	methods: {
		/**
		 * Run a write, show the failure text when it returns nothing, and
		 * emit `saved` with its result otherwise.
		 *
		 * @param {function(): Promise<object|null>} write - builds the payload and writes it
		 * @param {string} failure - the translated text shown when the write returns nothing
		 *
		 * @spec exclude Shared save frame of the edit dialogs; each dialog's spec covers what it writes.
		 */
		async submitSave(write, failure) {
			this.saving = true
			this.submitError = ''
			const saved = await write()
			this.saving = false
			if (!saved) {
				this.submitError = failure
				return
			}
			this.$emit('saved', saved)
		},
	},
}
