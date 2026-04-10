<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 KpiCard — Reusable KPI card for the dashboard.

 Displays a label, count, icon, and color accent. Emits a click event
 with the filterValue for parent navigation.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-2
-->
<template>
	<div
		class="kpi-card"
		:class="{ 'kpi-card--loading': loading }"
		:style="accentStyle"
		role="button"
		:tabindex="loading ? -1 : 0"
		:aria-label="ariaText"
		@click="handleClick"
		@keyup.enter="handleClick"
		@keyup.space="handleClick">
		<div class="kpi-card__icon" aria-hidden="true">
			<component :is="icon" :size="24" />
		</div>
		<div class="kpi-card__content">
			<span class="kpi-card__label">{{ label }}</span>
			<span v-if="!loading" class="kpi-card__count">{{ count }}</span>
			<NcLoadingIcon v-else :size="24" />
		</div>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-2
 */
export default {
	name: 'KpiCard',
	components: { NcLoadingIcon },
	props: {
		label: { type: String, required: true },
		count: { type: Number, required: true },
		icon: { type: [Object, Function], required: true },
		color: { type: String, required: true },
		filterValue: { type: String, required: true },
		loading: { type: Boolean, default: false },
	},
	computed: {
		/** @spec openspec/changes/dashboard-my-work/tasks.md#task-14 */
		ariaText() {
			return t('planix', '{label}: {count}', { label: this.label, count: this.count })
		},
		accentStyle() {
			return { borderLeftColor: `var(${this.color})` }
		},
	},
	methods: {
		handleClick() {
			if (!this.loading) {
				this.$emit('click', this.filterValue)
			}
		},
	},
}
</script>

<style scoped>
.kpi-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-left: 4px solid var(--color-primary-element);
	border-radius: var(--border-radius-large, 8px);
	cursor: pointer;
	transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.kpi-card:hover:not(.kpi-card--loading) {
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
	transform: translateY(-1px);
}

.kpi-card:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.kpi-card--loading {
	cursor: default;
	opacity: 0.7;
}

.kpi-card__icon {
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.kpi-card__content {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.kpi-card__label {
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--color-text-maxcontrast);
}

.kpi-card__count {
	font-size: 28px;
	font-weight: 700;
	line-height: 1.1;
	color: var(--color-main-text);
}
</style>
