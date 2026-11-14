/**
 * Tests for images pasted or dropped into a task description: each is stored
 * as a file of the task through OpenRegister and linked in the Markdown.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/task-description-images/specs/task-editing/spec.md
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { answers: [], calls: [] }

vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		post: async (url, body) => {
			server.calls.push(['post', url, body])
			const next = server.answers.shift()
			if (next instanceof Error) {
				throw next
			}
			return { data: next }
		},
	},
}))

const { taskImageUrl, uploadTaskImage } = await import('../../src/api/taskFiles.js')
const { imageMarkdown, imagesIn, insertAt } = await import('../../src/utils/descriptionImages.js')
const descriptionImages = (await import('../../src/mixins/descriptionImages.js')).default

const PREVIEW = '/apps/openregister/api/objects/planninq/task/t-1/files/41/preview?width=1024&height=1024'

/**
 * A pasted file.
 *
 * @param {string} name The file name.
 * @param {string} type The mime type.
 * @return {File}
 */
function fileOf(name, type) {
	return new File(['bytes'], name, { type })
}

/**
 * The dialog's methods over a plain state, as Vue would bind them.
 *
 * @param {object|null} task The edited task.
 * @param {string} description The draft description.
 * @return {object}
 */
function dialog(task, description) {
	const vm = {
		task,
		draft: { description },
		imageNotice: '',
		t: (app, text) => text,
	}
	for (const [name, method] of Object.entries(descriptionImages.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

describe('task description images', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('a pasted image becomes a file and a link', async () => {
		server.answers.push([{ id: 41, title: 'screenshot.png' }])
		const vm = dialog({ id: 't-1' }, 'Zie hieronder.')
		const event = {
			clipboardData: { files: [fileOf('screenshot.png', 'image/png')] },
			target: { selectionStart: 14 },
			preventDefault: vi.fn(),
		}

		await vm.onDescriptionPaste(event)

		expect(event.preventDefault).toHaveBeenCalled()
		expect(server.calls).toHaveLength(1)
		expect(server.calls[0][1]).toBe('/apps/openregister/api/objects/planninq/task/t-1/filesMultipart')
		expect(server.calls[0][2].getAll('files[]').map((f) => f.name)).toEqual(['screenshot.png'])
		expect(vm.draft.description).toBe(`Zie hieronder.\n![screenshot.png](${PREVIEW})`)
		expect(vm.imageNotice).toBe('')
	})

	it('a dropped image is inserted where the cursor was, on a line of its own', async () => {
		server.answers.push([{ id: 41 }])
		const vm = dialog({ id: 't-1' }, 'Voor\nNa')
		const event = {
			dataTransfer: { files: [fileOf('plan.jpg', 'image/jpeg')] },
			target: { selectionStart: 4 },
			preventDefault: vi.fn(),
		}

		await vm.onDescriptionDrop(event)

		expect(vm.draft.description).toBe(`Voor\n![plan.jpg](${PREVIEW})\nNa`)
	})

	it('a paste without images is left to the text area', async () => {
		const vm = dialog({ id: 't-1' }, 'tekst')
		const event = { clipboardData: { files: [fileOf('notes.txt', 'text/plain')] }, preventDefault: vi.fn() }

		await vm.onDescriptionPaste(event)

		expect(event.preventDefault).not.toHaveBeenCalled()
		expect(server.calls).toEqual([])
		expect(vm.draft.description).toBe('tekst')
	})

	it('a new task asks to be saved first and uploads nothing', async () => {
		const vm = dialog(null, '')

		await vm.addImages([fileOf('a.png', 'image/png')], 0)

		expect(server.calls).toEqual([])
		expect(vm.draft.description).toBe('')
		expect(vm.imageNotice).toBe('Save the task first, then paste or drop images into its description.')
	})

	it('a failed upload says so and leaves the description as it was', async () => {
		server.answers.push(new Error('413'))
		const vm = dialog({ id: 't-1' }, 'tekst')
		const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})

		await vm.addImages([fileOf('a.png', 'image/png')], 5)

		expect(vm.draft.description).toBe('tekst')
		expect(vm.imageNotice).toBe('The image could not be uploaded.')
		consoleError.mockRestore()
	})

	it('an answer without a stored file is an error', async () => {
		server.answers.push({ results: [] })

		await expect(uploadTaskImage('t-1', fileOf('a.png', 'image/png'))).rejects.toThrow('The image was not stored')
	})

	it('only image files count, and drag-over accepts files only', () => {
		expect(imagesIn({ files: [fileOf('a.png', 'image/png'), fileOf('b.pdf', 'application/pdf')] }).map((f) => f.name)).toEqual(['a.png'])
		expect(imagesIn(null)).toEqual([])

		const vm = dialog({ id: 't-1' }, '')
		const files = { dataTransfer: { types: ['Files'] }, preventDefault: vi.fn() }
		const text = { dataTransfer: { types: ['text/plain'] }, preventDefault: vi.fn() }
		vm.onDescriptionDragOver(files)
		vm.onDescriptionDragOver(text)
		expect(files.preventDefault).toHaveBeenCalled()
		expect(text.preventDefault).not.toHaveBeenCalled()
	})

	it('builds the preview address, a safe alternative text and inserts at the end when the cursor is unknown', () => {
		expect(taskImageUrl('t-1', 41)).toBe(PREVIEW)
		expect(imageMarkdown('a]b[c\n.png', '/x')).toBe('![a b c .png](/x)')
		expect(insertAt('tekst', undefined, '![a](/x)')).toBe('tekst\n![a](/x)')
		expect(insertAt('', 0, '![a](/x)')).toBe('![a](/x)')
	})
})
