<template>
	<NcAppSidebar
		v-model:open="internalOpen"
		v-model:active="activeTab"
		:name="project ? project.title : t('planninq', 'Project settings')"
		@close="$emit('close')">
		<!-- Details tab -->
		<NcAppSidebarTab
			id="details"
			:name="t('planninq', 'Details')"
			:order="1">
			<template #icon>
				<PencilIcon :size="20" />
			</template>

			<div v-if="project" class="project-settings-sidebar__section">
				<!-- Title -->
				<NcTextField
					v-model="form.title"
					:label="t('planninq', 'Title')" />

				<!-- Project key (tasks-readable-keys): editable until a task carries it -->
				<NcTextField
					v-if="canEditKey"
					v-model="form.key"
					:label="t('planninq', 'Project key')"
					:helperText="t('planninq', 'Every task number starts with it, such as VERG-42.')"
					maxlength="10"
					data-testid="project-key" />
				<div v-else class="project-settings-sidebar__field">
					<span class="project-settings-sidebar__label">{{ t('planninq', 'Project key') }}</span>
					<span class="project-settings-sidebar__readonly" data-testid="project-key">{{ project.key }}</span>
				</div>

				<!-- Description -->
				<NcTextArea
					v-model="form.description"
					:label="t('planninq', 'Description')"
					rows="3" />

				<!-- Color -->
				<div class="project-settings-sidebar__field">
					<label class="project-settings-sidebar__label" for="sidebar-color">
						{{ t('planninq', 'Color') }}
					</label>
					<input
						id="sidebar-color"
						v-model="form.color"
						type="color"
						class="project-settings-sidebar__color"
						:aria-label="t('planninq', 'Project color')">
				</div>

				<!-- Icon -->
				<NcTextField
					v-model="form.icon"
					:label="t('planninq', 'Icon (emoji)')"
					:placeholder="t('planninq', 'e.g. 📁 🚀')" />

				<!-- Portfolio: its managers read the project (projects-grouping-hierarchy-fields) -->
				<NcSelect
					v-model="form.portfolio"
					:options="portfolioOptions"
					:clearable="false"
					:inputLabel="t('planninq', 'Portfolio')"
					label="label"
					data-testid="project-portfolio" />

				<!-- Parent: a programme above this project (projects-grouping-hierarchy-fields) -->
				<NcSelect
					v-model="form.parent"
					:options="parentChoices"
					:clearable="false"
					:inputLabel="t('planninq', 'Part of')"
					label="label"
					data-testid="project-parent" />

				<!-- Project fields an admin defined (projects-grouping-hierarchy-fields) -->
				<div
					v-for="field in projectFields"
					:key="field.key"
					class="project-settings-sidebar__field"
					:data-testid="`project-field-${field.key}`">
					<NcSelect
						v-if="field.type === 'choice'"
						v-model="fieldForm[field.key]"
						:options="field.options || []"
						:inputLabel="field.required ? t('planninq', '{label} (required)', { label: field.label }) : field.label" />
					<NcCheckboxRadioSwitch v-else-if="field.type === 'boolean'" v-model="fieldForm[field.key]">
						{{ field.label }}
					</NcCheckboxRadioSwitch>
					<NcTextField
						v-else
						v-model="fieldForm[field.key]"
						:type="field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text')"
						:label="field.required ? t('planninq', '{label} (required)', { label: field.label }) : field.label" />
					<p
						v-if="missingFields.includes(field.key)"
						class="project-settings-sidebar__error"
						role="alert"
						:data-testid="`project-field-${field.key}-error`">
						{{ t('planninq', '{label} is required', { label: field.label }) }}
					</p>
				</div>

				<!-- Case reference (read-only) -->
				<div v-if="project.caseReference" class="project-settings-sidebar__field">
					<label class="project-settings-sidebar__label">
						{{ t('planninq', 'Case reference') }}
					</label>
					<span class="project-settings-sidebar__readonly">{{ project.caseReference }}</span>
					<CaseHandoverSection :project="project" />
				</div>

				<NcButton
					variant="primary"
					:disabled="saving"
					@click="saveDetails">
					<template v-if="saving" #icon>
						<NcLoadingIcon :size="16" />
					</template>
					{{ saving ? t('planninq', 'Saving…') : t('planninq', 'Save') }}
				</NcButton>
			</div>
		</NcAppSidebarTab>

		<!-- Members tab -->
		<NcAppSidebarTab
			id="members"
			:name="t('planninq', 'Members')"
			:order="2">
			<template #icon>
				<AccountGroupOutline :size="20" />
			</template>

			<div class="project-settings-sidebar__section">
				<MemberSearch
					v-if="project"
					:projectId="project.id"
					:existingMembers="project.members || []"
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
								variant="tertiary"
								:aria-label="t('planninq', 'Leave project')"
								@click="showLeaveDialog = true">
								{{ t('planninq', 'Leave project') }}
							</NcButton>
							<!-- Remove member (other users) -->
							<NcButton
								v-else
								variant="tertiary-no-background"
								:aria-label="t('planninq', 'Remove {name}', { name: uid })"
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
					<NcButton variant="error" @click="executeRemoval">
						{{ t('planninq', 'Remove anyway') }}
					</NcButton>
					<NcButton @click="cancelRemoval">
						{{ t('planninq', 'Cancel') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>

		<!-- Columns tab: the board's lanes as a keyboard-friendly list -->
		<NcAppSidebarTab
			v-if="project"
			id="columns"
			:name="t('planninq', 'Columns')"
			:order="3">
			<template #icon>
				<ViewColumnOutline :size="20" />
			</template>
			<ColumnSettingsList
				:projectId="project.id"
				:canManage="canManageColumns"
				@changed="$emit('columnsChanged')" />
		</NcAppSidebarTab>

		<!-- Danger zone tab -->
		<NcAppSidebarTab
			id="danger"
			:name="t('planninq', 'Danger zone')"
			:order="4">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>

			<div class="project-settings-sidebar__section">
				<!-- Restore an archived project (projects-lifecycle-policy) -->
				<div v-if="lifecycle.restore" class="project-settings-sidebar__danger-item">
					<p>{{ t('planninq', 'Bring this project back to the active list.') }}</p>
					<NcButton variant="primary" data-testid="project-restore" @click="doRestore">
						{{ t('planninq', 'Restore project') }}
					</NcButton>
				</div>

				<div v-if="lifecycle.archive" class="project-settings-sidebar__danger-item">
					<p>{{ t('planninq', 'Archive this project. It will no longer appear in the active list.') }}</p>
					<NcButton
						v-if="!confirmArchive"
						variant="warning"
						@click="confirmArchive = true">
						{{ t('planninq', 'Archive project') }}
					</NcButton>
					<div v-else class="project-settings-sidebar__confirm-row">
						<span>{{ t('planninq', 'Are you sure?') }}</span>
						<NcButton variant="warning" @click="doArchive">
							{{ t('planninq', 'Yes, archive') }}
						</NcButton>
						<NcButton @click="confirmArchive = false">
							{{ t('planninq', 'Cancel') }}
						</NcButton>
					</div>
				</div>

				<div class="project-settings-sidebar__danger-item">
					<p>{{ t('planninq', 'Permanently delete this project and all its tasks.') }}</p>
					<NcButton variant="error" @click="showDeleteDialog = true">
						{{ t('planninq', 'Delete project') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>

		<!-- Dialogs -->
		<ProjectLeaveDialog
			v-if="showLeaveDialog && project"
			:projectId="project.id"
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
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
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
	NcCheckboxRadioSwitch,
	NcLoadingIcon,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@nextcloud/vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline.vue'
import ProjectDeleteDialog from '../dialogs/ProjectDeleteDialog.vue'
import ProjectLeaveDialog from '../dialogs/ProjectLeaveDialog.vue'
import CaseHandoverSection from './CaseHandoverSection.vue'
import ColumnSettingsList from './ColumnSettingsList.vue'
import MemberSearch from './MemberSearch.vue'
import { useProjectsStore } from '../store/projects.js'
import { portfolioIdOf, sortPortfolios } from '../utils/portfolioGrouping.js'
import { customFieldValues, missingRequired, sortFields } from '../utils/projectFields.js'
import { lifecycleButtons } from '../utils/projectLifecycle.js'
import { parentIdOf, parentOptions, parentRefusal } from '../utils/projectTree.js'
import { keyEditable, keyRefusal, normaliseProjectKey } from '../utils/workItemKeys.js'

export default {
	name: 'ProjectSettingsSidebar',

	components: {
		CaseHandoverSection,
		NcAppSidebar,
		NcAppSidebarTab,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcSelect,
		NcTextField,
		NcTextArea,
		AccountGroupOutline,
		AlertCircleOutline,
		CloseIcon,
		PencilIcon,
		ViewColumnOutline,
		ColumnSettingsList,
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

	emits: ['close', 'archived', 'restored', 'deleted', 'columnsChanged'],

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
				key: this.project?.key || '',
				description: this.project?.description || '',
				color: this.project?.color || '#0082c9',
				icon: this.project?.icon || '',
				portfolio: null,
				parent: null,
			},

			projectFields: [],
			fieldForm: {},
			// The lifecycle actions OpenRegister offers on this project; null until read.
			projectActions: null,
			missingFields: [],

			portfolios: [],
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

		/**
		 * Whether the current user may manage the board columns: the project
		 * owner or an admin, the rule the server enforces too.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
		 */
		canManageColumns() {
			return getCurrentUser()?.isAdmin === true || (!!this.currentUid && this.project?.owner === this.currentUid)
		},

		/**
		 * Which of Archive and Restore the Danger zone shows.
		 *
		 * @return {{archive: boolean, restore: boolean}}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
		 */
		lifecycle() {
			return lifecycleButtons(this.project, this.projectActions)
		},

		/**
		 * Whether the key may still change: until a task carries it.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.3
		 */
		canEditKey() {
			return keyEditable(this.project)
		},

		/**
		 * No parent, then every project the user reads except this one and its subprojects.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
		 */
		parentChoices() {
			return [
				{ id: '', label: this.t('planninq', 'No parent project') },
				...parentOptions(this.projectsStore.projects, this.project).map((p) => ({ id: String(p.id), label: String(p.title ?? '') })),
			]
		},

		/**
		 * No portfolio, then every portfolio in list order.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		portfolioOptions() {
			return [
				{ id: '', label: this.t('planninq', 'No portfolio') },
				...sortPortfolios(this.portfolios).map((p) => ({ id: String(p.id), label: String(p.title ?? '') })),
			]
		},
	},

	watch: {
		/**
		 * @spec exclude Framework glue — syncs the project prop into the edit form on change.
		 * @param {object} newVal The updated project object.
		 */
		project(newVal) {
			if (newVal) {
				this.form.title = newVal.title || ''
				this.form.key = newVal.key || ''
				this.form.description = newVal.description || ''
				this.form.color = newVal.color || '#0082c9'
				this.form.icon = newVal.icon || ''
				this.form.portfolio = this.portfolioOption(newVal)
				this.form.parent = this.parentOption(newVal)
				this.fieldForm = { ...(newVal.customFields || {}) }
				this.missingFields = []
				this.loadActions()
			}
		},
	},

	/**
	 * Load the portfolios for the Portfolio field.
	 *
	 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
	 */
	async mounted() {
		const [portfolios, fields] = await Promise.all([
			this.projectsStore.fetchPortfolios(),
			this.projectsStore.fetchProjectFields(),
			this.projectsStore.projects.length ? Promise.resolve() : this.projectsStore.fetchProjects(),
		])
		this.portfolios = portfolios
		this.projectFields = sortFields(fields)
		this.fieldForm = { ...(this.project?.customFields || {}) }
		this.form.portfolio = this.portfolioOption(this.project)
		this.form.parent = this.parentOption(this.project)
		this.loadActions()
	},

	methods: {
		/**
		 * Read the lifecycle actions OpenRegister offers on the project.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
		 */
		async loadActions() {
			this.projectActions = this.project?.id ? await this.projectsStore.fetchProjectActions(this.project.id) : null
		},

		/**
		 * Restore the archived project, then show Archive again.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
		 */
		async doRestore() {
			const restored = await this.projectsStore.restoreProject(this.project.id)
			if (!restored) {
				showError(this.t('planninq', 'Could not restore the project'))
				return
			}
			showSuccess(this.t('planninq', 'Project restored'))
			this.$emit('restored', restored)
			await this.loadActions()
		},

		/**
		 * The option of a project's portfolio, or No portfolio.
		 *
		 * @param {object} project The project.
		 * @return {{id: string, label: string}}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		portfolioOption(project) {
			const id = portfolioIdOf(project)
			return this.portfolioOptions.find((option) => option.id === id) || this.portfolioOptions[0]
		},

		/**
		 * The option of a project's parent, or No parent project.
		 *
		 * @param {object} project The project.
		 * @return {{id: string, label: string}}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
		 */
		parentOption(project) {
			const id = parentIdOf(project)
			return this.parentChoices.find((option) => option.id === id) || this.parentChoices[0]
		},

		/**
		 * Persist title/description/color/icon edits via updateProject.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
		 */
		async saveDetails() {
			this.missingFields = missingRequired(this.projectFields, this.fieldForm)
			if (this.missingFields.length) {
				return
			}
			this.saving = true
			const firstKey = !this.project.key && !!normaliseProjectKey(this.form.key)
			try {
				const saved = await this.projectsStore.updateProject(this.project.id, {
					// A PUT nulls what it is not sent, so the key always goes along.
					key: this.canEditKey ? (normaliseProjectKey(this.form.key) || null) : (this.project.key || null),
					title: this.form.title.trim(),
					description: this.form.description.trim() || undefined,
					color: this.form.color,
					icon: this.form.icon.trim() || undefined,
					portfolio: this.form.portfolio?.id || null,
					parent: this.form.parent?.id || null,
					customFields: { ...(this.project.customFields || {}), ...this.clearedFields(), ...customFieldValues(this.projectFields, this.fieldForm) },
					// Always include existing members and owner so a PATCH/PUT does not wipe them
					members: Array.isArray(this.project.members) ? this.project.members : [],
					owner: this.project.owner || undefined,
				})
				if (!saved) {
					showError(this.saveFailure(this.projectsStore.error))
					this.form.parent = this.parentOption(this.project)
					return
				}
				showSuccess(firstKey
					? this.t('planninq', 'Project saved. The existing tasks get their numbers in the background.')
					: this.t('planninq', 'Project saved'))
			} catch {
				showError(this.t('planninq', 'Could not save project'))
			} finally {
				this.saving = false
			}
		},

		/**
		 * The defined fields emptied in the form, so a cleared value is removed rather than kept.
		 *
		 * @return {object}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
		 */
		clearedFields() {
			return Object.fromEntries(this.projectFields.map((field) => [field.key, undefined]))
		},

		/**
		 * The message for a project save the server refused.
		 *
		 * @param {string} error The store's error text.
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
		 */
		saveFailure(error) {
			const key = {
				used: this.t('planninq', 'This key is already used by another project.'),
				format: this.t('planninq', 'Use 2 to 10 letters and digits, starting with a letter.'),
				fixed: this.t('planninq', 'The key cannot change once tasks carry it.'),
			}[keyRefusal(error)]
			if (key) {
				return key
			}
			return {
				cycle: this.t('planninq', 'A project cannot sit under one of its own subprojects.'),
				depth: this.t('planninq', 'Projects nest three levels deep at most: programme, project and subproject.'),
			}[parentRefusal(error)] || this.t('planninq', 'Could not save project')
		},

		/**
		 * @spec exclude Event-wiring glue — refreshes the project after a member is added.
		 */
		onMemberAdded() {
			this.projectsStore.fetchProject(this.project.id)
		},

		/**
		 * Show assigned-task warning before removing a member.
		 * Queries the task count first (read-only); only removes if count is zero.
		 *
		 * @param {string} uid Nextcloud UID to remove
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async confirmRemoveMember(uid) {
			const count = await this.projectsStore.getMemberTaskCount(this.project.id, uid)
			if (count > 0) {
				// Show warning — do NOT remove yet.
				this.pendingRemoveUid = uid
				this.removalWarning = this.t('planninq', '{name} has {count} assigned tasks in this project', {
					name: uid,
					count,
				})
			} else {
				// No assigned tasks — remove immediately.
				await this.projectsStore.removeMember(this.project.id, uid)
				await this.projectsStore.fetchProject(this.project.id)
			}
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

.project-settings-sidebar__error {
	margin: 4px 0 0;
	color: var(--color-error-text);
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
