<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Move page')"
		@close="$emit('close')">
		<p class="wiki-move__intro">
			{{ t('planninq', 'Choose the page {title} moves under.', { title: page.title }) }}
		</p>
		<NcSelect
			v-model="target"
			:options="options"
			:inputLabel="t('planninq', 'New parent page')"
			label="label"
			data-testid="wiki-move-target" />
		<template #actions>
			<NcButton variant="primary"
				:disabled="!target"
				data-testid="wiki-move-confirm"
				@click="$emit('move', target.id)">
				{{ t('planninq', 'Move') }}
			</NcButton>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcDialog, NcSelect } from '@nextcloud/vue'
import { moveTargets, pageId } from '../utils/wiki.js'

/**
 * WikiPageMoveDialog: pick the page a wiki page moves under. The page, its
 * subpages and its current parent are not offered.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
 */
export default {
	name: 'WikiPageMoveDialog',

	components: { NcButton, NcDialog, NcSelect },

	props: {
		/** The page to move. */
		page: {
			type: Object,
			required: true,
		},

		/** Every page of the project. */
		pages: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'move'],

	data() {
		return { open: true, target: null }
	},

	computed: {
		/**
		 * The allowed new parents, plus the top level when the page sits under something.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
		 */
		options() {
			const list = moveTargets(this.pages, pageId(this.page)).map((page) => ({ id: pageId(page), label: String(page.title || '') }))
			return this.page.parent ? [{ id: '', label: this.t('planninq', 'Top level') }, ...list] : list
		},
	},

	methods: { t },
}
</script>

<style scoped>
.wiki-move__intro {
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}
</style>
