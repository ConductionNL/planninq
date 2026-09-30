<template>
	<section class="task-forge-links" data-testid="task-forge-links">
		<h3 class="task-forge-links__heading">
			{{ t('planninq', 'Code') }}
		</h3>

		<ul v-if="sorted.length > 0" class="task-forge-links__list">
			<li v-for="link in sorted"
				:key="link.id"
				class="task-forge-links__item"
				data-testid="task-forge-link">
				<component :is="kindIcon(link.kind)" :size="20" class="task-forge-links__icon" />
				<span class="task-forge-links__kind">{{ kindLabel(link.kind) }}</span>
				<a :href="link.url"
					class="task-forge-links__title"
					target="_blank"
					rel="noopener noreferrer">{{ link.title || link.url }}</a>
				<span v-if="link.repository" class="task-forge-links__repository">{{ link.repository }}</span>
				<span v-if="link.state" class="task-forge-links__state" :class="`is-${link.state}`">{{ stateLabel(link.state) }}</span>
				<span v-if="link.occurredAt" class="task-forge-links__date">{{ formatDate(link.occurredAt) }}</span>
				<span class="task-forge-links__source">{{ link.source === 'integriq' ? t('planninq', 'From the integration') : t('planninq', 'Added by hand') }}</span>
				<NcButton v-if="mayRemove(link)"
					variant="tertiary"
					:aria-label="t('planninq', 'Remove link {title}', { title: link.title || link.url })"
					:disabled="saving"
					data-testid="task-forge-link-remove"
					@click="remove(link)">
					<template #icon>
						<CloseIcon :size="16" />
					</template>
				</NcButton>
			</li>
		</ul>
		<p v-else-if="!loading" class="task-forge-links__empty">
			{{ emptyText }}
		</p>

		<form class="task-forge-links__add" @submit.prevent="add">
			<NcTextField
				v-model="pasted"
				:label="t('planninq', 'Link to a commit, branch or merge request')"
				:disabled="saving"
				data-testid="task-forge-link-url" />
			<NcButton
				type="submit"
				variant="secondary"
				:disabled="!pasted.trim() || saving"
				data-testid="task-forge-link-add">
				{{ t('planninq', 'Add link') }}
			</NcButton>
		</form>

		<p v-if="errorMessage"
			class="task-forge-links__error"
			role="alert"
			data-testid="task-forge-link-error">
			{{ errorMessage }}
		</p>
	</section>
</template>

<script>
/**
 * TaskForgeLinks: the Code section of the task page. Lists the commits,
 * branches, merge requests and issues linked to the task, newest first, and
 * lets a project member paste a link or remove one added by hand. Links
 * written by the integration are removed by an admin only.
 *
 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
 */
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import BugOutline from 'vue-material-design-icons/BugOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import LinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import SourceBranch from 'vue-material-design-icons/SourceBranch.vue'
import SourceCommit from 'vue-material-design-icons/SourceCommit.vue'
import SourcePull from 'vue-material-design-icons/SourcePull.vue'
import { useProjectsStore } from '../store/projects.js'
import { mayRemoveForgeLink, parseForgeUrl, sortForgeLinks } from '../utils/forgeUrl.js'

const ICONS = { commit: SourceCommit, branch: SourceBranch, mergeRequest: SourcePull, issue: BugOutline, link: LinkVariant }

export default {
	name: 'TaskForgeLinks',

	components: {
		NcButton,
		NcTextField,
		CloseIcon,
	},

	props: {
		/** The task, with its `id`, `key` and `project`. */
		task: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			links: [],
			loading: true,
			saving: false,
			pasted: '',
			errorMessage: '',
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @return {Array<object>} The links, newest first
		 */
		sorted() {
			return sortForgeLinks(this.links)
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @return {string} The empty-state hint, naming the task key when there is one
		 */
		emptyText() {
			return this.task?.key
				? t('planninq', 'No code linked yet. Name {key} in a commit message or paste a link.', { key: this.task.key })
				: t('planninq', 'No code linked yet. Paste a link to a commit or merge request.')
		},
	},

	watch: {
		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 */
		'task.id': function() {
			this.load()
		},
	},

	mounted() {
		this.load()
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 */
		async load() {
			if (!this.task?.id) {
				return
			}
			this.loading = true
			this.links = await useProjectsStore().fetchForgeLinks(this.task.id)
			this.loading = false
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @param {string} kind The link kind
		 * @return {object} The icon component
		 */
		kindIcon(kind) {
			return ICONS[kind] || LinkVariant
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @param {string} kind The link kind
		 * @return {string} The kind in words
		 */
		kindLabel(kind) {
			return {
				commit: t('planninq', 'Commit'),
				branch: t('planninq', 'Branch'),
				mergeRequest: t('planninq', 'Merge request'),
				issue: t('planninq', 'Issue'),
			}[kind] || t('planninq', 'Link')
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @param {string} state The forge state
		 * @return {string} The state in words
		 */
		stateLabel(state) {
			return { open: t('planninq', 'Open'), merged: t('planninq', 'Merged'), closed: t('planninq', 'Closed') }[state] || state
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @param {string} value An ISO date
		 * @return {string} The local date
		 */
		formatDate(value) {
			const date = new Date(value)
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @param {object} link The link
		 * @return {boolean} Whether the viewer may remove it
		 */
		mayRemove(link) {
			return mayRemoveForgeLink(link, getCurrentUser()?.isAdmin === true)
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 */
		async add() {
			this.errorMessage = ''
			const parsed = parseForgeUrl(this.pasted)
			if (!parsed) {
				this.errorMessage = t('planninq', 'Paste a web address that starts with https://.')
				return
			}
			this.saving = true
			const result = await useProjectsStore().saveForgeLink({
				task: this.task.id,
				kind: parsed.kind,
				url: parsed.url,
				title: parsed.reference || parsed.url,
				repository: parsed.repository,
				externalId: parsed.externalId,
				source: 'manual',
			})
			this.saving = false
			if (result.ok) {
				this.pasted = ''
				await this.load()
				return
			}
			this.errorMessage = result.duplicate
				? t('planninq', 'This link is already on the task.')
				: t('planninq', 'The link could not be added.')
		},

		/**
		 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
		 * @param {object} link The link to remove
		 */
		async remove(link) {
			this.errorMessage = ''
			this.saving = true
			const removed = await useProjectsStore().deleteForgeLink(link.id)
			this.saving = false
			if (removed) {
				this.links = this.links.filter((item) => item.id !== link.id)
				return
			}
			this.errorMessage = t('planninq', 'The link could not be removed.')
		},
	},
}
</script>

<style scoped>
.task-forge-links {
	margin-top: calc(var(--default-grid-baseline) * 6);
}

.task-forge-links__list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.task-forge-links__item {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2) 0;
	border-bottom: 1px solid var(--color-border);
}

.task-forge-links__kind,
.task-forge-links__repository,
.task-forge-links__date,
.task-forge-links__source {
	color: var(--color-text-maxcontrast);
}

.task-forge-links__title {
	font-weight: bold;
	text-decoration: underline;
}

.task-forge-links__state {
	padding: 0 calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius-pill);
	border: 1px solid var(--color-border-dark);
}

.task-forge-links__state.is-merged {
	border-color: var(--color-success);
}

.task-forge-links__state.is-closed {
	border-color: var(--color-error);
}

.task-forge-links__empty {
	color: var(--color-text-maxcontrast);
}

.task-forge-links__add {
	display: flex;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 3);
}

.task-forge-links__error {
	color: var(--color-error-text);
}
</style>
