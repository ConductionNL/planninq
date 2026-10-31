/**
 * Vitest tests for comparing timetable scenarios (timetabling-generator, section 7).
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-7.1
 */
import { describe, expect, it } from 'vitest'
import { bestPositions, compareRows, lessonDiff } from '../../src/utils/scenarioCompare.js'

const generated = {
	source: 'generated',
	metrics: { placed: 5, unplaced: 0, clashes: 0, hardWishesBroken: 0, softWishesBroken: 1, softPenalty: 2, teacherGaps: 1, teacherGapsWorst: 1, lessonsPerDayWorst: 3, roomUse: 0.3 },
	placements: [
		{ lesson: '3A:English:1', period: 'mon-1', room: 'r-1' },
		{ lesson: '3A:English:2', period: 'mon-2', room: 'r-1' },
		{ lesson: '3B:Maths:1', period: 'wed-1', room: 'r-2' },
	],
}
const imported = {
	source: 'imported',
	metrics: { placed: 5, unplaced: 0, clashes: 0, hardWishesBroken: 1, softWishesBroken: 1, softPenalty: 3, teacherGaps: 4, teacherGapsWorst: 2, lessonsPerDayWorst: 3, roomUse: 0.3 },
	placements: [
		{ lesson: '3A:English:1', period: 'mon-1', room: 'r-1' },
		{ lesson: '3A:English:2', period: 'wed-3', room: 'r-1' },
		{ lesson: '3B:Maths:1', period: 'wed-1', room: 'r-1' },
		{ lesson: '3B:Art:1', period: 'mon-4', room: 's-1' },
	],
}

describe('compareRows (scenario: compare a generated and an imported scenario)', () => {
	it('marks the best value per line', () => {
		const rows = Object.fromEntries(compareRows([generated, imported]).map((row) => [row.key, row]))
		expect(rows.hardWishesBroken).toEqual({ key: 'hardWishesBroken', values: [0, 1], best: [0] })
		expect(rows.teacherGaps.best).toEqual([0])
		expect(rows.softPenalty.best).toEqual([0])
	})

	it('marks nothing when the values are equal or the line has no direction', () => {
		const rows = Object.fromEntries(compareRows([generated, imported]).map((row) => [row.key, row]))
		expect(rows.placed.best).toEqual([])
		expect(rows.roomUse.best).toEqual([])
	})

	it('keeps a missing value as null and never marks it', () => {
		const rows = Object.fromEntries(compareRows([generated, { metrics: {} }, imported]).map((row) => [row.key, row]))
		expect(rows.teacherGaps.values).toEqual([1, null, 4])
		expect(rows.teacherGaps.best).toEqual([0])
	})
})

describe('bestPositions', () => {
	it('marks every position that shares the best value', () => {
		expect(bestPositions([2, 1, 1], 'low')).toEqual([1, 2])
		expect(bestPositions([2, 5, 5], 'high')).toEqual([1, 2])
		expect(bestPositions([2, null], 'low')).toEqual([])
	})
})

describe('lessonDiff', () => {
	it('lists the lessons whose period or room differs, a missing lesson as null', () => {
		expect(lessonDiff([generated, imported])).toEqual([
			{ lesson: '3A:English:2', cells: [{ period: 'mon-2', room: 'r-1' }, { period: 'wed-3', room: 'r-1' }] },
			{ lesson: '3B:Art:1', cells: [null, { period: 'mon-4', room: 's-1' }] },
			{ lesson: '3B:Maths:1', cells: [{ period: 'wed-1', room: 'r-2' }, { period: 'wed-1', room: 'r-1' }] },
		])
	})

	it('is empty for two equal scenarios', () => {
		expect(lessonDiff([generated, generated])).toEqual([])
	})
})
