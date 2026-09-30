<template>
	<NcDialog
		v-if="show"
		:name="item ? t('planninq', 'Edit wish') : t('planninq', 'New wish')"
		size="normal"
		@closing="close()">
		<template #default>
			<div class="timetable-wish-dialog__body">
				<NcSelect
					v-model="form.appliesTo"
					:options="appliesToOptions"
					:reduce="(option) => option.id"
					label="label"
					:clearable="false"
					:inputLabel="t('planninq', 'Applies to')"
					data-testid="wish-applies-to" />
				<NcTextField
					v-model="form.reference"
					:label="referenceLabel"
					:error="hasProblem('reference')"
					data-testid="wish-reference"
					required />
				<NcSelect
					v-model="form.kind"
					:options="kindOptions"
					:reduce="(option) => option.id"
					label="label"
					:clearable="false"
					:inputLabel="t('planninq', 'Kind')"
					data-testid="wish-kind" />

				<fieldset class="timetable-wish-dialog__strength">
					<legend>{{ t('planninq', 'Strength') }}</legend>
					<NcCheckboxRadioSwitch
						v-model="form.strength"
						value="hard"
						name="wish-strength"
						type="radio"
						data-testid="wish-strength-hard">
						{{ t('planninq', 'Hard: the generator always keeps it') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						v-model="form.strength"
						value="soft"
						name="wish-strength"
						type="radio"
						data-testid="wish-strength-soft">
						{{ t('planninq', 'Soft: the generator keeps it where it can') }}
					</NcCheckboxRadioSwitch>
				</fieldset>

				<NcTextField
					v-if="form.strength === 'soft'"
					v-model="form.weight"
					type="number"
					min="1"
					max="3"
					:label="t('planninq', 'Weight (1 to 3)')"
					data-testid="wish-weight" />

				<NcTextField
					v-if="form.kind === 'maxPerDay'"
					v-model="form.limit"
					type="number"
					min="1"
					:label="t('planninq', 'Most lessons a day')"
					:error="hasProblem('limit')"
					data-testid="wish-limit" />

				<table v-if="namesPeriods" class="timetable-wish-dialog__grid" data-testid="wish-periods">
					<caption>{{ t('planninq', 'Periods') }}</caption>
					<thead>
						<tr>
							<th scope="col">
								{{ t('planninq', 'Period') }}
							</th>
							<th v-for="day in grid.days" :key="day" scope="col">
								{{ dayLabel(day) }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in rows" :key="row.number">
							<th scope="row">
								{{ row.number }} <span class="timetable-wish-dialog__time">{{ row.start }}-{{ row.end }}</span>
							</th>
							<td v-for="cell in row.cells" :key="cell.key">
								<NcCheckboxRadioSwitch
									:modelValue="cell.selected"
									:aria-label="t('planninq', '{day} period {number}', { day: dayLabel(cell.day), number: row.number })"
									:data-testid="'wish-period-' + cell.key"
									@update:modelValue="togglePeriod(cell.key)" />
							</td>
						</tr>
					</tbody>
				</table>

				<NcTextArea
					v-model="form.note"
					:label="t('planninq', 'Note')"
					resize="vertical" />

				<ul v-if="shownProblems.length > 0" class="timetable-wish-dialog__error" role="alert">
					<li v-for="problem in shownProblems" :key="problem.field">
						{{ t('planninq', problem.message) }}
					</li>
				</ul>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="saving" @click="close()">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving"
				data-testid="wish-save"
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
 * TimetableWishDialog.
 *
 * The create and edit dialog of the wish list (manifest page TimetableWishes,
 * mounted in CnIndexPage's form-dialog slot). A wish is hard or soft; a soft
 * wish has a weight; the periods are chosen on the school week grid from the
 * `timetable_period_grid` setting. Saving goes through the page's own save
 * path (`confirm`), so the list refreshes as after any other edit.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
 */
import { getDayNames } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcLoadingIcon, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { gridRows, PERIOD_KINDS, readGrid, togglePeriod, wishForm, wishPayload, wishProblems } from '../utils/timetableWishes.js'

export default {
	name: 'TimetableWishDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** Whether the page shows the dialog. */
		show: {
			type: Boolean,
			default: false,
		},

		/** The wish to edit, or null for a new one. */
		item: {
			type: Object,
			default: null,
		},

		/** Saves the complete object through the page. */
		confirm: {
			type: Function,
			required: true,
		},

		/** Closes the dialog. */
		close: {
			type: Function,
			required: true,
		},
	},

	data() {
		return {
			form: wishForm(this.item),
			saving: false,
			tried: false,
		}
	},

	computed: {
		/**
		 * The week grid from the settings, the default grid until they are read.
		 *
		 * @return {object}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		grid() {
			return readGrid(useSettingsStore().settings?.timetable_period_grid ?? null)
		},

		/**
		 * The rows of the period picker.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		rows() {
			return gridRows(this.grid, this.form.periods)
		},

		/**
		 * Whether the chosen kind names periods.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		namesPeriods() {
			return PERIOD_KINDS.includes(this.form.kind)
		},

		/**
		 * The problems, shown once the user tried to save.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		shownProblems() {
			return this.tried ? wishProblems(this.form) : []
		},

		/**
		 * The label of the reference field for what the wish applies to.
		 *
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		referenceLabel() {
			return {
				teacher: this.t('planninq', 'Teacher (user name)'),
				group: this.t('planninq', 'Group'),
				room: this.t('planninq', 'Room'),
				activity: this.t('planninq', 'Activity (group:subject)'),
			}[this.form.appliesTo]
		},

		/**
		 * The choices for what a wish applies to.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		appliesToOptions() {
			return [
				{ id: 'teacher', label: this.t('planninq', 'Teacher') },
				{ id: 'group', label: this.t('planninq', 'Group') },
				{ id: 'room', label: this.t('planninq', 'Room') },
				{ id: 'activity', label: this.t('planninq', 'Activity') },
			]
		},

		/**
		 * The wish kinds.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		kindOptions() {
			return [
				{ id: 'unavailable', label: this.t('planninq', 'Not on these periods') },
				{ id: 'avoid', label: this.t('planninq', 'Preferably not on these periods') },
				{ id: 'maxPerDay', label: this.t('planninq', 'At most a number of lessons a day') },
				{ id: 'noGaps', label: this.t('planninq', 'No free periods between lessons') },
				{ id: 'sameRoom', label: this.t('planninq', 'Always the same room') },
			]
		},
	},

	watch: {
		/**
		 * The page keeps the dialog mounted and toggles it: every opening starts from the item.
		 *
		 * @param {boolean} show Whether the dialog opens.
		 * @return {void}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		show(show) {
			if (show) {
				this.form = wishForm(this.item)
				this.tried = false
			}
		},
	},

	/**
	 * Read the settings once, for the week grid.
	 *
	 * @return {void}
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
	 */
	created() {
		const settings = useSettingsStore()
		if (!settings.settings?.timetable_period_grid && typeof settings.fetchSettings === 'function') {
			settings.fetchSettings()
		}
	},

	methods: {
		/**
		 * Switch one period on or off.
		 *
		 * @param {string} key The period key.
		 * @return {void}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		togglePeriod(key) {
			this.form.periods = togglePeriod(this.form.periods, key)
		},

		/**
		 * Whether a field has a problem that is shown.
		 *
		 * @param {string} field The field.
		 * @return {boolean}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		hasProblem(field) {
			return this.shownProblems.some((problem) => problem.field === field)
		},

		/**
		 * The name of a week day, in the user's language (Nextcloud's own day names).
		 *
		 * @param {string} day The day key.
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		dayLabel(day) {
			const index = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'].indexOf(day)
			return index === -1 ? day : getDayNames()[index]
		},

		/**
		 * Save the wish through the page, then close.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-3.1
		 */
		async save() {
			this.tried = true
			if (wishProblems(this.form).length > 0) {
				return
			}
			this.saving = true
			const payload = wishPayload(this.form)
			await this.confirm(this.item ? { ...this.item, ...payload } : payload)
			this.saving = false
			this.close()
		},
	},
}
</script>

<style scoped>
.timetable-wish-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(560px, 90vw);
}

.timetable-wish-dialog__strength {
	border: none;
	padding: 0;
	margin: 0;
}

.timetable-wish-dialog__grid {
	border-collapse: collapse;
	width: 100%;
}

.timetable-wish-dialog__grid caption {
	text-align: start;
	font-weight: bold;
	padding-block-end: 4px;
}

.timetable-wish-dialog__grid th,
.timetable-wish-dialog__grid td {
	border: 1px solid var(--color-border);
	padding: 2px 4px;
	text-align: center;
}

.timetable-wish-dialog__time {
	color: var(--color-text-maxcontrast);
	font-weight: normal;
	white-space: nowrap;
}

.timetable-wish-dialog__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
