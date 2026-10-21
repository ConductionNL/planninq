<template>
	<NcDialog :name="t('planninq', 'Import from Microsoft Project')" size="normal" @closing="$emit('close')">
		<template #default>
			<div class="msproject-import" data-testid="msproject-import-dialog">
				<p>{{ t('planninq', 'Choose a plan saved from Microsoft Project in its XML format. Nothing is imported until you confirm.') }}</p>

				<label class="msproject-import__file">
					<span>{{ t('planninq', 'Plan file') }}</span>
					<input type="file"
						accept=".xml,.mpp,application/xml,text/xml"
						data-testid="msproject-import-file"
						:disabled="busy || done"
						@change="choose">
				</label>

				<div v-if="busy" class="msproject-import__loading">
					<NcLoadingIcon :size="24" />
				</div>

				<div v-if="refusal"
					class="msproject-import__error"
					role="alert"
					data-testid="msproject-import-error">
					{{ refusalText }}
				</div>

				<template v-if="preview && !done">
					<h3>{{ t('planninq', 'What the import creates') }}</h3>
					<dl class="msproject-import__counts" data-testid="msproject-import-counts">
						<template v-for="row in countRows" :key="row.kind">
							<dt>{{ row.label }}</dt>
							<dd>{{ row.count }}</dd>
						</template>
					</dl>
					<p v-if="preview.updates" data-testid="msproject-import-updates">
						{{ t('planninq', 'Already imported, updated in place: {count}', { count: preview.updates }) }}
					</p>

					<template v-if="losses.length">
						<h3>{{ t('planninq', 'What does not carry over') }}</h3>
						<ul data-testid="msproject-import-losses">
							<li v-for="loss in losses" :key="loss.code">
								{{ lossText(loss) }}
							</li>
						</ul>
					</template>
				</template>

				<template v-if="missing.length">
					<h3>{{ t('planninq', 'No longer in the plan') }}</h3>
					<p>{{ t('planninq', 'These stay in the project; remove them yourself if they are no longer needed.') }}</p>
					<ul data-testid="msproject-import-missing">
						<li v-for="(title, index) in missing" :key="index">
							{{ title }}
						</li>
					</ul>
				</template>

				<p v-if="result" role="status" data-testid="msproject-import-result">
					{{ resultText }}
				</p>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="busy" @click="$emit('close')">
				{{ done ? t('planninq', 'Close') : t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton v-if="!done"
				variant="primary"
				:disabled="busy || !preview"
				data-testid="msproject-import-confirm"
				@click="runImport">
				{{ t('planninq', 'Import') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * MsProjectImportDialog.
 *
 * The project owner picks a plan saved from Microsoft Project as XML. The
 * dialog asks the server for a preview (which writes nothing) and shows what
 * the import creates and what does not carry over; "Import" sends the same
 * file again. An .mpp file is refused before upload with the way to save the
 * plan as XML. After an import the parent reloads the timeline.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { importPlan, previewPlan } from '../api/msprojectImport.js'
import { COUNT_KINDS, fileRefusal, lossList, refusalReason } from '../utils/msprojectImport.js'

export default {
	name: 'MsProjectImportDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
	},

	props: {
		/** The project the plan is imported into. */
		projectId: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'imported'],

	data() {
		return {
			file: null,
			preview: null,
			result: null,
			refusal: '',
			busy: false,
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: an import finished without stopping.
		 */
		done() {
			return !!this.result && !this.result.error
		},

		/**
		 * The preview's counts, labelled, in the order the preview names them.
		 *
		 * @return {Array<{kind: string, label: string, count: number}>}
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		countRows() {
			const labels = {
				phases: this.t('planninq', 'Phases'),
				tasks: this.t('planninq', 'Tasks'),
				subtasks: this.t('planninq', 'Sub-tasks'),
				milestones: this.t('planninq', 'Milestones'),
				links: this.t('planninq', 'Links'),
			}
			return COUNT_KINDS.map((kind) => ({ kind, label: labels[kind], count: this.preview?.counts?.[kind] || 0 }))
		},

		/**
		 * @spec exclude Trivial getter: the losses in preview order.
		 */
		losses() {
			return lossList(this.preview?.losses)
		},

		/**
		 * @spec exclude Trivial getter: the titles that left the plan, from the result or else the preview.
		 */
		missing() {
			return this.result?.missing || this.preview?.missing || []
		},

		/**
		 * The explanation for a refused file.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		refusalText() {
			const texts = {
				mpp: this.t('planninq', 'Save the plan in Microsoft Project with File, Save as, XML format, and import that file.'),
				unsafe: this.t('planninq', 'This file declares a DOCTYPE or entities, which are not accepted.'),
				tooLarge: this.t('planninq', 'The file is larger than 10 MB.'),
				tooManyTasks: this.t('planninq', 'The plan has more than 2,000 tasks, more than one import takes.'),
				notAPlan: this.t('planninq', 'This file is not a Microsoft Project plan in XML format.'),
				noFile: this.t('planninq', 'Choose a file to import.'),
				forbidden: this.t('planninq', 'Only the project owner can import a plan.'),
				stopped: this.t('planninq', 'The import stopped part-way. Run it again to finish it.'),
			}
			return texts[this.refusal] || this.t('planninq', 'The plan could not be read. Please try again.')
		},

		/**
		 * What the import did.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		resultText() {
			const sum = (counts) => Object.values(counts || {}).reduce((total, value) => total + Number(value || 0), 0)
			const text = this.t('planninq', 'The plan is imported: {created} created, {updated} updated.', {
				created: sum(this.result?.created),
				updated: sum(this.result?.updated),
			})
			if (this.result?.refusedLinks) {
				return text + ' ' + this.t('planninq', 'Links that would close a loop were left out: {count}', { count: this.result.refusedLinks })
			}
			return text
		},
	},

	methods: {
		/**
		 * One loss as a sentence.
		 *
		 * @param {{code: string, count: number}} loss The loss.
		 * @return {string}
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		lossText(loss) {
			const count = loss.count
			const texts = {
				resources: () => this.t('planninq', 'Tasks with resources, which are not imported: {count}', { count }),
				collapsedLevels: () => this.t('planninq', 'Tasks deeper than level 3, placed under their level-2 task: {count}', { count }),
				relatedLinks: () => this.t('planninq', 'Links other than finish-to-start, imported as related links: {count}', { count }),
				lags: () => this.t('planninq', 'Link lags, which are dropped: {count}', { count }),
				phaseLinks: () => this.t('planninq', 'Links to a summary task, which are not imported: {count}', { count }),
			}
			return texts[loss.code]
				? texts[loss.code]()
				: this.t('planninq', 'Links to tasks outside this plan, which are not imported: {count}', { count })
		},

		/**
		 * Take the chosen file and ask for its preview.
		 *
		 * @param {Event} event The file input change.
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		async choose(event) {
			this.file = event.target?.files?.[0] || null
			this.preview = null
			this.result = null
			this.refusal = fileRefusal(this.file)
			if (this.refusal) {
				return
			}
			this.busy = true
			try {
				const { status, data } = await previewPlan(this.projectId, this.file)
				if (status === 200) {
					this.preview = data
				} else {
					this.refusal = refusalReason(status, data)
				}
			} catch (err) {
				console.error('MsProjectImportDialog: preview failed', err)
				this.refusal = 'other'
			}
			this.busy = false
		},

		/**
		 * Import the previewed file.
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		async runImport() {
			this.busy = true
			this.refusal = ''
			try {
				const { status, data } = await importPlan(this.projectId, this.file)
				if (status === 200) {
					this.result = data
					this.$emit('imported')
				} else if (status === 500 && data?.created) {
					this.result = data
					this.refusal = 'stopped'
					this.$emit('imported')
				} else {
					this.refusal = refusalReason(status, data)
				}
			} catch (err) {
				console.error('MsProjectImportDialog: import failed', err)
				this.refusal = 'other'
			}
			this.busy = false
		},
	},
}
</script>

<style scoped>
.msproject-import {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.msproject-import__file {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.msproject-import__loading {
	display: flex;
	justify-content: center;
}

.msproject-import__error {
	color: var(--color-error-text);
}

.msproject-import__counts {
	display: grid;
	grid-template-columns: max-content max-content;
	gap: 4px 16px;
	margin: 0;
}

.msproject-import__counts dd {
	margin: 0;
}
</style>
