<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<!-- Working days and holidays (planning-timeline-editing): the calendar the timeline plans in -->
	<CnSettingsSection
		:name="t('planninq', 'Working days and holidays')"
		:description="t('planninq', 'Dates set on the timeline skip the days that are not working days.')"
		data-testid="working-calendar-settings">
		<fieldset class="working-calendar__weekdays">
			<legend>{{ t('planninq', 'Working weekdays') }}</legend>
			<NcCheckboxRadioSwitch v-for="day in weekdayOptions"
				:key="day.iso"
				:modelValue="weekdays.includes(day.iso)"
				:data-testid="`working-weekday-${day.iso}`"
				@update:modelValue="toggleWeekday(day.iso, $event)">
				{{ day.label }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<h4>{{ t('planninq', 'Non-working days') }}</h4>
		<p v-if="days.length === 0" class="working-calendar__hint">
			{{ t('planninq', 'No non-working days yet.') }}
		</p>
		<ul v-else class="working-calendar__days" data-testid="working-calendar-days">
			<li v-for="day in days" :key="day.date" data-testid="working-calendar-day">
				<span>{{ longDate(day.date) }}</span>
				<span class="working-calendar__name">{{ day.name }}</span>
				<NcButton variant="tertiary"
					:aria-label="t('planninq', 'Remove {name}', { name: day.name || longDate(day.date) })"
					@click="remove(day.date)">
					{{ t('planninq', 'Remove') }}
				</NcButton>
			</li>
		</ul>

		<div class="working-calendar__add">
			<label>
				{{ t('planninq', 'Date') }}
				<input v-model="newDate" type="date" data-testid="working-calendar-new-date">
			</label>
			<label>
				{{ t('planninq', 'Name') }}
				<input v-model="newName"
					type="text"
					maxlength="100"
					data-testid="working-calendar-new-name">
			</label>
			<NcButton variant="secondary"
				:disabled="!newDate"
				data-testid="working-calendar-add"
				@click="add">
				{{ t('planninq', 'Add day') }}
			</NcButton>
		</div>

		<div class="working-calendar__add">
			<label>
				{{ t('planninq', 'Year') }}
				<input v-model.number="holidayYear"
					type="number"
					min="1900"
					max="2999"
					data-testid="working-calendar-year">
			</label>
			<NcButton variant="secondary" data-testid="working-calendar-dutch" @click="addDutchHolidays">
				{{ t('planninq', 'Add Dutch national holidays') }}
			</NcButton>
		</div>

		<NcButton variant="primary"
			:disabled="saving || weekdays.length === 0"
			data-testid="working-calendar-save"
			@click="save">
			{{ t('planninq', 'Save working days') }}
		</NcButton>
		<p v-if="weekdays.length === 0" class="working-calendar__hint">
			{{ t('planninq', 'Choose at least one working weekday.') }}
		</p>
		<p v-if="message" role="status" :class="{ 'working-calendar__error': !saved }">
			{{ message }}
		</p>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { dutchHolidays, normaliseCalendar } from '../utils/workingCalendar.js'

/**
 * WorkingCalendarSettings: the admin's working weekdays and non-working
 * days, with the Dutch national holidays of a year one button away. The
 * timeline reads both through GET /api/settings.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
 */
export default {
	name: 'WorkingCalendarSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			weekdays: [1, 2, 3, 4, 5],
			days: [],
			newDate: '',
			newName: '',
			holidayYear: new Date().getFullYear() + 1,
			saving: false,
			saved: false,
			message: '',
		}
	},

	computed: {
		/**
		 * Monday to Sunday with their names in the user's language.
		 *
		 * @return {Array<{iso: number, label: string}>}
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		weekdayOptions() {
			return [1, 2, 3, 4, 5, 6, 7].map((iso) => ({
				iso,
				label: new Date(Date.UTC(2026, 9, 11 + iso)).toLocaleDateString(undefined, { weekday: 'long', timeZone: 'UTC' }),
			}))
		},
	},

	/**
	 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
	 */
	async created() {
		const store = useSettingsStore()
		await store.fetchSettings()
		this.load(store.settings)
	},

	methods: {
		t,

		/**
		 * Take the stored calendar into the form.
		 *
		 * @param {object} settings The settings
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		load(settings) {
			const calendar = normaliseCalendar(settings || {})
			this.weekdays = [...calendar.weekdays]
			this.days = [...calendar.holidays.entries()].map(([date, name]) => ({ date, name }))
		},

		/**
		 * @param {number} iso ISO weekday
		 * @param {boolean} on Whether it is a working day
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		toggleWeekday(iso, on) {
			const rest = this.weekdays.filter((day) => day !== iso)
			this.weekdays = on ? [...rest, iso].sort() : rest
		},

		/**
		 * Add or rename entries, keeping one per date in date order.
		 *
		 * @param {Array<{date: string, name: string}>} entries The entries
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		merge(entries) {
			const byDate = new Map(this.days.map((day) => [day.date, day]))
			for (const entry of entries) {
				byDate.set(entry.date, entry)
			}
			this.days = [...byDate.values()].sort((a, b) => a.date.localeCompare(b.date))
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		add() {
			this.merge([{ date: this.newDate, name: this.newName.trim() }])
			this.newDate = ''
			this.newName = ''
		},

		/**
		 * @param {string} date The day to remove
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		remove(date) {
			this.days = this.days.filter((day) => day.date !== date)
		},

		/**
		 * Add the Dutch national holidays of the chosen year, named in the admin's language.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		addDutchHolidays() {
			const names = {
				'New Year\'s Day': t('planninq', 'New Year\'s Day'),
				'Easter Sunday': t('planninq', 'Easter Sunday'),
				'Easter Monday': t('planninq', 'Easter Monday'),
				'King\'s Day': t('planninq', 'King\'s Day'),
				'Liberation Day': t('planninq', 'Liberation Day'),
				'Ascension Day': t('planninq', 'Ascension Day'),
				'Whit Sunday': t('planninq', 'Whit Sunday'),
				'Whit Monday': t('planninq', 'Whit Monday'),
				'Christmas Day': t('planninq', 'Christmas Day'),
				'Boxing Day': t('planninq', 'Boxing Day'),
			}
			const year = Number(this.holidayYear)
			if (Number.isInteger(year) && year >= 1900 && year <= 2999) {
				this.merge(dutchHolidays(year, (name) => names[name] ?? name))
			}
		},

		/**
		 * @param {string} date A `YYYY-MM-DD` key
		 * @return {string} The date in words
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		longDate(date) {
			return new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
		},

		/**
		 * Save both keys, then read them back so the form shows what was stored.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.3
		 */
		async save() {
			this.saving = true
			this.message = ''
			const store = useSettingsStore()
			const result = await store.saveSettings({
				working_weekdays: JSON.stringify(this.weekdays),
				non_working_days: JSON.stringify(this.days),
			})
			await store.fetchSettings()
			this.load(store.settings)
			this.saved = Boolean(result)
			this.message = result ? t('planninq', 'Working days saved.') : t('planninq', 'Could not save the working days.')
			this.saving = false
		},
	},
}
</script>

<style scoped>
.working-calendar__weekdays {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.working-calendar__days {
	list-style: none;
	padding: 0;
}

.working-calendar__days li {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);
}

.working-calendar__name {
	color: var(--color-text-maxcontrast);
}

.working-calendar__add {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	margin: calc(var(--default-grid-baseline) * 2) 0;
}

.working-calendar__add label {
	display: flex;
	flex-direction: column;
}

.working-calendar__hint {
	color: var(--color-text-maxcontrast);
}

.working-calendar__error {
	color: var(--color-error-text);
}
</style>
