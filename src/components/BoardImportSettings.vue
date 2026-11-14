<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- Import a project from another tool (projects-import-from-other-tools task 1.2) -->
	<CnSettingsSection
		:name="t('planninq', 'Import a project from another tool')"
		:description="t('planninq', 'A Trello board or Jira project export becomes a new project you own.')"
		data-testid="board-import-settings">
		<NcSelect v-model="tool"
			:options="tools"
			label="label"
			:inputLabel="t('planninq', 'Source')"
			:clearable="false"
			data-testid="board-import-tool" />
		<label class="board-import__file">
			{{ t('planninq', 'Export file') }}
			<input type="file"
				:accept="accept"
				data-testid="board-import-file"
				@change="onFile">
		</label>
		<NcButton variant="primary"
			:disabled="!tool || !file || importing"
			data-testid="board-import-run"
			@click="run">
			{{ t('planninq', 'Import') }}
		</NcButton>

		<p v-if="error" class="board-import__error" role="alert">
			{{ error }}
		</p>
		<div v-if="result"
			class="board-import__result"
			role="status"
			data-testid="board-import-result">
			<p>
				{{ t('planninq', 'Imported {columns} columns and {tasks} tasks.', { columns: result.columns, tasks: result.tasks }) }}
				<a :href="projectUrl">{{ t('planninq', 'Open the project') }}</a>
			</p>
			<template v-if="result.losses && result.losses.length > 0">
				<p>{{ t('planninq', 'Not carried over:') }}</p>
				<ul>
					<li v-for="(loss, index) in result.losses" :key="index">
						{{ loss }}
					</li>
				</ul>
			</template>
		</div>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcSelect } from '@nextcloud/vue'
import { acceptFor, fetchBoardImports, importBoard } from '../api/boardImport.js'

export default {
	name: 'BoardImportSettings',
	components: { CnSettingsSection, NcButton, NcSelect },
	data() {
		return {
			tools: [],
			tool: null,
			file: null,
			importing: false,
			error: '',
			result: null,
		}
	},

	computed: {
		accept() {
			return acceptFor(this.tool?.format)
		},

		projectUrl() {
			return generateUrl(`/apps/planninq/projects/${this.result?.project}`)
		},
	},

	async mounted() {
		try {
			this.tools = await fetchBoardImports()
			this.tool = this.tools[0] || null
		} catch {
			this.error = t('planninq', 'The import failed.')
		}
	},

	methods: {
		t,
		onFile(event) {
			this.file = event.target.files?.[0] || null
		},

		async run() {
			this.importing = true
			this.error = ''
			this.result = null
			try {
				const { status, data } = await importBoard(this.tool.id, this.file)
				if (status === 201) {
					this.result = data
				} else {
					this.error = data.error || t('planninq', 'The import failed.')
				}
			} catch {
				this.error = t('planninq', 'The import failed.')
			} finally {
				this.importing = false
			}
		},
	},
}
</script>

<style scoped>
.board-import__file {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 12px 0;
}

.board-import__error {
	color: var(--color-error-text);
}

.board-import__result {
	margin-top: 12px;
}
</style>
