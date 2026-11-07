<template>
	<div v-if="offered" class="case-handover" data-testid="case-handover">
		<p class="case-handover__hint">
			{{ t('planninq', 'Copies every file on the project and its tasks, and a file describing the project, to the linked case, unchanged.') }}
		</p>
		<NcButton :disabled="busy" data-testid="case-handover-start" @click="handOver">
			<template v-if="busy" #icon>
				<NcLoadingIcon :size="16" />
			</template>
			{{ t('planninq', 'Hand over to case') }}
		</NcButton>
		<p v-if="error" class="case-handover__error" role="alert">
			{{ error }}
		</p>
		<ul v-if="handovers.length" class="case-handover__list" data-testid="case-handover-list">
			<li v-for="(handover, index) in handovers" :key="index">
				<strong>{{ t('planninq', '{date} by {user}', { date: formatDate(handover.date), user: handover.by }) }}</strong>
				<span>{{ t('planninq', 'Files copied: {count}', { count: (handover.files || []).length }) }}</span>
				<span v-if="(handover.failures || []).length" class="case-handover__error">
					{{ t('planninq', 'Files that failed: {names}', { names: handover.failures.map((failure) => failure.name).join(', ') }) }}
				</span>
			</li>
		</ul>
	</div>
</template>

<script>
/**
 * CaseHandoverSection.
 *
 * "Hand over to case" in the project settings sidebar: shown to the project
 * owner (or an admin) of a project linked to a case, only when the case app
 * is installed. It lists the handovers made so far, newest first, with the
 * number of files copied and any that failed.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
 */
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { fetchHandoverStatus, handOverToCase } from '../api/caseHandover.js'
import { handoverOffered } from '../utils/caseBridge.js'

export default {
	name: 'CaseHandoverSection',

	components: {
		NcButton,
		NcLoadingIcon,
	},

	props: {
		/** The project. */
		project: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			available: false,
			records: [],
			busy: false,
			error: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the shared rule for showing the action.
		 */
		offered() {
			return handoverOffered(this.project, getCurrentUser(), this.available)
		},

		/**
		 * @spec exclude Trivial getter: the handovers, newest first.
		 */
		handovers() {
			return [...this.records].reverse()
		},
	},

	/**
	 * @spec exclude Lifecycle glue: asks the server whether a handover is possible.
	 */
	async mounted() {
		const user = getCurrentUser()
		if (!this.project?.caseReference || !handoverOffered(this.project, user, true)) {
			return
		}
		try {
			const status = await fetchHandoverStatus(this.project.id)
			this.available = status.available
			this.records = status.handovers
		} catch (err) {
			console.error('CaseHandoverSection: status failed', err)
		}
	},

	methods: {
		/**
		 * Hand the project over and add the record to the list.
		 *
		 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
		 */
		async handOver() {
			this.busy = true
			this.error = ''
			try {
				this.records = [...this.records, await handOverToCase(this.project.id)]
			} catch (err) {
				this.error = err?.response?.data?.reason === 'caseNotFound'
					? this.t('planninq', 'The linked case could not be found, or you cannot open it.')
					: this.t('planninq', 'The handover failed. Please try again.')
			}
			this.busy = false
		},

		/**
		 * @param {string} value An ISO date-time.
		 * @return {string}
		 * @spec exclude Presentational date format.
		 */
		formatDate(value) {
			const date = new Date(value)
			return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleString()
		},
	},
}
</script>

<style scoped>
.case-handover {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.case-handover__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.case-handover__error {
	color: var(--color-error-text);
}

.case-handover__list {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.case-handover__list li {
	display: flex;
	flex-direction: column;
}
</style>
