<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Page history')"
		size="normal"
		@close="$emit('close')">
		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!entries.length" :name="t('planninq', 'No history yet')" />
		<ol v-else class="wiki-history" data-testid="wiki-history">
			<li v-for="entry in entries" :key="entry.id" class="wiki-history__entry">
				<span class="wiki-history__who">{{ names[entry.user] || entry.user }}</span>
				<span class="wiki-history__when">{{ when(entry.created) }}</span>
				<span v-if="entry.action === 'revert'" class="wiki-history__chip">{{ t('planninq', 'Restored') }}</span>
				<NcButton
					v-if="canRestore"
					variant="tertiary"
					:disabled="busy"
					:data-testid="'wiki-restore-' + entry.id"
					@click="$emit('restore', entry.id)">
					{{ t('planninq', 'Restore this version') }}
				</NcButton>
			</li>
		</ol>
		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcDialog, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'

/**
 * WikiHistoryDialog: the page's audit trail with author and time. A project
 * manager can restore an earlier version, which becomes the newest entry.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
 */
export default {
	name: 'WikiHistoryDialog',

	components: { NcButton, NcDialog, NcEmptyContent, NcLoadingIcon },

	props: {
		/** The history entries, newest first. */
		entries: {
			type: Array,
			default: () => [],
		},

		/** User id to display name. */
		names: {
			type: Object,
			default: () => ({}),
		},

		/** Whether the entries are still loading. */
		loading: {
			type: Boolean,
			default: false,
		},

		/** Whether the person may restore a version (project managers). */
		canRestore: {
			type: Boolean,
			default: false,
		},

		/** Whether a restore is running. */
		busy: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'restore'],

	data() {
		return { open: true }
	},

	methods: {
		t,

		/**
		 * @param {string} iso A timestamp.
		 * @return {string} The time in the person's locale.
		 * @spec exclude Display helper — formats a timestamp.
		 */
		when(iso) {
			const date = new Date(iso)
			return Number.isNaN(date.getTime()) ? iso : date.toLocaleString()
		},
	},
}
</script>

<style scoped>
.wiki-history {
	list-style: none;
	margin: 0;
	padding: 0;
}

.wiki-history__entry {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 3);
	padding-block: calc(var(--default-grid-baseline) * 2);
	border-bottom: 1px solid var(--color-border);
}

.wiki-history__when {
	color: var(--color-text-maxcontrast);
}

.wiki-history__chip {
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-pill);
	padding-inline: calc(var(--default-grid-baseline) * 2);
}
</style>
