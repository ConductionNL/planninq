<template>
	<li
		class="project-list-item"
		role="option"
		:aria-selected="false"
		@click="$emit('click', project)">
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
			:type="statusType"
			:no-close="true" />
	</li>
</template>

<script>
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'

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
		 * @spec exclude Trivial display formatter — counts the members array length.
		 */
		memberCount() {
			return Array.isArray(this.project.members) ? this.project.members.length : 0
		},

		/**
		 * @spec exclude Trivial display formatter — maps status to a translated label.
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
		 * @spec exclude Trivial display formatter — maps status to an NcChip type.
		 */
		statusType() {
			const map = { active: 'success', archived: 'warning', completed: 'default' }
			return map[this.project.status] || 'default'
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
