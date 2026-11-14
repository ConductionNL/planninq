/**
 * Logged hours live once, on humaniq's `TimeEntry` (plannedtimeentry-reads-humaniqs-hours).
 *
 * planninq keeps a `plannedTimeEntry` beside each one, holding only what the
 * owner has no column for (`hourlyRate`, `contractorRef`) plus the reference
 * to the humaniq entry. This module turns the two into the one entry the
 * pages already read (`duration` in minutes, `user`, `date`, `task`,
 * `project`), and turns a page's entry back into the two writes.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
import { isAppInstalled } from '@conduction/nextcloud-vue'

/** The app that owns the hours. */
export const HOURS_APP = 'humaniq'

/** humaniq's register slug. */
export const OWNER_REGISTER = 'humaniq'

/** humaniq's time entry schema slug. */
export const OWNER_SCHEMA = 'TimeEntry'

/**
 * The object store key the humaniq entry is registered under. Not the bare
 * schema slug, so it cannot collide with a planninq type of the same name.
 */
export const OWNER_TYPE = 'humaniqTimeEntry'

/** planninq's own half: the rate and the reference. */
export const PLANNED_SCHEMA = 'plannedTimeEntry'

/**
 * The `<app>:<schema>` humaniq records a planninq task under. The same value
 * the MoveLoggedHoursToHumaniq repair step writes, so moved and new entries
 * are found by the same filter.
 */
export const DOMAIN_OBJECT_TYPE = 'planninq:task'

/**
 * The fields a page edits that belong to the owner. `billable` is not one: on
 * planninq's side only a project manager sets it (TimeEntryGuardListener), and
 * this store does not hand that decision to a write the guard never sees.
 */
const OWNER_FIELDS = ['duration', 'date', 'description']

/** The fields a page edits that stay on the plannedTimeEntry. */
const PLANNED_FIELDS = ['hourlyRate', 'contractorRef']

/**
 * Whether the app that owns the hours is installed and enabled for this user.
 *
 * @return {boolean}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-time-widgets-hide-when-humaniq-is-absent
 */
export function hoursAppAvailable() {
	try {
		return isAppInstalled(HOURS_APP) === true
	} catch {
		return false
	}
}

/**
 * Minutes to hours, to two decimals, as the repair step converts them.
 *
 * @param {number|string} minutes Logged minutes.
 * @return {number}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
export function minutesToHours(minutes) {
	const value = Number(minutes)
	if (!Number.isFinite(value) || value <= 0) {
		return 0
	}
	return Math.round((value / 60) * 100) / 100
}

/**
 * Hours to whole minutes, so 1.5 reads as the 90 the user typed.
 *
 * @param {number|string} hours Hours on the owner's entry.
 * @return {number}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
 */
export function hoursToMinutes(hours) {
	const value = Number(hours)
	if (!Number.isFinite(value) || value <= 0) {
		return 0
	}
	return Math.round(value * 60)
}

/**
 * The humaniq `TimeEntry` a logged entry writes.
 *
 * @param {{task?: string, project?: string, user: string, date: string, duration: number, description?: string}} entry The entry as a page gives it.
 * @return {object} The owner's object.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
export function ownerEntryOf(entry) {
	const owner = {
		date: String(entry.date || ''),
		hours: minutesToHours(entry.duration),
		userId: String(entry.user || ''),
		description: String(entry.description || ''),
		origin: 'manual',
	}
	if (entry.project) {
		owner.projectId = String(entry.project)
	}
	if (entry.task) {
		owner.domainObjectType = DOMAIN_OBJECT_TYPE
		owner.domainObjectRef = String(entry.task)
	}
	return owner
}

/**
 * The plannedTimeEntry that sits beside a humaniq entry. It carries no hours.
 * `task` and `user` stay on it as keys, not as data: the server derives the
 * project and its members from `task` (ProjectMembershipService), a task with
 * logged time refuses deletion by it, and the schema's access rules match on
 * `user`.
 *
 * @param {string} ownerId The humaniq entry's uuid.
 * @param {object} entry The entry as a page gives it.
 * @return {object}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-logged-hours-are-stored-once-on-humaniqs-time-entry
 */
export function plannedEntryOf(ownerId, entry) {
	const planned = { timeEntry: String(ownerId), user: String(entry.user || '') }
	if (entry.task) {
		planned.task = String(entry.task)
	}
	for (const field of PLANNED_FIELDS) {
		if (entry[field] !== undefined && entry[field] !== null && entry[field] !== '') {
			planned[field] = entry[field]
		}
	}
	return planned
}

/**
 * A page's edit split into the owner's half and planninq's half.
 *
 * @param {object} patch The edited fields, `duration` in minutes.
 * @return {{owner: object, planned: object}}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#scenario-a-rate-stays-in-planninq
 */
export function splitPatch(patch) {
	const owner = {}
	const planned = {}
	for (const [key, value] of Object.entries(patch || {})) {
		if (OWNER_FIELDS.includes(key)) {
			if (key === 'duration') {
				owner.hours = minutesToHours(value)
			} else {
				owner[key] = value
			}
		} else if (PLANNED_FIELDS.includes(key)) {
			planned[key] = value
		}
	}
	return { owner, planned }
}

/**
 * The id of an object as the object store returns it.
 *
 * @param {object} row An object.
 * @return {string}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
 */
export function idOf(row) {
	return String(row?.id ?? row?.uuid ?? row?.['@self']?.id ?? '')
}

/**
 * The one entry a page reads: the owner's hours with planninq's rate.
 *
 * @param {object} owner The humaniq `TimeEntry`.
 * @param {object|null} planned The plannedTimeEntry referencing it, if any.
 * @return {object}
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
 */
export function viewEntryOf(owner, planned = null) {
	const id = idOf(owner)
	const task = owner.domainObjectType === DOMAIN_OBJECT_TYPE ? (owner.domainObjectRef || null) : null
	return {
		id,
		timeEntry: id,
		plannedId: planned ? idOf(planned) : null,
		task,
		project: owner.projectId || null,
		user: owner.userId || '',
		date: owner.date || '',
		duration: hoursToMinutes(owner.hours),
		description: owner.description || '',
		billable: owner.billable === true,
		hourlyRate: planned?.hourlyRate ?? null,
		contractorRef: planned?.contractorRef ?? null,
	}
}

/**
 * Join humaniq entries with the plannedTimeEntry rows that reference them.
 *
 * @param {Array<object>} owners humaniq `TimeEntry` objects.
 * @param {Array<object>} planned plannedTimeEntry objects.
 * @return {Array<object>} One page entry per humaniq entry.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
 */
export function joinEntries(owners, planned) {
	const byOwner = new Map()
	for (const row of Array.isArray(planned) ? planned : []) {
		if (row?.timeEntry) {
			byOwner.set(String(row.timeEntry), row)
		}
	}
	return (Array.isArray(owners) ? owners : [])
		.filter((owner) => idOf(owner) !== '')
		.map((owner) => viewEntryOf(owner, byOwner.get(idOf(owner)) || null))
}

/**
 * Register the two object types on the shared object store.
 *
 * @param {object} store The object store.
 * @param {string} register planninq's register slug.
 * @return {object} The same store.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/tasks.md
 */
export function registerHourTypes(store, register) {
	if (!store.objectTypeRegistry?.[OWNER_TYPE]) {
		store.registerObjectType(OWNER_TYPE, OWNER_SCHEMA, OWNER_REGISTER, { registerSlug: OWNER_REGISTER, schemaSlug: OWNER_SCHEMA })
	}
	if (!store.objectTypeRegistry?.[PLANNED_SCHEMA]) {
		store.registerObjectType(PLANNED_SCHEMA, PLANNED_SCHEMA, register, { registerSlug: register, schemaSlug: PLANNED_SCHEMA })
	}
	return store
}

/**
 * Read the humaniq entries a filter selects and the planninq rows beside
 * them. The planninq rows are read per entry, by their reference, because
 * that reference is the only field the two share.
 *
 * @param {object} store The object store, with both types registered.
 * @param {object} ownerFilter The filter on humaniq's `TimeEntry`.
 * @param {function(object, string, object): Promise<Array>} fetchAll Reads every row of a type for a filter.
 * @return {Promise<Array<object>>} The page entries.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-planninq-pages-read-the-hours-from-humaniq
 */
export async function readEntries(store, ownerFilter, fetchAll) {
	const owners = await fetchAll(store, OWNER_TYPE, ownerFilter)
	const list = Array.isArray(owners) ? owners : []
	const planned = await Promise.all(list.map((owner) => fetchAll(store, PLANNED_SCHEMA, { timeEntry: idOf(owner) })
		.then((rows) => (Array.isArray(rows) ? rows : []))
		.catch(() => [])))
	return joinEntries(list, planned.flat())
}
