<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Delete page')"
		@close="$emit('close')">
		<p v-if="hasSubpages">
			{{ t('planninq', '{title} has subpages. Move them up a level, or delete them too?', { title }) }}
		</p>
		<p v-else>
			{{ t('planninq', 'Delete {title}? This cannot be undone.', { title }) }}
		</p>
		<template #actions>
			<NcButton v-if="hasSubpages"
				variant="primary"
				data-testid="wiki-delete-promote"
				@click="$emit('remove', 'promote')">
				{{ t('planninq', 'Delete and move subpages up') }}
			</NcButton>
			<NcButton variant="error" data-testid="wiki-delete-confirm" @click="$emit('remove', 'cascade')">
				{{ hasSubpages ? t('planninq', 'Delete with subpages') : t('planninq', 'Delete') }}
			</NcButton>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcDialog } from '@nextcloud/vue'

/**
 * WikiPageDeleteDialog: confirms deleting a wiki page, and when it has
 * subpages, whether to move them up a level or delete them too.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
 */
export default {
	name: 'WikiPageDeleteDialog',

	components: { NcButton, NcDialog },

	props: {
		/** Title of the page to delete. */
		title: {
			type: String,
			default: '',
		},

		/** Whether the page has subpages. */
		hasSubpages: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'remove'],

	data() {
		return { open: true }
	},

	methods: { t },
}
</script>
