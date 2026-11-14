/**
 * Unit tests for the work type list helpers.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { addWorkType, renameWorkType, workTypeNames } from '../../src/utils/workTypes.js'

describe('workTypeNames', () => {
	it('reads the stored JSON list, trimmed and without repeats', () => {
		expect(workTypeNames('["Development", " Meeting ", "meeting", "", 3]')).toEqual(['Development', 'Meeting'])
		expect(workTypeNames(['Support'])).toEqual(['Support'])
	})

	it('reads nothing from an empty, missing or broken setting', () => {
		expect(workTypeNames('[]')).toEqual([])
		expect(workTypeNames(undefined)).toEqual([])
		expect(workTypeNames('not json')).toEqual([])
		expect(workTypeNames('{"a":"b"}')).toEqual([])
	})
})

describe('editing the list', () => {
	it('adds a new name at the end and refuses a blank or repeated one', () => {
		expect(addWorkType(['Development'], ' Meeting ')).toEqual(['Development', 'Meeting'])
		expect(addWorkType(['Development'], 'development')).toBeNull()
		expect(addWorkType(['Development'], '  ')).toBeNull()
	})

	it('renames in place and refuses a blank name or one another type has', () => {
		expect(renameWorkType(['Development', 'Meeting'], 'Meeting', 'Overleg')).toEqual(['Development', 'Overleg'])
		expect(renameWorkType(['Development', 'Meeting'], 'Meeting', 'meeting')).toEqual(['Development', 'meeting'])
		expect(renameWorkType(['Development', 'Meeting'], 'Meeting', 'development')).toBeNull()
		expect(renameWorkType(['Development', 'Meeting'], 'Meeting', '')).toBeNull()
	})
})
