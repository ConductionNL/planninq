<template>
	<NcDialog
		:name="t('planninq', 'Rules for {column}', { column: column.title })"
		@closing="$emit('close')">
		<template #default>
			<div class="column-rules-dialog__body">
				<p class="column-rules-dialog__intro">
					{{ t('planninq', 'When a card enters this column') }}
				</p>

				<p v-if="!rules.length" class="column-rules-dialog__empty" data-testid="column-rules-empty">
					{{ t('planninq', 'No rules yet. Add one to change a card when it enters this column.') }}
				</p>

				<ol class="column-rules-dialog__list">
					<li
						v-for="(rule, index) in rules"
						:key="rule.key"
						class="column-rules-dialog__rule"
						data-testid="column-rule">
						<NcSelect
							:modelValue="actionOptions.find((option) => option.id === rule.action) || null"
							:options="actionOptions"
							:clearable="false"
							:inputLabel="t('planninq', 'Action')"
							label="label"
							data-testid="column-rule-action"
							@update:modelValue="setAction(index, $event)" />
						<NcSelect
							v-if="valueOptionsFor(rule)"
							:modelValue="valueOptionsFor(rule).find((option) => option.id === rule.value) || null"
							:options="valueOptionsFor(rule)"
							:clearable="false"
							:inputLabel="valueLabel(rule.action)"
							label="label"
							data-testid="column-rule-value"
							@update:modelValue="rule.value = $event ? $event.id : ''" />
						<p v-if="problemText(rule)" class="column-rules-dialog__problem" data-testid="column-rule-problem">
							{{ problemText(rule) }}
						</p>
						<NcButton
							variant="tertiary"
							:aria-label="t('planninq', 'Remove rule')"
							data-testid="column-rule-remove"
							@click="rules.splice(index, 1)">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
						</NcButton>
					</li>
				</ol>

				<NcButton variant="secondary" data-testid="column-rule-add" @click="addRule">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('planninq', 'Add rule') }}
				</NcButton>

				<div v-if="submitError" class="column-rules-dialog__error" role="alert">
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
				:disabled="saving || !isValid"
				data-testid="column-rules-save"
				@click="save">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ColumnRulesDialog.
 *
 * The project owner's "when a card enters this column" rules for one board
 * column: set the priority, assign a project member, assign the person who
 * moved the card, remove the assignee, or add a label. The pickers offer only
 * valid values: this project's members, existing labels, the task schema's
 * priorities. A stored rule whose person left the project, or whose label was
 * deleted, is marked; the server skips it. The write is a PATCH of the
 * column's `automation`, which the server refuses unless the caller owns the
 * project or is an admin (ColumnOwnerGuardListener).
 *
 * @spec openspec/changes/boards-column-automation/tasks.md#task-2.1
 */
import { NcButton, NcDialog, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { useProjectsStore } from '../store/projects.js'
import { columnRules, RULE_PRIORITIES, ruleIsComplete, ruleProblem, rulesPayload } from '../utils/columnAutomation.js'
import { labelId } from '../utils/labelHelpers.js'
import { memberOptions } from '../utils/taskPeople.js'
import { displayNames } from '../utils/userNames.js'

let nextKey = 0

export default {
	name: 'ColumnRulesDialog',

	components: { NcButton, NcDialog, NcLoadingIcon, NcSelect, DeleteIcon, PlusIcon },

	props: {
		/** The column whose rules are edited. */
		column: {
			type: Object,
			required: true,
		},

		/** The column's project: its owner and members are the people a rule may assign. */
		project: {
			type: Object,
			required: true,
		},

		/** The existing labels. */
		labels: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			rules: columnRules(this.column).map((rule) => ({ key: nextKey++, action: rule.action, value: rule.value ?? '' })),
			names: {},
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Display helper: the action picker's options.
		 */
		actionOptions() {
			return [
				{ id: 'setPriority', label: this.t('planninq', 'Set the priority') },
				{ id: 'assign', label: this.t('planninq', 'Assign to') },
				{ id: 'assignMover', label: this.t('planninq', 'Assign the person who moved the card') },
				{ id: 'unassign', label: this.t('planninq', 'Remove the assignee') },
				{ id: 'addLabel', label: this.t('planninq', 'Add a label') },
			]
		},

		/**
		 * The project's members and owner, the only people a rule may assign.
		 *
		 * @spec openspec/changes/boards-column-automation/tasks.md#task-2.1
		 */
		peopleOptions() {
			return memberOptions(this.project, this.names)
		},

		/**
		 * @spec exclude Display helper: the label picker's options.
		 */
		labelOptions() {
			return this.labels.map((label) => ({ id: labelId(label), label: label.title || '' }))
		},

		/**
		 * @spec exclude Display helper: the priority picker's options.
		 */
		priorityOptions() {
			const labels = {
				low: this.t('planninq', 'Low'),
				normal: this.t('planninq', 'Normal'),
				high: this.t('planninq', 'High'),
				urgent: this.t('planninq', 'Urgent'),
			}
			return RULE_PRIORITIES.map((id) => ({ id, label: labels[id] }))
		},

		/**
		 * @spec exclude Form validation summary.
		 */
		isValid() {
			return this.rules.every(ruleIsComplete)
		},
	},

	async mounted() {
		this.names = await displayNames(this.peopleOptions.map((option) => option.id))
	},

	methods: {
		/**
		 * @param {object} rule A rule.
		 * @return {Array<object>|null} The value picker's options, or null for an action without a value.
		 * @spec exclude Display helper: the value picker for the rule's action.
		 */
		valueOptionsFor(rule) {
			switch (rule.action) {
				case 'setPriority':
					return this.priorityOptions
				case 'assign':
					return this.peopleOptions
				case 'addLabel':
					return this.labelOptions
				default:
					return null
			}
		},

		/**
		 * @param {string} action A rule action.
		 * @return {string} The value picker's label.
		 * @spec exclude Display helper: the value picker's label.
		 */
		valueLabel(action) {
			if (action === 'setPriority') {
				return this.t('planninq', 'Priority')
			}
			return action === 'assign' ? this.t('planninq', 'Person') : this.t('planninq', 'Label')
		},

		/**
		 * The marker for a stored rule that the server skips now.
		 *
		 * @param {object} rule A rule.
		 * @return {string}
		 *
		 * @spec openspec/changes/boards-column-automation/tasks.md#task-2.1
		 */
		problemText(rule) {
			const problem = ruleProblem(rule, this.peopleOptions.map((option) => option.id), this.labelOptions.map((option) => option.id))
			if (problem === 'formerMember') {
				return this.t('planninq', 'No longer a project member')
			}
			return problem === 'missingLabel' ? this.t('planninq', 'This label no longer exists') : ''
		},

		/**
		 * @param {number}      index  The rule's position.
		 * @param {object|null} option The chosen action.
		 * @spec exclude Form state: a new action clears the old value.
		 */
		setAction(index, option) {
			this.rules.splice(index, 1, { key: this.rules[index].key, action: option?.id || '', value: '' })
		},

		/**
		 * @spec exclude Form state: append an empty rule.
		 */
		addRule() {
			this.rules.push({ key: nextKey++, action: 'assignMover', value: '' })
		},

		/**
		 * PATCH the column's rules and tell the board.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-column-automation/tasks.md#task-2.1
		 */
		async save() {
			if (!this.isValid) {
				return
			}
			this.saving = true
			this.submitError = ''
			const saved = await useProjectsStore().saveColumn({ id: this.column.id, automation: rulesPayload(this.rules) })
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'Could not save the rules. Please try again.')
				return
			}
			this.$emit('saved', saved)
		},
	},
}
</script>

<style scoped>
.column-rules-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 8px;
}

.column-rules-dialog__intro {
	font-weight: bold;
}

.column-rules-dialog__list {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.column-rules-dialog__rule {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.column-rules-dialog__problem,
.column-rules-dialog__error {
	color: var(--color-error-text);
	flex-basis: 100%;
}

.column-rules-dialog__empty {
	color: var(--color-text-maxcontrast);
}
</style>
