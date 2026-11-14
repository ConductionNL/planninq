/**
 * matchesSearch: the board and backlog search (tasks-search-and-bulk, task 1.1).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { matchesSearch } from '../../src/utils/taskHelpers.js'

const LETTER = { title: 'Draft the permit letter', description: 'Send it to the applicant', key: 'VERG-42' }
const CALL = { title: 'Call the applicant', description: null, key: 'VERG-43' }

describe('matchesSearch', () => {
	it('matches the title ignoring case', () => {
		expect([LETTER, CALL].filter((task) => matchesSearch(task, 'PERMIT'))).toEqual([LETTER])
	})

	it('matches the description and the key', () => {
		expect(matchesSearch(LETTER, 'to the APPLICANT')).toBe(true)
		expect(matchesSearch(CALL, 'verg-43')).toBe(true)
		expect(matchesSearch(LETTER, 'verg-43')).toBe(false)
	})

	it('trims the term, and an empty or blank term matches every task', () => {
		expect(matchesSearch(LETTER, '  letter  ')).toBe(true)
		for (const term of ['', '   ', null, undefined]) {
			expect(matchesSearch(CALL, term)).toBe(true)
		}
	})

	it('reads a task without the fields as matching nothing', () => {
		expect(matchesSearch({}, 'x')).toBe(false)
		expect(matchesSearch(null, 'x')).toBe(false)
		expect(matchesSearch({ title: 42 }, '42')).toBe(false)
	})
})
