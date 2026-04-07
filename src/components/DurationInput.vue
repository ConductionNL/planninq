<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-2
-->
<template>
	<div class="duration-input">
		<NcTextField
			v-if="!displayOnly"
			:value="inputValue"
			:label="label"
			:placeholder="placeholder"
			:helper-text="helperText"
			:error="!!validationError"
			@update:value="onInput"
			@blur="onBlur" />
		<span v-else class="duration-input__display">
			{{ formattedDuration }}
		</span>
	</div>
</template>

<script>
/**
 * Duration input component with human-friendly parsing.
 *
 * Accepts formats like "2h 30m", "2.5h", "150m", "150" (minutes)
 * and emits the value in minutes.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-2
 */
import { NcTextField } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

/**
 * Parse a human-friendly duration string into minutes.
 *
 * Supported formats:
 * - "2h 30m" → 150
 * - "2h30m"  → 150
 * - "2.5h"   → 150
 * - "150m"   → 150
 * - "150"    → 150 (bare number = minutes)
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-2
 * @param {string} input Raw user input
 * @return {number|null} Duration in minutes, or null if unparseable
 */
export function parseDuration(input) {
	if (!input || typeof input !== 'string') return null
	const str = input.trim().toLowerCase()
	if (!str) return null

	// Pattern: "Xh Ym" or "XhYm"
	const hm = str.match(/^(\d+(?:\.\d+)?)\s*h\s*(?:(\d+)\s*m?)?$/)
	if (hm) {
		const hours = parseFloat(hm[1])
		const mins = hm[2] ? parseInt(hm[2], 10) : 0
		return Math.round(hours * 60 + mins)
	}

	// Pattern: "Xm"
	const mOnly = str.match(/^(\d+)\s*m$/)
	if (mOnly) {
		return parseInt(mOnly[1], 10)
	}

	// Bare number = minutes
	const bare = str.match(/^(\d+)$/)
	if (bare) {
		return parseInt(bare[1], 10)
	}

	return null
}

/**
 * Format minutes into a human-readable duration string.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-2
 * @param {number} minutes Duration in minutes
 * @return {string} Formatted string (e.g. "2h 30m")
 */
export function formatDuration(minutes) {
	if (minutes == null || minutes < 0) return ''
	const h = Math.floor(minutes / 60)
	const m = minutes % 60
	if (h === 0) return `${m}m`
	if (m === 0) return `${h}h`
	return `${h}h ${m}m`
}

export default {
	name: 'DurationInput',

	components: { NcTextField },

	props: {
		/** Duration value in minutes */
		value: {
			type: Number,
			default: null,
		},
		/** Input label */
		label: {
			type: String,
			default: '',
		},
		/** Placeholder text */
		placeholder: {
			type: String,
			default: 'e.g. 2h 30m',
		},
		/** Display-only mode (no input, just formatted text) */
		displayOnly: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			inputValue: this.value != null ? formatDuration(this.value) : '',
			validationError: false,
		}
	},

	computed: {
		formattedDuration() {
			return formatDuration(this.value)
		},
		helperText() {
			if (this.validationError) {
				return t('planix', 'Use formats like 2h 30m, 2.5h, 150m, or 150')
			}
			return ''
		},
	},

	watch: {
		value(newVal) {
			// Sync from parent when value changes externally.
			if (newVal != null) {
				this.inputValue = formatDuration(newVal)
			} else {
				this.inputValue = ''
			}
			this.validationError = false
		},
	},

	methods: {
		t,

		onInput(val) {
			this.inputValue = val
			this.validationError = false
		},

		onBlur() {
			if (!this.inputValue.trim()) {
				this.$emit('input', null)
				this.validationError = false
				return
			}
			const minutes = parseDuration(this.inputValue)
			if (minutes !== null) {
				this.inputValue = formatDuration(minutes)
				this.$emit('input', minutes)
				this.validationError = false
			} else {
				this.validationError = true
			}
		},
	},
}
</script>

<style scoped>
.duration-input__display {
	font-variant-numeric: tabular-nums;
}
</style>
