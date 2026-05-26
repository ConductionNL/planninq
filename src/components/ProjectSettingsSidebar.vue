<template>
	<NcAppSidebar
		:name="project ? project.title : t('planix', 'Project settings')"
		:open.sync="internalOpen"
		:active.sync="activeTab"
		@close="$emit('close')">
		<!-- Details tab -->
		<NcAppSidebarTab
			id="details"
			:name="t('planix', 'Details')"
			:order="1">
			<template #icon>
				<PencilIcon :size="20" />
			</template>

			<div v-if="project" class="project-settings-sidebar__section">
				<!-- Title -->
				<NcTextField
					:value="form.title"
					:label="t('planix', 'Title')"
					@update:value="form.title = $event" />

				<!-- Description -->
				<NcTextArea
					:value="form.description"
					:label="t('planix', 'Description')"
					rows="3"
					@update:value="form.description = $event" />

				<!-- Color -->
				<div class="project-settings-sidebar__field">
					<label class="project-settings-sidebar__label" for="sidebar-color">
						{{ t('planix', 'Color') }}
					</label>
					<input
						id="sidebar-color"
						v-model="form.color"
						type="color"
						class="project-settings-sidebar__color"
						:aria-label="t('planix', 'Project color')">
				</div>

				<!-- Icon -->
				<NcTextField
					:value="form.icon"
					:label="t('planix', 'Icon (emoji)')"
					:placeholder="t('planix', 'e.g. 📁 🚀')"
					@update:value="form.icon = $event" />

				<!-- Case reference (read-only) -->
				<div v-if="project.caseReference" class="project-settings-sidebar__field">
					<label class="project-settings-sidebar__label">
						{{ t('planix', 'Case reference') }}
					</label>
					<span class="project-settings-sidebar__readonly">{{ project.caseReference }}</span>
				</div>

				<NcButton
					type="primary"
					:disabled="saving"
					@click="saveDetails">
					<template v-if="saving" #icon>
						<NcLoadingIcon :size="16" />
					</template>
					{{ saving ? t('planix', 'Saving…') : t('planix', 'Save') }}
				</NcButton>
			</div>
		</NcAppSidebarTab>

		<!-- Members tab -->
		<NcAppSidebarTab
			id="members"
			:name="t('planix', 'Members')"
			:order="2">
			<template #icon>
				<AccountGroupOutline :size="20" />
			</template>

			<div class="project-settings-sidebar__section">
				<MemberSearch
					v-if="project"
					:project-id="project.id"
					:existing-members="project.members || []"
					@added="onMemberAdded" />

				<ul class="project-settings-sidebar__members" role="list">
					<li
						v-for="uid in (project ? project.members : [])"
						:key="uid"
						class="project-settings-sidebar__member">
						<NcAvatar
							:user="uid"
							:size="32"
							:aria-label="uid" />
						<span class="project-settings-sidebar__member-name">{{ uid }}</span>
						<div class="project-settings-sidebar__member-actions">
							<!-- Leave project (current user) -->
							<NcButton
								v-if="uid === currentUid"
								type="tertiary"
								:aria-label="t('planix', 'Leave project')"
								@click="showLeaveDialog = true">
								{{ t('planix', 'Leave project') }}
							</NcButton>
							<!-- Remove member (other users) -->
							<NcButton
								v-else
								type="tertiary-no-background"
								:aria-label="t('planix', 'Remove {name}', { name: uid })"
								@click="confirmRemoveMember(uid)">
								<template #icon>
									<CloseIcon :size="16" />
								</template>
							</NcButton>
						</div>
					</li>
				</ul>

				<!-- Assigned task warning before removal -->
				<div v-if="removalWarning" class="project-settings-sidebar__warning" role="alert">
					{{ removalWarning }}
					<NcButton type="error" @click="executeRemoval">
						{{ t('planix', 'Remove anyway') }}
					</NcButton>
					<NcButton @click="cancelRemoval">
						{{ t('planix', 'Cancel') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>

		<!-- Danger zone tab -->
		<NcAppSidebarTab
			id="danger"
			:name="t('planix', 'Danger zone')"
			:order="3">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>

			<div class="project-settings-sidebar__section">
				<div class="project-settings-sidebar__danger-item">
					<p>{{ t('planix', 'Archive this project. It will no longer appear in the active list.') }}</p>
					<NcButton
						v-if="!confirmArchive"
						type="warning"
						@click="confirmArchive = true">
						{{ t('planix', 'Archive project') }}
					</NcButton>
					<div v-else class="project-settings-sidebar__confirm-row">
						<span>{{ t('planix', 'Are you sure?') }}</span>
						<NcButton type="warning" @click="doArchive">
							{{ t('planix', 'Yes, archive') }}
						</NcButton>
						<NcButton @click="confirmArchive = false">
							{{ t('planix', 'Cancel') }}
						</NcButton>
					</div>
				</div>

				<div class="project-settings-sidebar__danger-item">
					<p>{{ t('planix', 'Permanently delete this project and all its tasks.') }}</p>
					<NcButton type="error" @click="showDeleteDialog = true">
						{{ t('planix', 'Delete project') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>

		<!-- Dialogs -->
		<ProjectLeaveDialog
			v-if="showLeaveDialog && project"
			:project-id="project.id"
			@close="showLeaveDialog = false"
			@left="onLeft" />

		<ProjectDeleteDialog
			v-if="showDeleteDialog && project"
			:project="project"
			@close="showDeleteDialog = false"
			@deleted="onDeleted" />
	</NcAppSidebar>
</template>

<script>
/**
 * ProjectSettingsSidebar.
 *
 * NcAppSidebar with Details/Members/Danger tabs for editing a project,
 * managing members, and archive/delete actions. Renders the read-only
 * caseReference field in the Details tab for procest-integration.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-13
 */
import {
	NcAppSidebar,
	NcAppSidebarTab,
	NcAvatar,
	NcButton,
	NcLoadingIcon,
	NcTextField,
	NcTextArea,
} from '@nextcloud/vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { useProjectsStore } from '../store/projects.js'
import MemberSearch from './MemberSearch.vue'
import ProjectLeaveDialog from './dialogs/ProjectLeaveDialog.vue'
import ProjectDeleteDialog from './dialogs/ProjectDeleteDialog.vue'

export default {
	name: 'ProjectSettingsSidebar',

	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		NcAvatar,
		NcButton,
		NcLoadingIcon,
		NcTextField,
		NcTextArea,
		AccountGroupOutline,
		AlertCircleOutline,
		CloseIcon,
		PencilIcon,
		MemberSearch,
		ProjectLeaveDialog,
		ProjectDeleteDialog,
	},

	props: {
		project: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'archived', 'deleted'],

	data() {
		return {
			internalOpen: true,
			activeTab: 'details',
			saving: false,
			confirmArchive: false,
			showLeaveDialog: false,
			showDeleteDialog: false,
			removalWarning: null,
			pendingRemoveUid: null,
			form: {
				title: this.project?.title || '',
				description: this.project?.description || '',
				color: this.project?.color || '#0082c9',
				icon: this.project?.icon || '',
			},
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — returns the projects Pinia store.
		 */
		projectsStore() {
			return useProjectsStore()
		},
		/**
		 * @spec exclude Auth passthrough — returns the current user's UID.
		 */
		currentUid() {
			return getCurrentUser()?.uid || ''
		},
	},

	watch: {
		/**
		 * @spec exclude Framework glue — syncs the project prop into the edit form on change.
		 */
		project(newVal) {
			if (newVal) {
				this.form.title = newVal.title || ''
				this.form.description = newVal.description || ''
				this.form.color = newVal.color || '#0082c9'
				this.form.icon = newVal.icon || ''
			}
		},
	},

	methods: {
		/**
		 * Persist title/description/color/icon edits via updateProject.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
		 */
		async saveDetails() {
			this.saving = true
			try {
				await this.projectsStore.updateProject(this.project.id, {
					title: this.form.title.trim(),
					description: this.form.description.trim() || undefined,
					color: this.form.color,
					icon: this.form.icon.trim() || undefined,
					// Always include existing members so a PATCH/PUT does not wipe them
					members: Array.isArray(this.project.members) ? this.project.members : [],
				})
				showSuccess(this.t('planix', 'Project saved'))
			} catch {
				showError(this.t('planix', 'Could not save project'))
			} finally {
				this.saving = false
			}
		},

		/**
		 * @spec exclude Event-wiring glue — refreshes the project after a member is added.
		 */
		onMemberAdded() {
			this.projectsStore.fetchProject(this.project.id)
		},

		/**
		 * Show assigned-task warning before removing a member.
		 *
		 * @param {string} uid Nextcloud UID to remove
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async confirmRemoveMember(uid) {
			const count = await this.projectsStore.removeMember(this.project.id, uid)
			// If member had assigned tasks, show warning first and re-add them.
			if (count > 0) {
				// Re-add (removal already happened) — we need to prevent this.
				// Actually removeMember returns the count AND removes. Undo by re-adding.
				await this.projectsStore.addMember(this.project.id, uid)
				this.pendingRemoveUid = uid
				this.removalWarning = this.t('planix', '{name} has {count} assigned tasks in this project', {
					name: uid,
					count,
				})
			}
			await this.projectsStore.fetchProject(this.project.id)
		},

		/**
		 * Confirm a pending member removal after the assigned-task warning —
		 * removes the member and refreshes the project.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async executeRemoval() {
			if (this.pendingRemoveUid) {
				await this.projectsStore.removeMember(this.project.id, this.pendingRemoveUid)
				await this.projectsStore.fetchProject(this.project.id)
			}
			this.cancelRemoval()
		},

		/**
		 * @spec exclude State-reset glue — clears the pending member-removal warning.
		 */
		cancelRemoval() {
			this.removalWarning = null
			this.pendingRemoveUid = null
		},

		/**
		 * Archive the project from the danger-zone tab.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
		 */
		async doArchive() {
			const result = await this.projectsStore.archiveProject(this.project.id)
			if (result) {
				this.$emit('archived')
				this.$emit('close')
			}
			this.confirmArchive = false
		},

		/**
		 * @spec exclude Event-wiring glue — closes the sidebar and routes to Projects after leaving.
		 */
		onLeft() {
			this.showLeaveDialog = false
			this.$emit('close')
			this.$router.push({ name: 'Projects' })
		},

		/**
		 * @spec exclude Event-wiring glue — re-emits deleted/close after a project delete.
		 */
		onDeleted() {
			this.showDeleteDialog = false
			this.$emit('deleted')
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.project-settings-sidebar__section {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 8px 0;
}

.project-settings-sidebar__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.project-settings-sidebar__label {
	font-size: 13px;
	font-weight: 500;
	color: var(--color-main-text);
}

.project-settings-sidebar__readonly {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.project-settings-sidebar__color {
	width: 48px;
	height: 36px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	padding: 2px;
	background: none;
}

.project-settings-sidebar__members {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.project-settings-sidebar__member {
	display: flex;
	align-items: center;
	gap: 10px;
}

.project-settings-sidebar__member-name {
	flex: 1;
	font-size: 14px;
}

.project-settings-sidebar__member-actions {
	flex-shrink: 0;
}

.project-settings-sidebar__warning {
	padding: 12px;
	border-radius: var(--border-radius);
	background: var(--color-warning-background);
	color: var(--color-warning-text);
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.project-settings-sidebar__danger-item {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.project-settings-sidebar__danger-item:last-child {
	border-bottom: none;
}

.project-settings-sidebar__confirm-row {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
</style>
