/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Files attached to a wiki page (projects-wiki, task 2.3): each is stored as
 * a file of the page through OpenRegister's object files, and the body links
 * it, an image shown from its preview and any other file as a download. A
 * page that was never saved gets no upload; a failed upload does not stop
 * the rest.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-keeps-wiki-pages-next-to-the-tasks
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

const { uploadWikiFile, wikiFileUrls } = await import('../../src/api/wikiPages.js')
const { attachmentMarkdown, filesIn, isImage } = await import('../../src/utils/wikiAttachments.js')
const { attachFiles, insertMarkdown } = await import('../../src/utils/wikiEditing.js')

const BASE = '/apps/openregister/api/objects/planninq/wikiPage'

/**
 * @param {string} name The file name.
 * @param {string} type The mime type.
 * @return {File}
 */
function file(name, type) {
	return new File(['x'], name, { type })
}

beforeEach(() => {
	server.answers = []
	server.calls = []
})

describe('the Markdown of an attached file', () => {
	it('shows an image from its preview and links any other file to its download', () => {
		const urls = { preview: '/p', download: '/d' }
		expect(attachmentMarkdown(file('plattegrond.png', 'image/png'), urls)).toBe('![plattegrond.png](/p)')
		expect(attachmentMarkdown(file('notulen.pdf', 'application/pdf'), urls)).toBe('[notulen.pdf](/d)')
	})

	it('takes out the characters that would end the link text', () => {
		expect(attachmentMarkdown({ name: 'a]b[c\nd.pdf', type: 'application/pdf' }, { preview: '/p', download: '/d' })).toBe('[a b c d.pdf](/d)')
		expect(attachmentMarkdown({ name: '', type: '' }, { preview: '/p', download: '/d' })).toBe('[file](/d)')
	})

	it('knows an image by its type and finds the files of a paste, a drop or a picker', () => {
		expect(isImage(file('a.jpg', 'image/jpeg'))).toBe(true)
		expect(isImage(file('a.txt', 'text/plain'))).toBe(false)
		expect(isImage({})).toBe(false)
		const files = [file('a.txt', 'text/plain'), file('b.png', 'image/png')]
		expect(filesIn({ files })).toEqual(files)
		expect(filesIn(null)).toEqual([])
		expect(filesIn({ files: [null] })).toEqual([])
	})
})

describe('storing a file on the page', () => {
	it('posts the file to the page\'s object files and answers the link to it', async () => {
		server.answers.push([{ id: 41 }])
		const markdown = await uploadWikiFile('pg-1', file('notulen.pdf', 'application/pdf'))
		expect(server.calls).toHaveLength(1)
		expect(server.calls[0][1]).toBe(`${BASE}/pg-1/filesMultipart`)
		expect(server.calls[0][2].get('files[]').name).toBe('notulen.pdf')
		expect(markdown).toBe('[notulen.pdf](/apps/openregister/api/files/41/download)')
	})

	it('shows a stored image from the page\'s preview, also from a results answer', async () => {
		server.answers.push({ results: [{ id: 7 }] })
		expect(await uploadWikiFile('pg-1', file('foto.png', 'image/png'))).toBe(`![foto.png](${BASE}/pg-1/files/7/preview?width=1024&height=1024)`)
		expect(wikiFileUrls('a/b', 3).preview).toBe(`${BASE}/a%2Fb/files/3/preview?width=1024&height=1024`)
	})

	it('refuses an answer that stored nothing', async () => {
		server.answers.push([])
		await expect(uploadWikiFile('pg-1', file('a.pdf', 'application/pdf'))).rejects.toThrow('The file was not stored')
	})
})

describe('attaching files in the editor', () => {
	it('uploads nothing for a page that was never saved', async () => {
		const upload = vi.fn()
		const insert = vi.fn()
		expect(await attachFiles(undefined, [file('a.pdf', 'application/pdf')], upload, insert)).toEqual({ unsaved: true, added: 0, failed: 0 })
		expect(upload).not.toHaveBeenCalled()
		expect(insert).not.toHaveBeenCalled()
	})

	it('inserts each stored file in order and goes on past a failed upload', async () => {
		const upload = vi.fn()
			.mockResolvedValueOnce('[a](/1)')
			.mockRejectedValueOnce(new Error('too large'))
			.mockResolvedValueOnce('[c](/3)')
		const inserted = []
		const files = [file('a', 'text/plain'), file('b', 'text/plain'), file('c', 'text/plain')]
		expect(await attachFiles('pg-1', files, upload, (markdown) => inserted.push(markdown))).toEqual({ unsaved: false, added: 2, failed: 1 })
		expect(upload).toHaveBeenNthCalledWith(1, 'pg-1', files[0])
		expect(upload).toHaveBeenNthCalledWith(3, 'pg-1', files[2])
		expect(inserted).toEqual(['[a](/1)', '[c](/3)'])
	})

	it('puts the link at the Markdown field\'s cursor on a line of its own', () => {
		expect(insertMarkdown(null, 'Eerste\nTweede', 6, '[a](/1)')).toBe('Eerste\n[a](/1)\nTweede')
		expect(insertMarkdown(null, 'Tekst', null, '[a](/1)')).toBe('Tekst\n[a](/1)')
	})

	it('inserts at the Text editor\'s cursor when it offers that, else sets its content', () => {
		const text = { insertAtCursor: vi.fn() }
		expect(insertMarkdown(text, 'Tekst', null, '[a](/1)')).toBe('Tekst\n[a](/1)')
		expect(text.insertAtCursor).toHaveBeenCalledWith('[a](/1)')
		const older = { setContent: vi.fn() }
		expect(insertMarkdown(older, '', null, '[a](/1)')).toBe('[a](/1)')
		expect(older.setContent).toHaveBeenCalledWith('[a](/1)')
	})
})
