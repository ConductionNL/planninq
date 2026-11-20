/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Admin API helpers for the portfolio e2e suites: create a portfolio, a
 * project in it and a status report, and remove what was made.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './base-url.ts'

export const OBJECTS = '/index.php/apps/openregister/api/objects/planninq'

export const ADMIN_USER = process.env.NC_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin'

/**
 * An API context signed in as the admin.
 */
export async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: {
			username: ADMIN_USER,
			password: process.env.NC_ADMIN_PASS ?? process.env.ADMIN_PASSWORD ?? 'admin',
			send: 'always',
		},
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * Create an object and return its id.
 *
 * @param api The admin context.
 * @param schema The schema slug.
 * @param data The object data.
 */
export async function createObject(api: APIRequestContext, schema: string, data: object): Promise<string> {
	const res = await api.post(`${OBJECTS}/${schema}`, { data })
	expect(res.ok(), `create ${schema}: ${res.status()}`).toBe(true)
	const body = await res.json()
	return body.id ?? body['@self']?.id
}

/**
 * Delete objects, ignoring the ones already gone.
 *
 * @param api The admin context.
 * @param made Pairs of schema slug and id.
 */
export async function removeObjects(api: APIRequestContext, made: Array<[string, string]>): Promise<void> {
	for (const [schema, id] of [...made].reverse()) {
		await api.delete(`${OBJECTS}/${schema}/${id}`, { failOnStatusCode: false })
	}
}
