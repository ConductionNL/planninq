<template>
	<NcDialog
		:name="t('planninq', 'Mark {name} as released', { name: release.title })"
		@closing="$emit('close')">
		<template #default>
			<div class="release-ship-dialog__body">
				<p v-if="open.length === 0">
					{{ t('planninq', 'Every task of this release is done or cancelled.') }}
				</p>
				<template v-else>
					<p>
						{{ t('planninq', 'Not finished yet: {count}', { count: open.length }) }}
					</p>
					<ul class="release-ship-dialog__tasks" data-testid="release-ship-open-tasks">
						<li v-for="task in open" :key="task.id">
							{{ task.title || t('planninq', 'Untitled task') }}
						</li>
					</ul>
					<fieldset class="release-ship-dialog__choices">
						<legend>{{ t('planninq', 'What happens to these tasks?') }}</legend>
						<NcCheckboxRadioSwitch v-for="target in targets"
							:key="target.id"
							v-model="choice"
							type="radio"
							name="release-ship-choice"
							:value="'move:' + target.id"
							:data-testid="'release-ship-move-' + target.id">
							{{ t('planninq', 'Move to {name}', { name: target.title }) }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="choice"
							type="radio"
							name="release-ship-choice"
							value="clear"
							data-testid="release-ship-clear">
							{{ t('planninq', 'Remove the release') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="choice"
							type="radio"
							name="release-ship-choice"
							value="keep"
							data-testid="release-ship-keep">
							{{ t('planninq', 'Keep it on {name}', { name: release.title }) }}
						</NcCheckboxRadioSwitch>
					</fieldset>
				</template>
				<div v-if="submitError" class="release-ship-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || (open.length > 0 && choice === '')"
				data-testid="release-ship-confirm"
				@click="ship">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Mark as released') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ReleaseShipDialog.
 *
 * Marks a release as released. When some of its tasks are neither done nor
 * cancelled it lists them and the member chooses: move them to another
 * planned release, clear their release, or keep them. Nothing moves silently.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
 */
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { refId, sortReleases, unfinishedTasks } from '../utils/roadmapHelpers.js'

export default {
	name: 'ReleaseShipDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
	},

	props: {
		/** The release to mark. */
		release: {
			type: Object,
			required: true,
		},

		/** The project's tasks. */
		tasks: {
			type: Array,
			default: () => [],
		},

		/** The project's releases, for the move targets. */
		releases: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'shipped'],

	data() {
		return {
			choice: '',
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
		 */
		open() {
			return unfinishedTasks(this.release, this.tasks)
		},

		/**
		 * The other planned releases the open tasks can move to.
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
		 */
		targets() {
			return sortReleases(this.releases.filter((other) => refId(other) !== refId(this.release) && (other.status || 'planned') === 'planned'))
		},
	},

	methods: {
		/**
		 * Write the member's choice, then mark the release.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
		 */
		async ship() {
			this.saving = true
			this.submitError = ''
			const [mode, target] = this.open.length === 0 ? ['keep', null] : this.choice.split(':')
			const result = await useProjectsStore().shipRelease(this.release, this.tasks, mode, target || null)
			this.saving = false
			if (!result.ok) {
				this.submitError = this.t('planninq', 'Could not mark the release as released. Please try again.')
				return
			}
			this.$emit('shipped')
		},
	},
}
</script>

<style scoped>
.release-ship-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(480px, 90vw);
}

.release-ship-dialog__tasks {
	margin: 0;
	padding-inline-start: 20px;
	list-style: disc;
}

.release-ship-dialog__choices {
	border: none;
	margin: 0;
	padding: 0;
}

.release-ship-dialog__choices legend {
	font-weight: 600;
	margin-bottom: 4px;
}

.release-ship-dialog__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
