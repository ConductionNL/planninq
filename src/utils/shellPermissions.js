/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The permission flags the app shell filters the menu by.
 *
 * @spec openspec/specs/app-shell-and-data-store/spec.md
 */

/**
 * The permissions of the signed-in person, for CnAppRoot's `permissions` prop.
 *
 * Never empty: nextcloud-vue reads an empty list as "do not filter", which is
 * how a regular user came to see the admin-only Timetable entry. Nextcloud has
 * no `OC.currentUser.permissions`; whether the person is an administrator is
 * what the manifest's `permission: "admin"` entries ask.
 *
 * @param {{isAdmin?: boolean}|null|undefined} user The current user, as @nextcloud/auth answers it.
 * @return {string[]}
 *
 * @spec openspec/specs/app-shell-and-data-store/spec.md
 */
export function shellPermissions(user) {
	return user?.isAdmin === true ? ['user', 'admin'] : ['user']
}
