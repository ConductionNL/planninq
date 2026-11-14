// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * @spec openspec/changes/task-attachments-from-files/specs/task-collaboration/spec.md#requirement-a-member-attaches-a-file-that-is-already-in-files
 */
import { describe, expect, it } from 'vitest'
import { linkedFileRefs, linkedFilesPatch } from '../../src/utils/taskLinkedFiles.js'

describe('linkedFileRefs', () => {
	it('keeps the path of each picked file and names it by its basename', () => {
		expect(linkedFileRefs([{ path: '/Projecten/Overleg/Presentielijst.docx' }])).toEqual([
			{ path: '/Projecten/Overleg/Presentielijst.docx', name: 'Presentielijst.docx' },
		])
	})

	it('keeps a given name, drops blanks and repeats, and accepts bare paths', () => {
		expect(linkedFileRefs([
			{ path: '/a/b.pdf', name: 'Begroting' },
			' /a/b.pdf ',
			{ path: '  ' },
			null,
			'/c.txt',
		])).toEqual([
			{ path: '/a/b.pdf', name: 'Begroting' },
			{ path: '/c.txt', name: 'c.txt' },
		])
		expect(linkedFileRefs(undefined)).toEqual([])
	})
})

describe('linkedFilesPatch', () => {
	it('stores the list after a pick', () => {
		expect(linkedFilesPatch([], [{ path: '/Projecten/Overleg/Presentielijst.docx' }])).toEqual({
			linkedFiles: [{ path: '/Projecten/Overleg/Presentielijst.docx', name: 'Presentielijst.docx' }],
		})
	})

	it('stores the shorter list after a removal', () => {
		const current = [{ path: '/a.pdf', name: 'a.pdf' }, { path: '/b.pdf', name: 'b.pdf' }]
		expect(linkedFilesPatch(current, [{ path: '/b.pdf', name: 'b.pdf' }])).toEqual({
			linkedFiles: [{ path: '/b.pdf', name: 'b.pdf' }],
		})
	})

	it('writes nothing when the list did not change', () => {
		expect(linkedFilesPatch([{ path: '/a.pdf' }], [{ path: '/a.pdf', name: 'a.pdf' }])).toEqual({})
	})
})
