<template>
	<CnSettingsSection
		:name="t('planix', 'Default Project Columns')"
		:description="t('planix', 'Configure the default columns for new projects. Drag to reorder.')">
		<div class="default-columns">
			<ul class="column-list">
				<li v-for="(column, index) in columns" :key="index" class="column-item">
					<input
						v-model="columns[index]"
						type="text"
						class="column-input"
						:aria-label="t('planix', 'Column name')">
					<NcButton
						type="tertiary"
						:aria-label="t('planix', 'Move up')"
						:disabled="index === 0"
						@click="moveUp(index)">
						<template #icon>
							<ChevronUpIcon :size="16" />
						</template>
					</NcButton>
					<NcButton
						type="tertiary"
						:aria-label="t('planix', 'Move down')"
						:disabled="index === columns.length - 1"
						@click="moveDown(index)">
						<template #icon>
							<ChevronDownIcon :size="16" />
						</template>
					</NcButton>
					<NcButton
						type="tertiary"
						:aria-label="t('planix', 'Remove column')"
						@click="removeColumn(index)">
						<template #icon>
							<CloseIcon :size="16" />
						</template>
					</NcButton>
				</li>
			</ul>

			<div class="add-column">
				<input
					v-model="newColumnName"
					type="text"
					:placeholder="t('planix', 'New column name')"
					:aria-label="t('planix', 'New column name')"
					@keyup.enter="addColumn">
				<NcButton type="secondary" :disabled="!newColumnName.trim()" @click="addColumn">
					{{ t('planix', 'Add column') }}
				</NcButton>
			</div>

			<div v-if="successMessage" class="feedback success-message">
				{{ successMessage }}
			</div>
			<div v-if="errorMessage" class="feedback error-message">
				{{ errorMessage }}
			</div>

			<NcButton
				type="primary"
				:disabled="saving"
				@click="save">
				{{ saving ? t('planix', 'Saving...') : t('planix', 'Save') }}
			</NcButton>
		</div>
	</CnSettingsSection>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'DefaultColumns',
	components: {
		NcButton,
		CnSettingsSection,
		ChevronUpIcon,
		ChevronDownIcon,
		CloseIcon,
	},
	data() {
		const settingsStore = useSettingsStore()
		return {
			columns: [...(settingsStore.settings?.default_columns ?? ['To Do', 'In Progress', 'Review', 'Done'])],
			newColumnName: '',
			saving: false,
			successMessage: '',
			errorMessage: '',
		}
	},
	methods: {
		moveUp(index) {
			if (index === 0) return
			const col = this.columns.splice(index, 1)[0]
			this.columns.splice(index - 1, 0, col)
		},
		moveDown(index) {
			if (index === this.columns.length - 1) return
			const col = this.columns.splice(index, 1)[0]
			this.columns.splice(index + 1, 0, col)
		},
		removeColumn(index) {
			this.columns.splice(index, 1)
		},
		addColumn() {
			const name = this.newColumnName.trim()
			if (!name) return
			this.columns.push(name)
			this.newColumnName = ''
		},
		async save() {
			this.saving = true
			this.successMessage = ''
			this.errorMessage = ''
			const settingsStore = useSettingsStore()
			const result = await settingsStore.saveSettings({ default_columns: this.columns })
			if (result) {
				this.successMessage = t('planix', 'Default columns saved successfully')
			} else {
				this.errorMessage = t('planix', 'Failed to save default columns')
			}
			this.saving = false
		},
	},
}
</script>

<style scoped>
.default-columns {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.column-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.column-item {
	display: flex;
	align-items: center;
	gap: 4px;
}
.column-input {
	flex: 1;
	padding: 6px 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}
.add-column {
	display: flex;
	gap: 8px;
	align-items: center;
}
.add-column input {
	flex: 1;
	padding: 6px 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}
.feedback {
	padding: 6px 0;
}
.success-message {
	color: var(--color-success);
}
.error-message {
	color: var(--color-error);
}
</style>
