<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- Work types (time-timer-and-work-type): what kind of work logged time was -->
	<CnSettingsSection
		:name="t('planninq', 'Work types')"
		:description="t('planninq', 'When there are work types, every time entry records one, and the time report splits the minutes by it. With none, time is logged without one.')"
		data-testid="work-type-settings">
		<p v-if="names.length === 0" class="work-types__hint">
			{{ t('planninq', 'No work types yet.') }}
		</p>
		<ul v-else class="work-types__list" data-testid="work-type-list">
			<li v-for="name in names"
				:key="name"
				class="work-types__row"
				data-testid="work-type-row">
				<template v-if="renaming === name">
					<NcTextField v-model="renameTo"
						:label="t('planninq', 'New name for {name}', { name })"
						data-testid="work-type-rename-input" />
					<NcCheckboxRadioSwitch v-model="renameEntries" data-testid="work-type-rename-entries">
						{{ t('planninq', 'Also rename it on existing entries?') }}
					</NcCheckboxRadioSwitch>
					<NcButton variant="primary"
						:disabled="saving"
						data-testid="work-type-rename-save"
						@click="rename(name)">
						{{ t('planninq', 'Rename') }}
					</NcButton>
					<NcButton variant="tertiary" :disabled="saving" @click="renaming = ''">
						{{ t('planninq', 'Cancel') }}
					</NcButton>
				</template>
				<template v-else>
					<span class="work-types__name">{{ name }}</span>
					<NcButton variant="tertiary"
						:aria-label="renameLabel(name)"
						:disabled="saving"
						@click="startRename(name)">
						{{ t('planninq', 'Rename') }}
					</NcButton>
					<NcButton variant="tertiary"
						:aria-label="retireLabel(name)"
						:disabled="saving"
						data-testid="work-type-retire"
						@click="retire(name)">
						{{ t('planninq', 'Retire') }}
					</NcButton>
				</template>
			</li>
		</ul>

		<form class="work-types__add" novalidate @submit.prevent="add">
			<NcTextField v-model="newName"
				:label="t('planninq', 'New work type')"
				data-testid="work-type-new" />
			<NcButton type="submit" :disabled="saving || !newName.trim()" data-testid="work-type-add">
				{{ t('planninq', 'Add') }}
			</NcButton>
		</form>
		<p class="work-types__hint">
			{{ t('planninq', 'Retiring a work type removes it from the choice; entries that have it keep it.') }}
		</p>

		<div v-if="message" :class="ok ? 'work-types__success' : 'work-types__error'" role="status">
			{{ message }}
		</div>
	</CnSettingsSection>
</template>

<script>
/**
 * WorkTypeSettings.
 *
 * The admin list of work types: add, rename (optionally on the existing
 * entries too, in the background) and retire. Each change is saved at once;
 * the server validates the list and refuses a repeated name.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { NcButton, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { addWorkType, renameWorkType, workTypeNames } from '../utils/workTypes.js'

export default {
	name: 'WorkTypeSettings',

	components: { CnSettingsSection, NcButton, NcCheckboxRadioSwitch, NcTextField },

	data() {
		return {
			settingsStore: useSettingsStore(),
			newName: '',
			renaming: '',
			renameTo: '',
			renameEntries: true,
			saving: false,
			message: '',
			ok: false,
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — the stored work types.
		 */
		names() {
			return workTypeNames(this.settingsStore.settings?.work_types)
		},
	},

	methods: {
		/**
		 * Add a work type at the end of the list.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
		 */
		async add() {
			const list = addWorkType(this.names, this.newName)
			if (!list) {
				this.fail(this.t('planninq', 'Enter a name that is not in the list yet.'))
				return
			}
			if (await this.save({ work_types: JSON.stringify(list) })) {
				this.newName = ''
			}
		},

		/**
		 * @spec exclude UI glue — opens the rename field of one type.
		 *
		 * @param {string} name The type to rename.
		 */
		startRename(name) {
			this.renaming = name
			this.renameTo = name
			this.renameEntries = true
		},

		/**
		 * Rename a work type; when asked, the existing entries follow in the background.
		 *
		 * @param {string} name The type to rename.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
		 */
		async rename(name) {
			const list = renameWorkType(this.names, name, this.renameTo)
			if (!list) {
				this.fail(this.t('planninq', 'Enter a name that is not in the list yet.'))
				return
			}
			const to = this.renameTo.trim()
			const payload = { work_types: JSON.stringify(list) }
			if (this.renameEntries && to !== name) {
				payload.work_type_rename = { from: name, to }
			}
			if (await this.save(payload)) {
				this.renaming = ''
			}
		},

		/**
		 * Retire a work type: it leaves the choice, entries keep it.
		 *
		 * @param {string} name The type to retire.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
		 */
		async retire(name) {
			await this.save({ work_types: JSON.stringify(this.names.filter((n) => n !== name)) })
		},

		/**
		 * Save and report; true when the server stored the list as sent.
		 *
		 * @param {object} payload The settings to save.
		 * @return {Promise<boolean>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
		 */
		async save(payload) {
			this.saving = true
			this.message = ''
			try {
				const result = await this.settingsStore.saveSettings(payload)
				const stored = result?.config?.work_types ?? result?.work_types
				if (!result || JSON.stringify(workTypeNames(stored)) !== payload.work_types) {
					this.fail(this.t('planninq', 'The work types could not be saved.'))
					await this.settingsStore.fetchSettings()
					return false
				}
				await this.settingsStore.fetchSettings()
				this.ok = true
				this.message = this.t('planninq', 'Work types saved.')
				return true
			} finally {
				this.saving = false
			}
		},

		/**
		 * @spec exclude Display glue — the accessible name of a row's Rename button.
		 *
		 * @param {string} name The type.
		 * @return {string}
		 */
		renameLabel(name) {
			return this.t('planninq', 'Rename "{name}"', { name })
		},

		/**
		 * @spec exclude Display glue — the accessible name of a row's Retire button.
		 *
		 * @param {string} name The type.
		 * @return {string}
		 */
		retireLabel(name) {
			return this.t('planninq', 'Retire "{name}"', { name })
		},

		/**
		 * @spec exclude UI glue — shows an error message.
		 *
		 * @param {string} message The message.
		 */
		fail(message) {
			this.ok = false
			this.message = message
		},
	},
}
</script>

<style scoped>
.work-types__list {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 8px 0;
	padding: 0;
	list-style: none;
}

.work-types__row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.work-types__name {
	flex: 1 1 160px;
}

.work-types__add {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
	max-width: 480px;
}

.work-types__hint {
	color: var(--color-text-maxcontrast);
}

.work-types__success {
	color: var(--color-success-text);
}

.work-types__error {
	color: var(--color-error-text);
}
</style>
