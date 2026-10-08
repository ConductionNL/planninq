<template>
	<div class="project-wiki">
		<div class="project-wiki__header">
			<h2>{{ t('planninq', 'Wiki') }}</h2>
			<NcButton v-if="canWrite"
				variant="primary"
				data-testid="wiki-add"
				@click="startAdd('')">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('planninq', 'Add page') }}
			</NcButton>
		</div>

		<ProjectTabs :projectId="projectId" />

		<div v-if="loading" class="project-wiki__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-else class="project-wiki__layout">
			<aside class="project-wiki__aside">
				<NcTextField
					v-model="search"
					:label="t('planninq', 'Search the wiki')"
					data-testid="wiki-search" />

				<form v-if="adding" class="project-wiki__add" @submit.prevent="createPage">
					<NcTextField
						v-model="newTitle"
						:label="addingUnder ? t('planninq', 'Title of the subpage') : t('planninq', 'Title of the page')"
						data-testid="wiki-add-title" />
					<NcButton variant="primary"
						type="submit"
						:disabled="!newTitle.trim()"
						data-testid="wiki-add-confirm">
						{{ t('planninq', 'Create page') }}
					</NcButton>
					<NcButton @click="adding = false">
						{{ t('planninq', 'Cancel') }}
					</NcButton>
				</form>

				<WikiTree
					v-if="shownPages.length"
					v-model:openIds="openIds"
					:pages="shownPages"
					:selectedId="pageId"
					@select="openPage" />
				<NcEmptyContent v-else :name="search ? t('planninq', 'No page matches') : t('planninq', 'No pages yet')" />
			</aside>

			<main class="project-wiki__main">
				<NcEmptyContent v-if="!page" :name="t('planninq', 'Pick a page in the tree')" />

				<template v-else>
					<nav class="project-wiki__breadcrumb" :aria-label="t('planninq', 'Page path')" data-testid="wiki-breadcrumb">
						{{ crumbs.map((crumb) => crumb.title).join(' / ') }}
					</nav>

					<p v-if="otherWriter"
						class="project-wiki__lock"
						role="status"
						data-testid="wiki-locked">
						{{ t('planninq', '{name} is editing this page', { name: names[lockHolder] || lockHolder }) }}
					</p>
					<p v-if="conflict"
						class="project-wiki__lock"
						role="alert"
						data-testid="wiki-conflict">
						{{ t('planninq', 'This page was changed by someone else while you were editing. Copy your text, then reload the page.') }}
					</p>

					<template v-if="editing">
						<NcTextField v-model="draft.title" :label="t('planninq', 'Title')" data-testid="wiki-edit-title" />
						<WikiPageEditor v-model="draft.body" />
						<div class="project-wiki__actions">
							<NcButton variant="primary"
								:disabled="saving || !draft.title.trim()"
								data-testid="wiki-save"
								@click="save">
								{{ t('planninq', 'Save') }}
							</NcButton>
							<NcButton :disabled="saving" data-testid="wiki-cancel" @click="stopEditing">
								{{ t('planninq', 'Cancel') }}
							</NcButton>
						</div>
					</template>

					<template v-else>
						<div class="project-wiki__title-row">
							<h3 data-testid="wiki-title">
								{{ page.title }}
							</h3>
							<div class="project-wiki__actions">
								<NcButton v-if="canWrite"
									:disabled="otherWriter"
									data-testid="wiki-edit"
									@click="startEditing">
									{{ t('planninq', 'Edit') }}
								</NcButton>
								<NcActions :ariaLabel="t('planninq', 'Page actions')">
									<NcActionButton v-if="canWrite" @click="startAdd(pageId)">
										{{ t('planninq', 'Add subpage') }}
									</NcActionButton>
									<NcActionButton v-if="canWrite" data-testid="wiki-move" @click="moving = true">
										{{ t('planninq', 'Move') }}
									</NcActionButton>
									<NcActionButton v-if="canWrite" @click="reorder(-1)">
										{{ t('planninq', 'Move up') }}
									</NcActionButton>
									<NcActionButton v-if="canWrite" @click="reorder(1)">
										{{ t('planninq', 'Move down') }}
									</NcActionButton>
									<NcActionButton data-testid="wiki-history-open" @click="openHistory">
										{{ t('planninq', 'History') }}
									</NcActionButton>
									<NcActionButton @click="showFiles = !showFiles">
										{{ t('planninq', 'Attachments') }}
									</NcActionButton>
									<NcActionButton v-if="canWrite" data-testid="wiki-delete" @click="deleting = true">
										{{ t('planninq', 'Delete page') }}
									</NcActionButton>
								</NcActions>
							</div>
						</div>
						<NcRichText :text="page.body || ''" :useMarkdown="true" data-testid="wiki-body" />
					</template>
				</template>
			</main>

			<CnObjectSidebar
				v-if="page && showFiles"
				:open="true"
				v-bind="sidebarConfig"
				:title="page.title"
				:subtitle="t('planninq', 'Wiki page')"
				:filesLabel="t('planninq', 'Attachments')"
				@update:open="showFiles = $event" />
		</div>

		<WikiPageMoveDialog
			v-if="moving && page"
			:page="page"
			:pages="pages"
			@close="moving = false"
			@move="movePage" />

		<WikiHistoryDialog
			v-if="historyOpen"
			:entries="history"
			:names="names"
			:loading="historyLoading"
			:canRestore="canRestore"
			:busy="saving"
			@close="historyOpen = false"
			@restore="restore" />

		<NcDialog
			v-if="deleting && page"
			:name="t('planninq', 'Delete page')"
			@update:open="deleting = false">
			<p v-if="hasSubpages">
				{{ t('planninq', '{title} has subpages. Move them up a level, or delete them too?', { title: page.title }) }}
			</p>
			<p v-else>
				{{ t('planninq', 'Delete {title}? This cannot be undone.', { title: page.title }) }}
			</p>
			<template #actions>
				<NcButton v-if="hasSubpages"
					variant="primary"
					data-testid="wiki-delete-promote"
					@click="removePage('promote')">
					{{ t('planninq', 'Delete and move subpages up') }}
				</NcButton>
				<NcButton variant="error" data-testid="wiki-delete-confirm" @click="removePage('cascade')">
					{{ hasSubpages ? t('planninq', 'Delete with subpages') : t('planninq', 'Delete') }}
				</NcButton>
				<NcButton @click="deleting = false">
					{{ t('planninq', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { CnObjectSidebar } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { NcActionButton, NcActions, NcButton, NcDialog, NcEmptyContent, NcLoadingIcon, NcRichText, NcTextField } from '@nextcloud/vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import WikiPageEditor from '../components/WikiPageEditor.vue'
import WikiTree from '../components/WikiTree.vue'
import WikiHistoryDialog from '../dialogs/WikiHistoryDialog.vue'
import WikiPageMoveDialog from '../dialogs/WikiPageMoveDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { useWikiStore } from '../store/wiki.js'
import { currentGroupIds, projectRole } from '../utils/projectRole.js'
import { displayNames } from '../utils/userNames.js'
import { breadcrumb, canRestoreWiki, canWriteWiki, filterPages, lockOwner, pageId, subtreeIds, wikiSidebarConfig } from '../utils/wiki.js'

/**
 * ProjectWiki: the wiki tab of a project. A page tree on the left, the page on
 * the right, with an editor, history and one writer at a time.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
 */
export default {
	name: 'ProjectWiki',

	components: {
		CnObjectSidebar,
		NcActionButton,
		NcActions,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcRichText,
		NcTextField,
		PlusIcon,
		ProjectTabs,
		WikiHistoryDialog,
		WikiPageEditor,
		WikiPageMoveDialog,
		WikiTree,
	},

	data() {
		return {
			project: null,
			loading: true,
			search: '',
			openIds: [],
			names: {},
			adding: false,
			addingUnder: '',
			newTitle: '',
			editing: false,
			draft: { title: '', body: '' },
			editedPage: null,
			saving: false,
			conflict: false,
			moving: false,
			deleting: false,
			showFiles: false,
			historyOpen: false,
			historyLoading: false,
			history: [],
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — the wiki Pinia store.
		 */
		wikiStore() {
			return useWikiStore()
		},

		/**
		 * @spec exclude Store passthrough — the pages of the project.
		 */
		pages() {
			return this.wikiStore.pages
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {string} The open page's id, from /projects/:id/wiki/:pageId.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		pageId() {
			return String(this.$route.params.pageId || '')
		},

		/**
		 * @spec exclude Display helper — the open page.
		 */
		page() {
			return this.pages.find((candidate) => pageId(candidate) === this.pageId) || null
		},

		/**
		 * @spec exclude Display helper — the pages the search leaves in the tree.
		 */
		shownPages() {
			return filterPages(this.pages, this.search)
		},

		/**
		 * @spec exclude Display helper — the path from the top to the open page.
		 */
		crumbs() {
			return breadcrumb(this.pages, this.pageId)
		},

		/**
		 * @spec exclude Display helper — whether the open page has subpages.
		 */
		hasSubpages() {
			return this.page ? subtreeIds(this.pages, this.pageId).size > 1 : false
		},

		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.4
		 */
		role() {
			return projectRole(this.project, getCurrentUser()?.uid, currentGroupIds())
		},

		/**
		 * Writing controls show for people who may change tasks; an admin always may.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.4
		 */
		canWrite() {
			return getCurrentUser()?.isAdmin === true || canWriteWiki(this.role)
		},

		/**
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
		 */
		canRestore() {
			return getCurrentUser()?.isAdmin === true || canRestoreWiki(this.role)
		},

		/**
		 * @spec exclude Display helper — who holds the lock on the open page.
		 */
		lockHolder() {
			return lockOwner(this.page)
		},

		/**
		 * @spec exclude Display helper — whether somebody else is editing the open page.
		 */
		otherWriter() {
			return this.lockHolder !== '' && this.lockHolder !== getCurrentUser()?.uid && !this.editing
		},

		/**
		 * @spec exclude Display helper — the attachments sidebar props.
		 */
		sidebarConfig() {
			return wikiSidebarConfig(this.page)
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec exclude Watcher glue — reloads the wiki for another project.
			 */
			handler() {
				this.load()
			},
		},

		pageId() {
			if (this.editing) {
				this.stopEditing()
			}
			this.revealPage()
		},
	},

	beforeUnmount() {
		if (this.editing && this.editedPage) {
			this.wikiStore.unlock(pageId(this.editedPage))
		}
	},

	methods: {
		/**
		 * Load the project, its pages and the names of the people who hold locks.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		async load() {
			this.loading = true
			try {
				const [project] = await Promise.all([useProjectsStore().fetchProject(this.projectId), this.wikiStore.fetchPages(this.projectId)])
				this.project = project
				this.revealPage()
				const holders = this.pages.map((page) => lockOwner(page)).filter(Boolean)
				this.names = holders.length ? await displayNames([...new Set(holders)]) : {}
			} finally {
				this.loading = false
			}
		},

		/**
		 * Open the path to the open page in the tree.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		revealPage() {
			const path = breadcrumb(this.pages, this.pageId).slice(0, -1).map(pageId)
			this.openIds = [...new Set([...this.openIds, ...path])]
		},

		/**
		 * Open a page from the tree.
		 *
		 * @param {string} id The page id.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		openPage(id) {
			if (id !== this.pageId) {
				this.$router.push({ name: 'ProjectWikiPage', params: { id: this.projectId, pageId: id } })
			}
		},

		/**
		 * Show the form for a new page, at the top level or under a page.
		 *
		 * @param {string} parent The parent page id, '' for the top level.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		startAdd(parent) {
			this.adding = true
			this.addingUnder = parent
			this.newTitle = ''
		},

		/**
		 * Create the page, open it and start writing.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		async createPage() {
			const created = await this.wikiStore.createPage({ title: this.newTitle, parent: this.addingUnder })
			if (!created) {
				showError(this.t('planninq', 'The page could not be created.'))
				return
			}
			this.adding = false
			this.openIds = [...new Set([...this.openIds, this.addingUnder].filter(Boolean))]
			await this.$router.push({ name: 'ProjectWikiPage', params: { id: this.projectId, pageId: pageId(created) } })
			await this.startEditing()
		},

		/**
		 * Take the lock and open the editor, or say who is editing.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async startEditing() {
			if (!this.page) {
				return
			}
			const locked = await this.wikiStore.lock(pageId(this.page))
			if (!locked.ok) {
				this.names = { ...this.names, ...await displayNames([lockOwner(locked.page)].filter(Boolean)) }
				return
			}
			this.editedPage = this.page
			this.draft = { title: String(this.page.title ?? ''), body: String(this.page.body ?? '') }
			this.conflict = false
			this.editing = true
		},

		/**
		 * Save on the version the page was opened at; a newer version is never overwritten.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async save() {
			this.saving = true
			const result = await this.wikiStore.savePage(this.editedPage, this.draft)
			this.saving = false
			if (result.conflict) {
				this.conflict = true
				return
			}
			if (!result.page) {
				showError(this.t('planninq', 'The page could not be saved. Please try again.'))
				return
			}
			await this.stopEditing()
		},

		/**
		 * Leave the editor and release the lock.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async stopEditing() {
			const edited = this.editedPage
			this.editing = false
			this.editedPage = null
			this.conflict = false
			if (edited) {
				await this.wikiStore.unlock(pageId(edited))
			}
		},

		/**
		 * @param {string} newParent The new parent id, '' for the top level.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
		 */
		async movePage(newParent) {
			this.moving = false
			if (!await this.wikiStore.movePage(this.pageId, newParent)) {
				showError(this.t('planninq', 'The page could not be moved there.'))
				return
			}
			this.revealPage()
		},

		/**
		 * @param {-1|1} direction -1 up, 1 down.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		async reorder(direction) {
			await this.wikiStore.reorderPage(this.pageId, direction)
		},

		/**
		 * @param {'promote'|'cascade'} mode What happens to the subpages.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
		 */
		async removePage(mode) {
			this.deleting = false
			if (!await this.wikiStore.deletePage(this.pageId, mode)) {
				showError(this.t('planninq', 'The page could not be deleted.'))
				return
			}
			this.$router.push({ name: 'ProjectWiki', params: { id: this.projectId } })
		},

		/**
		 * Load the page's history into the dialog.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
		 */
		async openHistory() {
			this.historyOpen = true
			this.historyLoading = true
			this.history = await this.wikiStore.history(this.pageId)
			this.names = { ...this.names, ...await displayNames([...new Set(this.history.map((entry) => entry.user).filter(Boolean))]) }
			this.historyLoading = false
		},

		/**
		 * Go back to an earlier version; the restore shows up as the newest entry.
		 *
		 * @param {string} entryId The history entry.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
		 */
		async restore(entryId) {
			this.saving = true
			const restored = await this.wikiStore.restore(this.pageId, entryId)
			this.saving = false
			if (!restored) {
				showError(this.t('planninq', 'That version could not be restored.'))
				return
			}
			this.history = await this.wikiStore.history(this.pageId)
		},
	},
}
</script>

<style scoped>
.project-wiki {
	padding: 8px 4px 24px;
}

.project-wiki__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.project-wiki__layout {
	display: grid;
	grid-template-columns: minmax(220px, 280px) 1fr;
	gap: calc(var(--default-grid-baseline) * 6);
}

.project-wiki__aside {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
}

.project-wiki__add {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.project-wiki__breadcrumb {
	color: var(--color-text-maxcontrast);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.project-wiki__title-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 3);
}

.project-wiki__actions {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
}

.project-wiki__lock {
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
}

@media (max-width: 800px) {
	.project-wiki__layout {
		grid-template-columns: 1fr;
	}
}
</style>
