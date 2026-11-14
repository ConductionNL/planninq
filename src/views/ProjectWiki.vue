<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="project-wiki" data-testid="project-wiki">
		<ProjectTabs :projectId="projectId" />

		<NcLoadingIcon v-if="loading" :size="32" />

		<div v-else class="project-wiki__layout">
			<aside class="project-wiki__side">
				<NcTextField
					v-model="search"
					:label="t('planninq', 'Title')"
					data-testid="wiki-search" />
				<NcButton
					v-if="writable"
					variant="primary"
					wide
					data-testid="wiki-add-page"
					@click="startNew('')">
					{{ t('planninq', 'Add page') }}
				</NcButton>
				<WikiTree
					:pages="shownPages"
					:currentId="pageIdParam"
					:canWrite="writable"
					@open="openPage"
					@addSubpage="startNew"
					@reorder="reorder"
					@move="(id) => (moving = id)" />
				<p v-if="pages.length === 0" class="project-wiki__muted">
					{{ t('planninq', 'This project has no wiki pages yet.') }}
				</p>
			</aside>

			<section class="project-wiki__main">
				<NcNoteCard v-if="error" type="error" data-testid="wiki-error">
					{{ error }}
				</NcNoteCard>

				<WikiPageEditor
					v-if="editing"
					:key="editing.id || 'new'"
					:page="editing"
					@saved="onSaved"
					@cancel="editing = null"
					@locked="onLockRefused" />

				<article v-else-if="current" data-testid="wiki-page">
					<p class="project-wiki__muted" data-testid="wiki-breadcrumb">
						{{ crumb }}
					</p>
					<h2>{{ current.title }}</h2>
					<NcNoteCard v-if="holder" type="info" data-testid="wiki-locked">
						{{ t('planninq', '{name} is editing this page', { name: holder.displayName }) }}
					</NcNoteCard>
					<div class="project-wiki__toolbar">
						<NcButton
							v-if="writable"
							:disabled="holder !== null"
							data-testid="wiki-edit"
							@click="editing = current">
							{{ t('planninq', 'Edit') }}
						</NcButton>
						<NcButton data-testid="wiki-history" @click="toggleHistory">
							{{ t('planninq', 'History') }}
						</NcButton>
					</div>
					<!-- eslint-disable-next-line vue/no-v-html -- sanitised by renderBody -->
					<div class="project-wiki__body" data-testid="wiki-body" v-html="body" />

					<section v-if="history !== null" class="project-wiki__history" data-testid="wiki-history-list">
						<h3>{{ t('planninq', 'History') }}</h3>
						<ol>
							<li v-for="(entry, index) in history" :key="entry.id || index">
								<span>{{ entry.created }} · {{ entry.userName || entry.user || '' }}</span>
								<NcButton
									v-if="managing && index > 0"
									variant="tertiary"
									:data-testid="`wiki-restore-${entry.id}`"
									@click="restore(entry)">
									{{ t('planninq', 'Restore') }}
								</NcButton>
							</li>
						</ol>
					</section>
				</article>

				<NcEmptyContent
					v-else
					:name="t('planninq', 'Wiki')" />
			</section>
		</div>

		<WikiPageMoveDialog
			v-if="moving"
			:pages="pages"
			:pageId="moving"
			@close="moving = null"
			@move="move" />
	</div>
</template>

<script>
/**
 * ProjectWiki.
 *
 * A project's wiki at /projects/:id/wiki and /projects/:id/wiki/:pageId: the
 * page tree on the left, the open page on the right with its breadcrumb and
 * formatted body. People who may change the project's tasks add, edit, move
 * and reorder pages; viewers read only. A page someone else is editing says
 * so and keeps its Edit button disabled. Managers restore an earlier version
 * from the page's history.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.4
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, NcTextField } from '@nextcloud/vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import WikiPageEditor from '../components/WikiPageEditor.vue'
import WikiTree from '../components/WikiTree.vue'
import WikiPageMoveDialog from '../dialogs/WikiPageMoveDialog.vue'
import { fetchWikiPageHistory, fetchWikiPages, lockHolder, restoreWikiPage, saveWikiPage } from '../api/wikiPages.js'
import projectRoute from '../mixins/projectRoute.js'
import { useProjectsStore } from '../store/projects.js'
import { canManageMembers, canWrite, currentGroupIds, projectRole } from '../utils/projectRole.js'
import { breadcrumb, nextOrder, pageId, pathTo, renderBody, reorderPatches } from '../utils/wikiTree.js'

export default {
	name: 'ProjectWiki',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
		ProjectTabs,
		WikiPageEditor,
		WikiPageMoveDialog,
		WikiTree,
	},

	mixins: [projectRoute],

	data() {
		return {
			project: null,
			pages: [],
			loading: true,
			search: '',
			editing: null,
			moving: null,
			history: null,
			error: '',
		}
	},

	computed: {
		/** @return {string} */
		pageIdParam() {
			return String(this.$route.params.pageId || '')
		},

		/** @return {string} */
		role() {
			const user = getCurrentUser()
			return projectRole(this.project, user?.uid, currentGroupIds())
		},

		/**
		 * Whether the person may write pages: as for tasks.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-viewer-reads-but-cannot-write
		 */
		writable() {
			return canWrite(this.role)
		},

		/**
		 * Whether the person may restore versions: the project's managers.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-restoring-an-earlier-version
		 */
		managing() {
			return canManageMembers(this.role)
		},

		/** @return {object|null} */
		current() {
			return this.pages.find((page) => pageId(page) === this.pageIdParam) || null
		},

		/** @return {string} */
		crumb() {
			return breadcrumb(this.pages, this.pageIdParam)
		},

		/** @return {string} */
		body() {
			return renderBody(this.current?.body)
		},

		/**
		 * Who else holds the open page's lock.
		 *
		 * @return {{user: string, displayName: string}|null}
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-second-writer-sees-the-page-is-being-edited
		 */
		holder() {
			const holder = lockHolder(this.current)
			return holder && holder.user !== getCurrentUser()?.uid ? holder : null
		},

		/**
		 * The pages whose title matches the search, with the path to each, so
		 * the tree stays a tree.
		 *
		 * @return {Array<object>}
		 */
		shownPages() {
			const needle = this.search.trim().toLowerCase()
			if (needle === '') {
				return this.pages
			}
			const keep = new Set()
			for (const page of this.pages) {
				if (String(page.title || '').toLowerCase().includes(needle)) {
					pathTo(this.pages, pageId(page)).forEach((id) => keep.add(id))
				}
			}
			return this.pages.filter((page) => keep.has(pageId(page)))
		},
	},

	watch: {
		pageIdParam() {
			this.editing = null
			this.history = null
		},
	},

	methods: {
		/**
		 * Load the project and its pages.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				const store = useProjectsStore()
				const [project, pages] = await Promise.all([store.fetchProject(this.projectId), fetchWikiPages(this.projectId)])
				this.project = project
				this.pages = pages
			} catch {
				this.error = this.t('planninq', 'Please try again later.')
			} finally {
				this.loading = false
			}
		},

		/** @param {string} id The page to open. */
		openPage(id) {
			this.$router.push({ name: 'ProjectWikiPage', params: { id: this.projectId, pageId: id } })
		},

		/**
		 * Start a new page under a parent.
		 *
		 * @param {string} parent The parent id, '' for the top level.
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-writing-the-first-page
		 */
		startNew(parent) {
			this.editing = {
				project: this.projectId,
				parent: parent || null,
				order: nextOrder(this.pages, parent || ''),
				title: '',
				body: '',
			}
		},

		/**
		 * @param {object} saved The saved page.
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-writing-the-first-page
		 */
		async onSaved(saved) {
			this.editing = null
			await this.load()
			const id = pageId(saved)
			if (id && id !== this.pageIdParam) {
				this.openPage(id)
			}
		},

		/** Someone else took the lock first. */
		async onLockRefused() {
			this.editing = null
			await this.load()
			this.error = this.t('planninq', 'Please try again later.')
		},

		/**
		 * Move a page one place among its siblings.
		 *
		 * @param {{id: string, direction: number}} event The page and the direction.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		async reorder({ id, direction }) {
			await this.write(reorderPatches(this.pages, id, direction))
		},

		/**
		 * @param {{id: string, parent: string|null, order: number}} move The new place.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
		 */
		async move(move) {
			this.moving = null
			await this.write([move])
		},

		/**
		 * Save patches one by one, then reload. A refusal, such as the parent
		 * guard's, is shown and the tree is read again as it is.
		 *
		 * @param {Array<object>} patches The changes.
		 */
		async write(patches) {
			this.error = ''
			try {
				for (const patch of patches) {
					await saveWikiPage(patch)
				}
			} catch (exception) {
				this.error = exception?.response?.data?.message || this.t('planninq', 'Please try again later.')
			}
			await this.load()
		},

		/**
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
		 */
		async toggleHistory() {
			if (this.history !== null) {
				this.history = null
				return
			}
			try {
				this.history = await fetchWikiPageHistory(this.pageIdParam)
			} catch {
				this.error = this.t('planninq', 'Please try again later.')
			}
		},

		/**
		 * @param {object} entry The version to restore.
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-restoring-an-earlier-version
		 */
		async restore(entry) {
			try {
				await restoreWikiPage(this.pageIdParam, entry.id)
				await this.load()
				this.history = await fetchWikiPageHistory(this.pageIdParam)
			} catch {
				this.error = this.t('planninq', 'Please try again later.')
			}
		},
	},
}
</script>

<style scoped>
.project-wiki {
	padding: 8px 16px;
}

.project-wiki__layout {
	display: grid;
	grid-template-columns: minmax(220px, 300px) 1fr;
	gap: 24px;
}

.project-wiki__side {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.project-wiki__toolbar {
	display: flex;
	gap: 8px;
	margin-bottom: 12px;
}

.project-wiki__muted {
	color: var(--color-text-maxcontrast);
}

.project-wiki__history ol {
	padding-inline-start: 20px;
}

@media (max-width: 800px) {
	.project-wiki__layout {
		grid-template-columns: 1fr;
	}
}
</style>
