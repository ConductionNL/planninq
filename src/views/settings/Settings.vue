<template>
	<div>
		<!-- Default Project Configuration -->
		<CnSettingsSection
			:name="t('planix', 'Default Project Configuration')"
			:description="t('planix', 'Configure the default column set for new projects')">
			<form @submit.prevent="saveColumns">
				<div class="columns-editor">
					<div
						v-for="(col, index) in columnList"
						:key="index"
						class="column-item">
						<input
							v-model="columnList[index]"
							type="text"
							class="column-input"
							:placeholder="t('planix', 'Column name')">
						<NcButton
							type="tertiary"
							:aria-label="t('planix', 'Move up')"
							:disabled="index === 0"
							@click="moveColumn(index, -1)">
							▲
						</NcButton>
						<NcButton
							type="tertiary"
							:aria-label="t('planix', 'Move down')"
							:disabled="index === columnList.length - 1"
							@click="moveColumn(index, 1)">
							▼
						</NcButton>
						<NcButton
							type="tertiary"
							:aria-label="t('planix', 'Remove column')"
							@click="removeColumn(index)">
							✕
						</NcButton>
					</div>
					<NcButton
						type="secondary"
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
					type="primary"
					native-type="submit"
					:disabled="savingColumns">
					{{ savingColumns ? t('planix', 'Saving...') : t('planix', 'Save') }}
				</NcButton>
			</form>
		</CnSettingsSection>

		<!-- Register Setup -->
		<CnSettingsSection
			:name="t('planix', 'Register Setup')"
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
					type="secondary"
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
					type="primary"
					native-type="submit"
					:disabled="saving">
					{{ saving ? t('planix', 'Saving...') : t('planix', 'Save') }}
				</NcButton>
			</form>
		</CnSettingsSection>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { useSettingsStore } from '../../store/modules/settings.js'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Settings',
	components: {
		NcButton,
		CnSettingsSection,
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
		}
	},
	computed: {
		settings() {
			return useSettingsStore().settings || {}
		},
	},
	created() {
		const settingsStore = useSettingsStore()
		this.form.register = settingsStore.settings?.register || ''
		this.loadColumnList(settingsStore.settings)
	},
	methods: {
		loadColumnList(settings) {
			try {
				const raw = settings?.default_columns || '["To Do","In Progress","Review","Done"]'
				this.columnList = JSON.parse(raw)
			} catch (e) {
				this.columnList = ['To Do', 'In Progress', 'Review', 'Done']
			}
		},
		addColumn() {
			this.columnList.push('')
		},
		removeColumn(index) {
			this.columnList.splice(index, 1)
		},
		moveColumn(index, direction) {
			const target = index + direction
			if (target < 0 || target >= this.columnList.length) {
				return
			}
			const updated = [...this.columnList]
			;[updated[index], updated[target]] = [updated[target], updated[index]]
			this.columnList = updated
		},
		async saveColumns() {
			this.savingColumns = true
			this.columnsSuccess = ''
			this.columnsError = ''
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings({
				default_columns: JSON.stringify(this.columnList.filter(c => c.trim() !== '')),
			})
			if (result) {
				this.columnsSuccess = t('planix', 'Default columns saved successfully')
			} else {
				this.columnsError = t('planix', 'Failed to save default columns')
			}
			this.savingColumns = false
		},
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
					this.initSuccess = t('planix', 'Register initialized successfully')
				} else {
					this.initError = data.message || t('planix', 'Initialization failed')
				}
			} catch (e) {
				this.initError = t('planix', 'Initialization failed')
			}
			this.initializing = false
		},
		async save() {
			this.saving = true
			this.successMessage = ''
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings(this.form)
			if (result) {
				this.successMessage = t('planix', 'Settings saved successfully')
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
</style>
