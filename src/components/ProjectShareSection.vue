<!--
SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
SPDX-License-Identifier: EUPL-1.2
-->
<template>
	<div class="project-share" data-testid="project-share">
		<p class="project-share__hint">
			{{ t('planninq', 'Anyone with a link sees the board\'s cards: title, status, due date and labels. People and descriptions are never shown.') }}
		</p>

		<form class="project-share__create" data-testid="project-share-create" @submit.prevent="create">
			<NcTextField
				v-model="endDate"
				type="date"
				:label="t('planninq', 'End date')"
				:min="tomorrow"
				:max="latest"
				:error="!!endDateMessage"
				:helperText="endDateMessage"
				data-testid="project-share-end-date" />
			<NcPasswordField
				v-model="password"
				:label="t('planninq', 'Password')"
				autocomplete="new-password"
				data-testid="project-share-password" />
			<NcButton
				type="submit"
				variant="primary"
				:disabled="creating || !!endDateMessage"
				data-testid="project-share-create-button">
				{{ t('planninq', 'Create link') }}
			</NcButton>
		</form>

		<div v-if="created" class="project-share__created" data-testid="project-share-created">
			<NcTextField
				:modelValue="created.url"
				:label="t('planninq', 'Link')"
				readonly
				data-testid="project-share-created-url" />
			<NcButton data-testid="project-share-copy" @click="copy(created.url)">
				<template #icon>
					<ContentCopy :size="20" />
				</template>
				{{ t('planninq', 'Copy link') }}
			</NcButton>
		</div>

		<NcLoadingIcon v-if="loading" />
		<p v-else-if="links.length === 0" class="project-share__hint" data-testid="project-share-empty">
			{{ t('planninq', 'This board has no live links.') }}
		</p>
		<ul v-else
			class="project-share__links"
			role="list"
			data-testid="project-share-links">
			<li
				v-for="link in links"
				:key="link.id"
				class="project-share__link"
				:data-testid="`project-share-link-${link.id}`">
				<div class="project-share__link-text">
					<span>{{ t('planninq', 'Until {date}', { date: dateOf(link.expiresAt) }) }}</span>
					<span v-if="link.hasPassword">{{ t('planninq', 'With password') }}</span>
					<span>{{ t('planninq', 'Made by {name}', { name: link.createdBy || '' }) }}</span>
					<span>{{ t('planninq', 'Uses: {count}', { count: Number(link.useCount || 0) }) }}</span>
				</div>
				<NcButton
					:disabled="switching === link.id"
					:data-testid="`project-share-switch-off-${link.id}`"
					@click="switchOff(link)">
					{{ t('planninq', 'Switch off') }}
				</NcButton>
			</li>
		</ul>
	</div>
</template>

<script>
/**
 * ProjectShareSection.
 *
 * The Share tab of the project settings sidebar: create a read-only board
 * link with an end date and an optional password, list the project's live
 * links and switch one off. Shown to owners and managers only.
 *
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcLoadingIcon, NcPasswordField, NcTextField } from '@nextcloud/vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import {
	createProjectLink,
	defaultEndDate,
	endDateRefusal,
	latestEndDate,
	projectLinks,
	publicBoardUrl,
	switchOffLink,
} from '../utils/projectShare.js'

export default {
	name: 'ProjectShareSection',

	components: { ContentCopy, NcButton, NcLoadingIcon, NcPasswordField, NcTextField },

	props: {
		/** The project whose board is shared. */
		project: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			endDate: defaultEndDate(),
			password: '',
			links: [],
			loading: false,
			creating: false,
			switching: null,
			created: null,
		}
	},

	computed: {
		/**
		 * The first day a link may end on.
		 *
		 * @return {string}
		 */
		tomorrow() {
			return new Date(Date.now() + 24 * 3600 * 1000).toISOString().slice(0, 10)
		},

		/**
		 * The last day a link may end on.
		 *
		 * @return {string}
		 */
		latest() {
			return latestEndDate()
		},

		/**
		 * What is wrong with the chosen end date, or an empty string.
		 *
		 * @return {string}
		 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
		 */
		endDateMessage() {
			return endDateRefusal(this.endDate) === null ? '' : t('planninq', 'The end date must be after today and within one year.')
		},
	},

	watch: {
		'project.id': {
			immediate: true,
			handler() {
				this.created = null
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Read the project's live links.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
		 */
		async load() {
			this.loading = true
			try {
				this.links = await projectLinks(this.project.id)
			} catch {
				this.links = []
				showError(t('planninq', 'The link could not be changed. Please try again.'))
			} finally {
				this.loading = false
			}
		},

		/**
		 * Create a link with the chosen end date and password.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
		 */
		async create() {
			if (this.endDateMessage) {
				return
			}

			this.creating = true
			try {
				const link = await createProjectLink(this.project, { endDate: this.endDate, password: this.password })
				this.created = { ...link, url: publicBoardUrl(link.anchor) }
				this.password = ''
				await this.load()
			} catch {
				showError(t('planninq', 'The link could not be changed. Please try again.'))
			} finally {
				this.creating = false
			}
		},

		/**
		 * Switch a link off; it answers "This link does not work any more" from then on.
		 *
		 * @param {object} link The link.
		 * @return {Promise<void>}
		 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
		 */
		async switchOff(link) {
			this.switching = link.id
			try {
				await switchOffLink(link.id)
				this.links = this.links.filter((l) => l.id !== link.id)
				if (this.created?.id === link.id) {
					this.created = null
				}
			} catch {
				showError(t('planninq', 'The link could not be changed. Please try again.'))
			} finally {
				this.switching = null
			}
		},

		/**
		 * The date part of a timestamp.
		 *
		 * @param {string} value An ISO timestamp.
		 * @return {string}
		 */
		dateOf(value) {
			return String(value ?? '').slice(0, 10)
		},

		/**
		 * Copy a link to the clipboard.
		 *
		 * @param {string} url The link.
		 * @return {Promise<void>}
		 */
		async copy(url) {
			try {
				await navigator.clipboard.writeText(url)
			} catch {
				showError(t('planninq', 'The link could not be changed. Please try again.'))
			}
		},
	},
}
</script>

<style scoped>
.project-share {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
	padding: calc(var(--default-grid-baseline) * 2);
}

.project-share__hint {
	color: var(--color-text-maxcontrast);
}

.project-share__create,
.project-share__created {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.project-share__links {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	list-style: none;
	margin: 0;
	padding: 0;
}

.project-share__link {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
	border-bottom: 1px solid var(--color-border);
	padding-bottom: calc(var(--default-grid-baseline) * 2);
}

.project-share__link-text {
	display: flex;
	flex-direction: column;
}
</style>
