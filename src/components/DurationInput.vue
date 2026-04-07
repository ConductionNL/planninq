<!--
SPDX-License-Identifier: EUPL-1.2
Copyright (C) 2026 Conduction B.V.

@spec openspec/changes/time-tracking-mvp/tasks.md#task-2
-->
<template>
	<div class="duration-input">
		<NcTextField
			v-if="editing"
			ref="input"
			:value="rawValue"
			:label="label"
			:placeholder="placeholder"
			@update:value="onInput"
			@blur="onBlur"
			@keyup.enter="onBlur" />
		<span
			v-else
			class="duration-input__display"
			:title="t('planix', 'Click to edit')"
			tabindex="0"
			@click="startEditing"
			@keyup.enter="startEditing">
			{{ displayValue || placeholder }}
		</span>
	</div>
</template>

<script>
import { NcTextField } from '@nextcloud/vue'
import { parseDuration, formatDuration } from '../utils/duration.js'

/**
 * Duration input component with human-friendly parsing.
 *
 * @spec openspec/changes/time-tracking-mvp/tasks.md#task-2
 */
export default {
	name: 'DurationInput',

	components: { NcTextField },

	props: {
		/** Duration in minutes */
		value: {
			type: Number,
			default: null,
		},
		label: {
			type: String,
			default: '',
		},
		placeholder: {
			type: String,
			default: 'e.g. 2h 30m',
		},
		/** If true, always show the text field (no toggle) */
		inputOnly: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			editing: this.inputOnly,
			rawValue: this.value != null ? formatDuration(this.value) : '',
		}
	},

	computed: {
		displayValue() {
			return this.value != null ? formatDuration(this.value) : ''
		},
	},

	watch: {
		value(val) {
			if (!this.editing) {
				this.rawValue = val != null ? formatDuration(val) : ''
			}
		},
		inputOnly(val) {
			this.editing = val
		},
	},

	methods: {
		startEditing() {
			if (this.inputOnly) return
			this.editing = true
			this.rawValue = this.value != null ? formatDuration(this.value) : ''
			this.$nextTick(() => this.$refs.input?.$el?.querySelector('input')?.focus())
		},

		onInput(val) {
			this.rawValue = val
		},

		onBlur() {
			const minutes = parseDuration(this.rawValue)
			this.$emit('input', minutes)
			if (!this.inputOnly) {
				this.editing = false
			}
			if (minutes != null) {
				this.rawValue = formatDuration(minutes)
			}
		},
	},
}
</script>

<style scoped>
.duration-input__display {
	cursor: pointer;
	padding: 4px 8px;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
}

.duration-input__display:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}
</style>
