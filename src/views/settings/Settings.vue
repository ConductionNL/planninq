<template>
	<div>
		<!-- Default project configuration -->
		<CnSettingsSection
			:name="t('planix', 'Default project configuration')"
			:description="t('planix', 'Configure the default column set for new projects')">
			<form @submit.prevent="saveColumns">
				<div class="columns-editor">
					<div
						v-for="(col, index) in columnList"
						:key="index"
						class="column-item">
						<!-- A placeholder is not an accessible name: it is not
						     exposed as one, and it disappears the moment the
						     field has a value. These inputs repeat, so the name
						     carries the position too — otherwise a screen-reader
						     user hears "Column name" five times with nothing to
						     tell the fields apart. -->
						<input
							v-model="columnList[index]"
							type="text"
							class="column-input"
							:aria-label="t('planix', 'Column {number} name', { number: index + 1 })"
							:placeholder="t('planix', 'Column name')">
						<NcButton
							variant="tertiary"
							:aria-label="t('planix', 'Move up')"
							:disabled="index === 0"
							@click="moveColumn(index, -1)">
							▲
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('planix', 'Move down')"
							:disabled="index === columnList.length - 1"
							@click="moveColumn(index, 1)">
							▼
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('planix', 'Remove column')"
							@click="removeColumn(index)">
							✕
						</NcButton>
					</div>
					<NcButton
						variant="secondary"
						@click="addColumn">
						+ {{ t('planix', 'Add column') }}
					</NcButton>
				</div>

				<div v-if="columnsSuccess" class="success-message">
					{{ columnsSuccess }}
				</div>
				<div v-if="columnsError" class="error-message">
					{{ columnsError }}
				</div>

				<NcButton
					variant="primary"
					type="submit"
					:disabled="savingColumns">
					{{ savingColumns ? t('planix', 'Saving...') : t('planix', 'Save') }}
				</NcButton>
			</form>
		</CnSettingsSection>

		<!-- Project creation policy -->
		<CnSettingsSection
			:name="t('planix', 'Project creation')"
			:description="t('planix', 'Control who may create new projects')">
			<form @submit.prevent="saveCreationPolicy">
				<div class="form-group">
					<label for="allow-project-creation">{{ t('planix', 'Allow project creation') }}</label>
					<select
						id="allow-project-creation"
						v-model="creationPolicy"
						class="column-input">
						<option value="all">
							{{ t('planix', 'All authenticated users') }}
						</option>
						<option value="admins">
							{{ t('planix', 'Administrators only') }}
						</option>
					</select>
				</div>
				<div v-if="creationPolicySuccess" class="success-message">
					{{ creationPolicySuccess }}
				</div>
				<div v-if="creationPolicyError" class="error-message">
					{{ creationPolicyError }}
				</div>
				<NcButton
					variant="primary"
					type="submit"
					:disabled="savingCreationPolicy">
					{{ savingCreationPolicy ? t('planix', 'Saving...') : t('planix', 'Save') }}
				</NcButton>
			</form>
		</CnSettingsSection>

		<!-- Notification settings -->
		<CnSettingsSection
			:name="t('planix', 'Notification settings')"
			:description="t('planix', 'Configure when due-date reminders are sent')">
			<!--
				`novalidate` is load-bearing, not tidy-up.

				`saveLeadHours()` implements the range rule itself and renders
				`Lead time must be between 1 and 336 hours` in the error slot
				below. But `min="1" max="336"` on the input also arms the
				browser's native constraint validation, which runs FIRST: on an
				out-of-range value Chrome cancels the submit event entirely, so
				`saveLeadHours()` never ran and the app's own message could not
				appear. The user got a browser-locale tooltip instead of the
				translated planix string, and the spec scenario "Invalid lead
				time rejected in the UI" failed on an assertion that was correct.

				The attributes stay: they still give the number input its spinner
				bounds and communicate the range to assistive technology. Only
				the automatic block is turned off, so the component's validator
				is the single thing that decides and reports.
			-->
			<form novalidate @submit.prevent="saveLeadHours">
				<div class="form-group">
					<label for="due-reminder-lead-hours">{{ t('planix', 'Due-date reminder lead time (hours)') }}</label>
					<input
						id="due-reminder-lead-hours"
						v-model="leadHours"
						type="number"
						min="1"
						max="336"
						class="column-input">
				</div>
				<div v-if="leadHoursSuccess" class="success-message">
					{{ leadHoursSuccess }}
				</div>
				<div v-if="leadHoursError" class="error-message">
					{{ leadHoursError }}
				</div>
				<NcButton
					variant="primary"
					type="submit"
					:disabled="savingLeadHours">
					{{ savingLeadHours ? t('planix', 'Saving...') : t('planix', 'Save') }}
				</NcButton>
			</form>
		</CnSettingsSection>

		<!-- Label management -->
		<CnSettingsSection
			:name="t('planix', 'Label management')"
			:description="t('planix', 'Create, edit, and delete the app-wide labels that tasks reference')">
			<div class="label-mgmt">
				<NcLoadingIcon v-if="labelsLoading" :size="24" />
				<div v-else-if="labelsError" class="error-message" role="alert">
					{{ labelsError }}
				</div>
				<template v-else>
					<p v-if="labels.length === 0" class="label-mgmt__empty">
						{{ t('planix', 'No labels yet.') }}
					</p>
					<ul v-else class="label-mgmt__list">
						<li
							v-for="label in labels"
							:key="label.id"
							class="label-mgmt__item">
							<span
								class="label-mgmt__chip"
								:style="{ backgroundColor: label.color }" />
							<span class="label-mgmt__title">{{ label.title }}</span>
							<span v-if="label.description" class="label-mgmt__desc">{{ label.description }}</span>
							<span class="label-mgmt__usage">
								{{ n('planix', 'used by {count} task', 'used by {count} tasks', label.usageCount || 0, { count: label.usageCount || 0 }) }}
							</span>
							<NcButton
								variant="tertiary"
								:aria-label="t('planix', 'Edit label')"
								@click="openEdit(label)">
								{{ t('planix', 'Edit') }}
							</NcButton>
							<NcButton
								variant="tertiary"
								:aria-label="t('planix', 'Delete label')"
								@click="openDelete(label)">
								{{ t('planix', 'Delete') }}
							</NcButton>
						</li>
					</ul>
					<NcButton
						variant="secondary"
						@click="openCreate">
						+ {{ t('planix', 'Create label') }}
					</NcButton>
				</template>
			</div>
		</CnSettingsSection>

		<!-- Register setup -->
		<CnSettingsSection
			:name="t('planix', 'Register setup')"
			:description="t('planix', 'OpenRegister schema and register initialization for Planix')">
			<div class="register-status">
				<span v-if="settings.openregisters" class="status-indicator status-ok">
					✓ {{ t('planix', 'OpenRegister is available') }}
				</span>
				<span v-else class="status-indicator status-warn">
					⚠ {{ t('planix', 'OpenRegister is not installed or enabled') }}
				</span>
			</div>

			<div v-if="settings.openregisters" class="register-init">
				<div v-if="initSuccess" class="success-message">
					{{ initSuccess }}
				</div>
				<div v-if="initError" class="error-message">
					{{ initError }}
				</div>
				<NcButton
					variant="secondary"
					:disabled="initializing"
					@click="initializeRegister">
					{{ initializing ? t('planix', 'Initializing...') : t('planix', 'Initialize register') }}
				</NcButton>
			</div>
		</CnSettingsSection>

		<!-- Legacy register ID section -->
		<CnSettingsSection
			:name="t('planix', 'Configuration')"
			:description="t('planix', 'Configure the app settings')">
			<form @submit.prevent="save">
				<div class="form-group">
					<label for="register">{{ t('planix', 'Register') }}</label>
					<input
						id="register"
						v-model="form.register"
						type="text"
						:placeholder="t('planix', 'OpenRegister register ID')">
				</div>

				<div v-if="successMessage" class="success-message">
					{{ successMessage }}
				</div>

				<NcButton
					variant="primary"
					type="submit"
					:disabled="saving">
					{{ saving ? t('planix', 'Saving...') : t('planix', 'Save') }}
				</NcButton>
			</form>
		</CnSettingsSection>

		<LabelEditDialog
			v-if="showLabelEdit"
			:label="editingLabel"
			@close="showLabelEdit = false"
			@saved="onLabelSaved" />
		<LabelDeleteDialog
			v-if="showLabelDelete"
			:label="deletingLabel"
			@close="showLabelDelete = false"
			@deleted="onLabelDeleted" />
	</div>
</template>

<script>
/**
 * Settings view (admin form).
 *
 * Admin form with the default-columns editor, OpenRegister initialize button,
 * and the legacy register-id field.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { useSettingsStore } from '../../store/modules/settings.js'
import { useLabelsStore } from '../../store/labels.js'
import LabelEditDialog from '../../components/dialogs/LabelEditDialog.vue'
import LabelDeleteDialog from '../../components/dialogs/LabelDeleteDialog.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Settings',
	components: {
		NcButton,
		NcLoadingIcon,
		CnSettingsSection,
		LabelEditDialog,
		LabelDeleteDialog,
	},
	data() {
		return {
			form: {
				register: '',
			},
			saving: false,
			successMessage: '',
			// Default columns editor
			columnList: [],
			savingColumns: false,
			columnsSuccess: '',
			columnsError: '',
			// OpenRegister init
			initializing: false,
			initSuccess: '',
			initError: '',
			// allow_project_creation
			creationPolicy: 'all',
			savingCreationPolicy: false,
			creationPolicySuccess: '',
			creationPolicyError: '',
			// due_reminder_lead_hours
			leadHours: 24,
			savingLeadHours: false,
			leadHoursSuccess: '',
			leadHoursError: '',
			// Label management
			showLabelEdit: false,
			showLabelDelete: false,
			editingLabel: null,
			deletingLabel: null,
		}
	},
	computed: {
		/**
		 * @spec exclude Store passthrough — proxies the settings store's settings object.
		 */
		settings() {
			return useSettingsStore().settings || {}
		},
		/**
		 * @spec exclude Store passthrough — the loaded labels (with usage counts).
		 */
		labels() {
			return useLabelsStore().labels
		},
		/**
		 * @spec exclude Store passthrough — labels loading flag.
		 */
		labelsLoading() {
			return useLabelsStore().loading
		},
		/**
		 * @spec exclude Store passthrough — labels error message.
		 */
		labelsError() {
			return useLabelsStore().error
		},
	},
	/**
	 * @spec exclude Lifecycle glue — seeds the form fields from the settings store on create.
	 */
	created() {
		const settingsStore = useSettingsStore()
		this.form.register = settingsStore.settings?.register || ''
		this.creationPolicy = settingsStore.settings?.allow_project_creation || 'all'
		this.leadHours = parseInt(settingsStore.settings?.due_reminder_lead_hours, 10) || 24
		this.loadColumnList(settingsStore.settings)
		useLabelsStore().fetchLabels()
	},
	methods: {
		/**
		 * Open the label dialog in create mode.
		 *
		 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
		 */
		openCreate() {
			this.editingLabel = null
			this.showLabelEdit = true
		},
		/**
		 * Open the label dialog in edit mode for the given label.
		 *
		 * @param {object} label The label to edit.
		 *
		 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
		 */
		openEdit(label) {
			this.editingLabel = label
			this.showLabelEdit = true
		},
		/**
		 * Open the delete-confirmation dialog for the given label.
		 *
		 * @param {object} label The label to delete.
		 *
		 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
		 */
		openDelete(label) {
			this.deletingLabel = label
			this.showLabelDelete = true
		},
		/**
		 * Refresh labels after a successful create/edit and close the dialog.
		 *
		 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
		 */
		async onLabelSaved() {
			this.showLabelEdit = false
			await useLabelsStore().fetchLabels()
		},
		/**
		 * Refresh labels after a successful delete and close the dialog.
		 *
		 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
		 */
		async onLabelDeleted() {
			this.showLabelDelete = false
			await useLabelsStore().fetchLabels()
		},
		/**
		 * Parse the stored default_columns JSON into the editable list,
		 * falling back to the hardcoded default set on parse failure.
		 *
		 * @param {object} settings Settings object holding default_columns
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		loadColumnList(settings) {
			try {
				const raw = settings?.default_columns || '["To Do","In Progress","Review","Done"]'
				this.columnList = JSON.parse(raw)
			} catch (e) {
				this.columnList = ['To Do', 'In Progress', 'Review', 'Done']
			}
		},
		/**
		 * Append an empty column to the editable default-columns list.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		addColumn() {
			this.columnList.push('')
		},
		/**
		 * Remove the column at the given index from the editable list.
		 *
		 * @param {number} index Index of the column to remove
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		removeColumn(index) {
			this.columnList.splice(index, 1)
		},
		/**
		 * Reorder a column up or down within the editable list.
		 *
		 * @param {number} index     Index of the column to move
		 * @param {number} direction -1 to move up, +1 to move down
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		moveColumn(index, direction) {
			const target = index + direction
			if (target < 0 || target >= this.columnList.length) {
				return
			}
			const updated = [...this.columnList]
			;[updated[index], updated[target]] = [updated[target], updated[index]]
			this.columnList = updated
		},
		/**
		 * Persist the default-columns JSON via settingsStore.saveSettings.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		async saveColumns() {
			this.savingColumns = true
			this.columnsSuccess = ''
			this.columnsError = ''
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings({
				default_columns: JSON.stringify(this.columnList.filter(c => c.trim() !== '')),
			})
			if (result) {
				this.columnsSuccess = this.t('planix', 'Default columns saved successfully')
			} else {
				this.columnsError = this.t('planix', 'Failed to save default columns')
			}
			this.savingColumns = false
		},
		/**
		 * Persist the project creation policy via settingsStore.saveSettings.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		async saveCreationPolicy() {
			this.savingCreationPolicy = true
			this.creationPolicySuccess = ''
			this.creationPolicyError = ''
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings({
				allow_project_creation: this.creationPolicy,
			})
			if (result) {
				this.creationPolicySuccess = this.t('planix', 'Creation policy saved successfully')
			} else {
				this.creationPolicyError = this.t('planix', 'Failed to save creation policy')
			}
			this.savingCreationPolicy = false
		},

		/**
		 * Persist the due-date reminder lead time via settingsStore.saveSettings.
		 * Validates the 1–336 hour range inline before submitting.
		 *
		 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#3
		 */
		async saveLeadHours() {
			this.leadHoursSuccess = ''
			this.leadHoursError = ''
			const hours = parseInt(this.leadHours, 10)
			if (Number.isNaN(hours) || hours < 1 || hours > 336) {
				this.leadHoursError = this.t('planix', 'Lead time must be between 1 and 336 hours')
				return
			}
			this.savingLeadHours = true
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings({
				due_reminder_lead_hours: String(hours),
			})
			if (result) {
				this.leadHoursSuccess = this.t('planix', 'Reminder lead time saved successfully')
			} else {
				this.leadHoursError = this.t('planix', 'Failed to save reminder lead time')
			}
			this.savingLeadHours = false
		},
		/**
		 * Trigger SettingsController::load to re-import the planix register.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-2
		 */
		async initializeRegister() {
			this.initializing = true
			this.initSuccess = ''
			this.initError = ''
			try {
				const response = await fetch(generateUrl('/apps/planix/api/settings/load'), {
					method: 'POST',
					headers: { requesttoken: OC.requestToken },
				})
				const data = await response.json()
				if (data.success) {
					this.initSuccess = this.t('planix', 'Register initialized successfully')
				} else {
					this.initError = data.message || this.t('planix', 'Initialization failed')
				}
			} catch (e) {
				this.initError = this.t('planix', 'Initialization failed')
			}
			this.initializing = false
		},
		/**
		 * Persist the legacy register-id field via settingsStore.saveSettings.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		async save() {
			this.saving = true
			this.successMessage = ''
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings(this.form)
			if (result) {
				this.successMessage = this.t('planix', 'Settings saved successfully')
			}
			this.saving = false
		},
	},
}
</script>

<style scoped>
.form-group {
	margin-bottom: 12px;
}

.form-group label {
	display: block;
	margin-bottom: 4px;
	font-weight: 600;
}

.success-message {
	color: var(--color-success);
	margin-bottom: 8px;
}

.error-message {
	color: var(--color-error);
	margin-bottom: 8px;
}

.columns-editor {
	margin-bottom: 16px;
}

.column-item {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;
}

.column-input {
	flex: 1;
}

.register-status {
	margin-bottom: 12px;
}

.status-indicator {
	font-weight: 600;
}

.status-ok {
	color: var(--color-success);
}

.status-warn {
	color: var(--color-warning);
}

.register-init {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.label-mgmt {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.label-mgmt__list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.label-mgmt__item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 0;
	border-bottom: 1px solid var(--color-border);
}

.label-mgmt__chip {
	display: inline-block;
	width: 16px;
	height: 16px;
	border-radius: 50%;
	flex-shrink: 0;
}

.label-mgmt__title {
	font-weight: 600;
}

.label-mgmt__desc {
	color: var(--color-text-maxcontrast);
	flex: 1;
}

.label-mgmt__usage {
	margin-left: auto;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.label-mgmt__empty {
	color: var(--color-text-maxcontrast);
}
</style>
