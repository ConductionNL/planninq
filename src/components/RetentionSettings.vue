<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- Retention (retention-on-planninq-records task 2.1): the selection-list category per schema -->
	<CnSettingsSection
		:name="t('planninq', 'Retention')"
		:description="t('planninq', 'How long closed projects and their records are kept, by selection-list category.')"
		data-testid="retention-settings">
		<p v-if="loaded && options.length === 0" class="retention-settings__note" data-testid="retention-no-list">
			{{ t('planninq', 'OpenRegister has no selection list yet. Import one there first.') }}
		</p>
		<div class="retention-settings__fields">
			<NcSelect
				v-for="row in rows"
				:key="row.schema"
				:modelValue="selected(row.schema)"
				:options="options"
				:inputLabel="row.label"
				:placeholder="t('planninq', 'No category')"
				:disabled="!loaded || options.length === 0"
				label="label"
				:data-testid="`retention-${row.schema}`"
				@update:modelValue="pick(row.schema, $event)" />
		</div>
		<NcButton
			variant="primary"
			:disabled="saving || !loaded || options.length === 0"
			data-testid="retention-save"
			@click="save">
			{{ t('planninq', 'Save') }}
		</NcButton>
		<p
			v-if="message"
			class="retention-settings__message"
			:class="[{ 'retention-settings__message--error': failed }]"
			role="status"
			data-testid="retention-message">
			{{ message }}
		</p>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcSelect } from '@nextcloud/vue'
import { categoryOptions, loadRetention, normalisePicks, saveRetention } from '../utils/retention.js'

/**
 * RetentionSettings: the admin section that picks, per schema, the
 * selection-list category OpenRegister dates planninq's records by.
 *
 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 */
export default {
	name: 'RetentionSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcSelect,
	},

	data() {
		return {
			categories: [],
			picks: normalisePicks({}),
			loaded: false,
			saving: false,
			message: '',
			failed: false,
		}
	},

	computed: {
		/**
		 * @return {Array<{schema: string, label: string}>} The schemas, labelled.
		 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
		 */
		rows() {
			return [
				{ schema: 'project', label: t('planninq', 'Projects') },
				{ schema: 'task', label: t('planninq', 'Tasks') },
				{ schema: 'plannedTimeEntry', label: t('planninq', 'Planned hours') },
			]
		},

		/**
		 * @return {Array<{id: string, label: string}>} The categories to pick from.
		 */
		options() {
			return categoryOptions(this.categories)
		},
	},

	/**
	 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
	 */
	async created() {
		await this.load()
	},

	methods: {
		t,

		/**
		 * @return {string} The endpoint.
		 */
		url() {
			return generateUrl('/apps/planninq/api/settings/retention')
		},

		/**
		 * Read the categories and the stored pick.
		 *
		 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
		 */
		async load() {
			try {
				const { categories, picks } = await loadRetention(axios, this.url())
				this.categories = categories
				this.picks = picks
			} catch {
				this.show(t('planninq', 'Could not load the retention settings.'), true)
			} finally {
				this.loaded = true
			}
		},

		/**
		 * @param {string} schema The schema
		 * @return {object|null} The option picked for it.
		 */
		selected(schema) {
			return this.options.find((option) => option.id === this.picks[schema]) ?? null
		},

		/**
		 * @param {string} schema The schema
		 * @param {{id: string}|null} option The option picked, null when cleared
		 */
		pick(schema, option) {
			this.picks = { ...this.picks, [schema]: option?.id ?? '' }
			this.message = ''
		},

		/**
		 * Save the pick.
		 *
		 * @spec openspec/changes/retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
		 */
		async save() {
			this.saving = true
			try {
				this.picks = await saveRetention(axios, this.url(), this.picks)
				this.show(t('planninq', 'Retention saved.'), false)
			} catch {
				this.show(t('planninq', 'Could not save the retention. Please try again.'), true)
			} finally {
				this.saving = false
			}
		},

		/**
		 * @param {string} message The message
		 * @param {boolean} failed Whether it reports a failure
		 */
		show(message, failed) {
			this.message = message
			this.failed = failed
		},
	},
}
</script>

<style scoped>
.retention-settings__fields {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
	gap: 12px;
	margin-block: 12px;
}

.retention-settings__note,
.retention-settings__message {
	color: var(--color-text-maxcontrast);
}

.retention-settings__message--error {
	color: var(--color-error-text);
}
</style>
