<template>
	<!-- role="option" is not focusable by default, so the click handler was
	     mouse-only. tabindex + the two activation keys give it the keyboard
	     path its role implies (WCAG 2.2 2.1.1). -->
	<li
		class="project-list-item"
		role="option"
		tabindex="0"
		:aria-selected="false"
		@click="$emit('click', project)"
		@keydown.enter="$emit('click', project)"
		@keydown.space.prevent="$emit('click', project)">
		<!-- Color swatch -->
		<span
			class="project-list-item__swatch"
			:style="{ backgroundColor: project.color || 'var(--color-primary)' }"
			:aria-label="t('planix', 'Project color: {color}', { color: project.color || 'default' })" />

		<!-- Icon / emoji -->
		<span class="project-list-item__icon" aria-hidden="true">
			{{ project.icon || '📁' }}
		</span>

		<!-- Title and description -->
		<span class="project-list-item__body">
			<span class="project-list-item__title">{{ project.title }}</span>
			<span v-if="project.description" class="project-list-item__description">
				{{ project.description }}
			</span>
		</span>

		<!-- Member count -->
		<span
			class="project-list-item__badge"
			:aria-label="t('planix', '{count} members', { count: memberCount })">
			{{ memberCount }} {{ t('planix', 'members') }}
		</span>

		<!-- Status chip -->
		<NcChip
			class="project-list-item__status"
			:text="statusLabel"
			:variant="statusVariant"
			:no-close="true" />
	</li>
</template>

<script>
// @nextcloud/vue@9 removed the `dist/Components/*.js` layout; the package now
// publishes only an `exports` map (root barrel + `./components/<Name>`).
import { NcChip } from '@nextcloud/vue'

export default {
	name: 'ProjectListItem',
	components: { NcChip },

	props: {
		project: {
			type: Object,
			required: true,
		},
	},

	emits: ['click'],

	computed: {
		/**
		 * @spec openspec/changes/retrofit-2026-05-26-planix-display-capabilities/tasks.md#task-4
		 */
		memberCount() {
			return Array.isArray(this.project.members) ? this.project.members.length : 0
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-26-planix-display-capabilities/tasks.md#task-1
		 */
		statusLabel() {
			const map = {
				active: this.t('planix', 'Active'),
				archived: this.t('planix', 'Archived'),
				completed: this.t('planix', 'Completed'),
			}
			return map[this.project.status] || this.project.status || this.t('planix', 'Active')
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-26-planix-display-capabilities/tasks.md#task-2
		 */
		statusVariant() {
			// `secondary` is NcChip's own default variant. The pre-migration
			// code returned 'default', which was never a valid NcChip value in
			// either major — it only ever tripped the prop validator and fell
			// through to the base styling.
			const map = { active: 'success', archived: 'warning', completed: 'secondary' }
			return map[this.project.status] || 'secondary'
		},
	},
}
</script>

<style scoped>
.project-list-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 16px;
	cursor: pointer;
	border-radius: var(--border-radius);
	transition: background-color 0.1s;
	list-style: none;
}

/* WCAG 2.2 2.3.3 — the hover/focus colour still changes, just not gradually. */
@media (prefers-reduced-motion: reduce) {
	.project-list-item {
		transition: none;
	}
}

.project-list-item:hover {
	background-color: var(--color-background-hover);
}

.project-list-item__swatch {
	flex-shrink: 0;
	width: 16px;
	height: 16px;
	border-radius: 50%;
	display: inline-block;
}

.project-list-item__icon {
	flex-shrink: 0;
	font-size: 18px;
	line-height: 1;
}

.project-list-item__body {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.project-list-item__title {
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-list-item__description {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-list-item__badge {
	flex-shrink: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.project-list-item__status {
	flex-shrink: 0;
}
</style>
