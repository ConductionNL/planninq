/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public board's reading of an access-link answer (projects-public-share task 2.1).
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (url, params) => '/index.php' + url.replace('{anchor}', params.anchor),
}))

const { anchorFromPath, boardFromLink, failureState, linkDataUrl, PUBLIC_BOARD_LIMIT } = await import('../../src/utils/publicBoard.js')

describe('anchorFromPath', () => {
	it('reads the anchor of the public board route', () => {
		expect(anchorFromPath('/index.php/apps/planninq/public/Ab12Cd34')).toBe('Ab12Cd34')
		expect(anchorFromPath('/apps/planninq/public/Ab12Cd34/')).toBe('Ab12Cd34')
	})

	it('reads nothing from another path or a malformed anchor', () => {
		expect(anchorFromPath('/apps/planninq/projects/1')).toBeNull()
		expect(anchorFromPath('/apps/planninq/public/ab-12')).toBeNull()
		expect(anchorFromPath(undefined)).toBeNull()
	})
})

describe('linkDataUrl', () => {
	it('points at OpenRegister\'s public link endpoint', () => {
		expect(linkDataUrl('Ab12')).toBe('/index.php/apps/openregister/api/public/links/Ab12')
	})
})

describe('boardFromLink', () => {
	const body = {
		link: { label: 'Fietspad' },
		results: [
			{ id: 'c2', title: 'Done', order: 2, wipLimit: 0 },
			{ id: 't2', title: 'Second', column: 'c1', columnOrder: 2, status: 'open', dueDate: '2026-12-01', labels: ['road', 3] },
			{ id: 'c1', title: 'To do', order: 1, wipLimit: 0 },
			{ id: 't1', title: 'First', column: 'c1', columnOrder: 1, status: 'open' },
			{ id: 't3', title: 'Loose', column: 'gone', columnOrder: 1 },
		],
		total: 5,
	}

	it('draws the columns in board order with their cards in column order', () => {
		const board = boardFromLink(body)
		expect(board.columns.map((column) => column.title)).toEqual(['To do', 'Done'])
		expect(board.columns[0].cards.map((card) => card.title)).toEqual(['First', 'Second'])
		expect(board.columns[1].cards).toEqual([])
	})

	it('keeps only title, status, due date and labels on a card', () => {
		const card = boardFromLink(body).columns[0].cards[1]
		expect(card).toEqual({ id: 't2', title: 'Second', status: 'open', dueDate: '2026-12-01', labels: ['road'] })
	})

	it('keeps a card whose column was not served apart', () => {
		expect(boardFromLink(body).loose.map((card) => card.title)).toEqual(['Loose'])
	})

	it('says when the link served as many objects as it can', () => {
		expect(boardFromLink(body).capped).toBe(false)
		const full = { results: Array.from({ length: PUBLIC_BOARD_LIMIT }, (_, i) => ({ id: 't' + i, title: 'T', column: 'x' })) }
		expect(boardFromLink(full).capped).toBe(true)
	})

	it('reads an empty or malformed answer as an empty board', () => {
		expect(boardFromLink(null)).toEqual({ columns: [], loose: [], capped: false })
		expect(boardFromLink({ results: 'x' })).toEqual({ columns: [], loose: [], capped: false })
	})
})

describe('failureState', () => {
	it('asks for the password on a first 401 and says it is wrong on a second', () => {
		expect(failureState(401, false)).toBe('password')
		expect(failureState(401, true)).toBe('wrongPassword')
	})

	it('reads a revoked, expired or unknown link as gone', () => {
		expect(failureState(404, false)).toBe('gone')
		expect(failureState(404, true)).toBe('gone')
		expect(failureState(undefined, false)).toBe('gone')
	})
})
