/**
 * Calendar dates as people in the Netherlands read them.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/time-tracking.md
 */

const TIME_ZONE = 'Europe/Amsterdam'

/**
 * The ISO date (YYYY-MM-DD) of a moment in Europe/Amsterdam local time.
 *
 * `toISOString()` gives the UTC date, which is yesterday between midnight and
 * 01:00 (02:00 in summer) Dutch time.
 *
 * @param {Date} [moment] The moment, now when omitted.
 * @return {string} The local date.
 *
 * @spec openspec/specs/time-tracking.md
 */
export function localIsoDate(moment = new Date()) {
	const formatter = new Intl.DateTimeFormat('en-GB', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' })
	const parts = Object.fromEntries(formatter.formatToParts(moment).map(({ type, value }) => [type, value]))
	return `${parts.year}-${parts.month}-${parts.day}`
}
