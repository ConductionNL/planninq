import { buildHeaders } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
/**
 * TimeEntry Pinia store.
 *
 * Logged hours live once, on humaniq's `TimeEntry`
 * (plannedtimeentry-reads-humaniqs-hours): this store writes the hours there
 * and keeps a planninq `plannedTimeEntry` beside each one for the rate and the
 * contractor, both through OpenRegister's object API (ADR-022 — consumed, not
 * reimplemented). The pages keep reading one entry with `duration` in
 * minutes; `src/utils/loggedHours.js` joins the two halves.
 *
 * Without humaniq there is nowhere for the hours to go, so reads return
 * nothing and a write is refused with a sentence that names humaniq.
 *
 * The per-owner authorization guard lives on both schemas (humaniq matches
 * `userId`, planninq matches `user`), so a direct non-owner write is refused
 * server-side; `canModify` only hides controls the API would refuse.
 *
 * SPDX-FileCopyrightText: 2026 Planninq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/time-tracking/spec.md
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md
 */
import { defineStore } from 'pinia'
import { fetchEvery } from '../utils/fetchEvery.js'
import { localIsoDate } from '../utils/localDate.js'
import {
	DOMAIN_OBJECT_TYPE,
	hoursAppAvailable,
	idOf,
	joinEntries,
	OWNER_REGISTER,
	OWNER_SCHEMA,
	OWNER_TYPE,
	ownerEntryOf,
	PLANNED_SCHEMA,
	plannedEntryOf,
	readEntries,
	registerHourTypes,
	splitPatch,
} from '../utils/loggedHours.js'
import { responseBody } from '../utils/responseBody.js'
import { useObjectStore } from './objectStore.js'

// The OpenRegister register SLUG, not the app id. It moved from `planix` to
// `planninq` together with the MigrateRegisterSlug repair step, which renames
// the register ROW: OR resolves a register by slug and by nothing else, so the
// literal and the row move in the same release or neither resolves.
const REGISTER = 'planninq'

/**
 * The refusal when humaniq is not there to hold the hours.
 *
 * @return {string}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#scenario-no-humaniq-logging-time-says-why
 */
export function hoursAppMissingText() {
	return t('planninq', 'Time logging needs the humaniq app')
}
export const useTimeEntriesStore = defineStore('timeEntries', {
	state: () => ({
		/** @type {Array} Entries currently loaded (task- or user-scoped). */
		entries: [],
		/** @type {boolean} */ loading: false,
		/** @type {string|null} */ error: null,
	}),

	getters: {
		/**
		 * Total logged minutes across the currently-loaded entries.
		 *
		 * @param {object} state Store state.
		 * @return {number} Sum of durations.
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		totalMinutes: (state) => state.entries.reduce((acc, e) => acc + (Number(e.duration) || 0), 0),
	},

	actions: {
		/**
		 * @spec exclude Internal helper — lazily registers humaniq's TimeEntry and the plannedTimeEntry on the shared object store.
		 */
		_objectStore() {
			return registerHourTypes(useObjectStore(), REGISTER)
		},

		/**
		 * @spec exclude Auth passthrough — the current user's UID.
		 */
		_currentUid() {
			return getCurrentUser()?.uid || ''
		},

		/**
		 * Whether the current user may edit/delete `entry` — the entry's owner
		 * or an admin. Mirrors the schema RBAC so the UI hides controls the API
		 * would reject (defence in depth).
		 *
		 * @param {object} entry The time entry.
		 * @return {boolean}
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		canModify(entry) {
			const uid = this._currentUid()
			const admin = getCurrentUser()?.isAdmin === true
			return admin || (!!uid && entry?.user === uid)
		},

		/**
		 * Read the entries a humaniq filter selects into the store. Without
		 * humaniq there are none, and the store says why.
		 *
		 * @param {object} ownerFilter The filter on humaniq's TimeEntry.
		 * @return {Promise<Array>}
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
		 */
		async _load(ownerFilter) {
			this.loading = true
			this.error = null
			try {
				if (!hoursAppAvailable()) {
					this.entries = []
					this.error = hoursAppMissingText()
					return []
				}
				this.entries = await readEntries(this._objectStore(), ownerFilter, fetchEvery)
				return this.entries
			} catch (err) {
				this.error = err?.message || 'fetch-error'
				console.error('timeEntries fetch error:', err)
				this.entries = []
				return []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Load the time entries of a task, from humaniq. humaniq shows a person
		 * their own entries; an HR officer sees everyone's.
		 *
		 * @param {string} taskId Task UUID.
		 * @return {Promise<Array>}
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
		 */
		async fetchForTask(taskId) {
			return this._load({ domainObjectType: DOMAIN_OBJECT_TYPE, domainObjectRef: taskId })
		},

		/**
		 * Load the current user's time entries (for the timesheet), from
		 * humaniq, filtered on the server by `userId` and again here, so the
		 * timesheet never shows another person's row an HR officer may read.
		 *
		 * @return {Promise<Array>}
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#scenario-the-timesheet-shows-the-humaniq-hours
		 */
		async fetchForCurrentUser() {
			const uid = this._currentUid()
			const entries = await this._load(uid ? { userId: uid } : {})
			if (uid) {
				this.entries = entries.filter((e) => e.user === uid)
			}
			return this.entries
		},

		/**
		 * Log time: one humaniq `TimeEntry` holding the hours and one
		 * `plannedTimeEntry` referencing it. When the second write fails the
		 * first is removed again, so no hours are left without their planninq
		 * row. Without humaniq nothing is written.
		 *
		 * @param {{task: string, project?: string, duration: number, date?: string, description?: string, hourlyRate?: number, contractorRef?: string}} data Entry fields, `duration` in minutes.
		 * @return {Promise<object|null>} The created entry, or null on failure.
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#scenario-logging-time-writes-one-humaniq-time-entry
		 */
		async create(data) {
			this.error = null
			if (!hoursAppAvailable()) {
				this.error = hoursAppMissingText()
				return null
			}
			const entry = { user: this._currentUid(), date: localIsoDate(), ...data }
			try {
				const objectStore = this._objectStore()
				const owner = await objectStore.saveObject(OWNER_TYPE, ownerEntryOf(entry))
				const ownerId = idOf(owner)
				if (!owner || ownerId === '') {
					this.error = objectStore.error || 'create-error'
					return null
				}
				const planned = await this._savePlanned(objectStore, ownerId, entry)
				if (!planned) {
					await objectStore.deleteObject(OWNER_TYPE, ownerId)
					this.error = this.error || 'create-error'
					return null
				}
				const [saved] = joinEntries([owner], [planned])
				this.entries = [...this.entries, saved]
				return saved
			} catch (err) {
				this.error = err?.message || 'create-error'
				console.error('timeEntry create error:', err)
				return null
			}
		},

		/**
		 * @param {object} objectStore The object store.
		 * @param {string} ownerId The humaniq entry's uuid.
		 * @param {object} entry The entry as the page gave it.
		 * @return {Promise<object|null>} The saved planninq row, or null.
		 *
		 * @spec exclude Internal helper — writes the planninq half, keeping a thrown failure as `null`.
		 */
		async _savePlanned(objectStore, ownerId, entry) {
			try {
				const planned = await objectStore.saveObject(PLANNED_SCHEMA, plannedEntryOf(ownerId, entry))
				return planned || null
			} catch (err) {
				this.error = err?.message || 'create-error'
				return null
			}
		},

		/**
		 * Update a time entry: the hours, date, note and billability on
		 * humaniq's entry, the rate and contractor on planninq's. Each half is
		 * written only when it changed.
		 *
		 * PATCH, not PUT: OpenRegister's PUT nulls every property it is not
		 * sent, which would wipe the entry's author and project.
		 *
		 * @param {string} id The humaniq entry's UUID (the page entry's `id`).
		 * @param {object} patch Fields to change, `duration` in minutes.
		 * @return {Promise<object|null>} Updated entry or null on failure.
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#scenario-a-rate-stays-in-planninq
		 */
		async update(id, patch) {
			const { owner, planned } = splitPatch(patch)
			const current = this.entries.find((e) => e.id === id) || null
			const changes = { ...patch }
			try {
				if (Object.keys(owner).length > 0) {
					const ok = await this._patch(OWNER_REGISTER, OWNER_SCHEMA, id, owner)
					if (!ok) {
						return null
					}
				}
				if (Object.keys(planned).length > 0) {
					const plannedId = current?.plannedId
					if (!plannedId) {
						this.error = 'update-error'
						return null
					}
					const ok = await this._patch(REGISTER, PLANNED_SCHEMA, plannedId, planned)
					if (!ok) {
						return null
					}
				}
				const saved = { ...(current || { id }), ...changes }
				this._mergeEntry(id, saved)
				return saved
			} catch (err) {
				this.error = err?.message || 'update-error'
				console.error('timeEntry update error:', err)
				return null
			}
		},

		/**
		 * @param {string} register The register slug.
		 * @param {string} schema The schema slug.
		 * @param {string} id The object uuid.
		 * @param {object} body The fields to change.
		 * @return {Promise<boolean>} True when the server took the change.
		 *
		 * @spec exclude Internal helper — one PATCH, keeping the server's refusal in `error`.
		 */
		async _patch(register, schema, id, body) {
			const response = await fetch(generateUrl(`/apps/openregister/api/objects/${register}/${schema}/${id}`), {
				method: 'PATCH',
				headers: buildHeaders(),
				body: JSON.stringify(body),
			})
			if (!response.ok) {
				const errorData = await responseBody(response)
				this.error = errorData?.message || 'update-error'
				return false
			}
			return true
		},

		/**
		 * Merge a saved entry into the loaded list; an empty answer changes nothing.
		 *
		 * @param {string} id Entry UUID.
		 * @param {object|null} saved The saved entry.
		 *
		 * @spec openspec/specs/time-tracking/spec.md
		 */
		_mergeEntry(id, saved) {
			if (saved) {
				this.entries = this.entries.map((e) => (e.id === id ? { ...e, ...saved } : e))
			}
		},

		/**
		 * Delete a time entry: humaniq's entry, then planninq's row beside it.
		 *
		 * @param {string} id The humaniq entry's UUID (the page entry's `id`).
		 * @return {Promise<boolean>} True on success.
		 *
		 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
		 */
		async delete(id) {
			try {
				const objectStore = this._objectStore()
				const plannedId = this.entries.find((e) => e.id === id)?.plannedId || null
				const ok = await objectStore.deleteObject(OWNER_TYPE, id)
				if (!ok) {
					return false
				}
				if (plannedId) {
					await objectStore.deleteObject(PLANNED_SCHEMA, plannedId)
				}
				this.entries = this.entries.filter((e) => e.id !== id)
				return true
			} catch (err) {
				this.error = err?.message || 'delete-error'
				console.error('timeEntry delete error:', err)
				return false
			}
		},
	},
})
