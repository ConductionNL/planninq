// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Parse a human-friendly duration string into minutes.
 *
 * Accepts formats: "2h 30m", "2.5h", "150m", "150", "2h", "30m"
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-2
 * @param {string} input Raw duration input
 * @return {number|null} Duration in minutes, or null if unparseable
 */
export function parseDuration(input) {
	if (!input || typeof input !== 'string') return null

	const trimmed = input.trim()
	if (!trimmed) return null

	// Try "Xh Ym" or "Xh" or "Ym" patterns
	const hMatch = trimmed.match(/(\d+(?:\.\d+)?)\s*h/i)
	const mMatch = trimmed.match(/(\d+(?:\.\d+)?)\s*m/i)

	if (hMatch || mMatch) {
		const hours = hMatch ? parseFloat(hMatch[1]) : 0
		const mins = mMatch ? parseFloat(mMatch[1]) : 0
		return Math.round(hours * 60 + mins)
	}

	// Plain number — treat as minutes
	const num = parseFloat(trimmed)
	if (!isNaN(num) && num >= 0) {
		return Math.round(num)
	}

	return null
}

/**
 * Format minutes into a human-readable duration string.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-2
 * @param {number} minutes Duration in minutes
 * @return {string} Formatted duration (e.g., "2h 30m")
 */
export function formatDuration(minutes) {
	if (minutes == null || minutes < 0) return ''

	const h = Math.floor(minutes / 60)
	const m = minutes % 60

	if (h === 0) return `${m}m`
	if (m === 0) return `${h}h`
	return `${h}h ${m}m`
}
