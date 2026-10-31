/**
 * Vitest unit tests for the portfolio overview helpers: the roll-up per
 * aspect with its worst-state rule and the "No report" count, the out-of-date
 * marker and who sees money (portfolio-status-overview, section 2).
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { canSeeMoney, isOutOfDate, portfolioRollup, rollupAspect } from '../../src/utils/portfolioStatus.js'

const reported = (money, date = '2026-09-20') => ({ healthDate: date, healthMoney: money, healthTime: 'onTrack' })

describe('rollupAspect (scenario: the roll-up of a portfolio)', () => {
	it('counts two on track, one off track and one without a report, and the portfolio is off track', () => {
		const projects = [reported('onTrack'), reported('onTrack'), reported('offTrack'), { title: 'Never reported' }]
		expect(rollupAspect(projects, 'money')).toEqual({ onTrack: 2, atRisk: 0, offTrack: 1, noReport: 1, state: 'offTrack' })
	})

	it('never counts a project without a report as on track, and has no state without any report', () => {
		expect(rollupAspect([{}, { healthMoney: 'onTrack' }], 'money')).toEqual({ onTrack: 0, atRisk: 0, offTrack: 0, noReport: 2, state: null })
	})

	it('takes the worst state: at risk beats on track', () => {
		expect(rollupAspect([reported('onTrack'), reported('atRisk')], 'money').state).toBe('atRisk')
	})
})

describe('portfolioRollup', () => {
	it('rolls up all six aspects in their fixed order', () => {
		const rows = portfolioRollup([reported('offTrack')])
		expect(rows.map((row) => row.aspect)).toEqual(['money', 'organisation', 'time', 'information', 'quality', 'risk'])
		expect(rows[0].state).toBe('offTrack')
		expect(rows[2].state).toBe('onTrack')
		// A report that leaves an aspect empty counts it as neither state nor missing report.
		expect(rows[1]).toEqual({ aspect: 'organisation', onTrack: 0, atRisk: 0, offTrack: 0, noReport: 0, state: null })
	})
})

describe('isOutOfDate', () => {
	it('marks a report older than the period, not one on the last day', () => {
		expect(isOutOfDate('2026-08-30', '2026-09-29', 30)).toBe(false)
		expect(isOutOfDate('2026-08-29', '2026-09-29', 30)).toBe(true)
		expect(isOutOfDate('2026-08-29T10:00:00+00:00', '2026-09-29', 30)).toBe(true)
		expect(isOutOfDate('', '2026-09-29', 30)).toBe(false)
	})

	it('falls back to thirty days when the setting is missing or nonsense', () => {
		expect(isOutOfDate('2026-08-29', '2026-09-29', undefined)).toBe(true)
		expect(isOutOfDate('2026-09-01', '2026-09-29', 'abc')).toBe(false)
	})
})

describe('canSeeMoney', () => {
	const portfolio = { id: 'p-r', managers: ['mia'] }
	const project = { owner: 'carol', members: ['bob'] }
	it('shows money to admins, the project owner and the portfolio managers only', () => {
		expect(canSeeMoney(project, portfolio, { uid: 'root', isAdmin: true })).toBe(true)
		expect(canSeeMoney(project, portfolio, { uid: 'carol' })).toBe(true)
		expect(canSeeMoney(project, portfolio, { uid: 'mia' })).toBe(true)
		expect(canSeeMoney(project, portfolio, { uid: 'bob' })).toBe(false)
		expect(canSeeMoney(project, null, { uid: 'mia' })).toBe(false)
		expect(canSeeMoney(project, portfolio, null)).toBe(false)
	})
})
