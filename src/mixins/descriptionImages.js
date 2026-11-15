/**
 * Images pasted or dropped into a task description: each is stored as a file
 * of the task through OpenRegister and linked in the Markdown where the cursor
 * was (task-description-images). The host keeps the task in `task` and the
 * Markdown in `draft.description`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/task-description-images/specs/task-editing/spec.md
 */
import { imagesIn, insertAt } from '../utils/descriptionImages.js'

export default {
	data() {
		return {
			imageNotice: '',
		}
	},

	methods: {
		/**
		 * Store the images of a paste as files of the task and link them where
		 * the cursor is. A paste without images is left to the text area.
		 *
		 * @param {ClipboardEvent} event The paste.
		 * @spec openspec/changes/task-description-images/tasks.md#1.1
		 */
		onDescriptionPaste(event) {
			const images = imagesIn(event?.clipboardData)
			if (images.length === 0) {
				return
			}
			event.preventDefault()
			return this.addImages(images, event.target?.selectionStart)
		},

		/**
		 * Let files be dropped on the description.
		 *
		 * @param {DragEvent} event The drag.
		 * @spec openspec/changes/task-description-images/tasks.md#1.1
		 */
		onDescriptionDragOver(event) {
			if (Array.from(event?.dataTransfer?.types ?? []).includes('Files')) {
				event.preventDefault()
			}
		},

		/**
		 * Store the dropped images as files of the task and link them.
		 *
		 * @param {DragEvent} event The drop.
		 * @spec openspec/changes/task-description-images/tasks.md#1.1
		 */
		onDescriptionDrop(event) {
			const images = imagesIn(event?.dataTransfer)
			if (images.length === 0) {
				return
			}
			event.preventDefault()
			return this.addImages(images, event.target?.selectionStart)
		},

		/**
		 * Upload each image to the task and insert its Markdown link. A new
		 * task has no files yet, so it is saved first.
		 *
		 * @param {File[]} images The images.
		 * @param {number|undefined} at Where the cursor was.
		 * @spec openspec/changes/task-description-images/specs/task-editing/spec.md
		 */
		async addImages(images, at) {
			if (!this.task?.id) {
				this.imageNotice = this.t('planninq', 'Save the task first, then paste or drop images into its description.')
				return
			}
			this.imageNotice = ''
			// Loaded when an image arrives, so the dialog does not pull the
			// HTTP client in until it uploads something.
			const { uploadTaskImage } = await import('../api/taskFiles.js')
			let position = at
			for (const image of images) {
				try {
					const markdown = await uploadTaskImage(this.task.id, image)
					const before = this.draft.description
					this.draft.description = insertAt(before, position, markdown)
					position = Number.isInteger(position) ? position + (this.draft.description.length - before.length) : position
				} catch (error) {
					console.error('task description image upload failed:', error)
					this.imageNotice = this.t('planninq', 'The image could not be uploaded.')
				}
			}
		},
	},
}
