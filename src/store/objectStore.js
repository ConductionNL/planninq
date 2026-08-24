/**
 * Planninq object store instance.
 *
 * Created via `createObjectStore` (@conduction/nextcloud-vue >= beta.212) so
 * the liveUpdatesPlugin is installed default-on: the store exposes
 * `subscribe(type, id?)` / `unsubscribe(handle)` backed by the
 * notify_push service with a visibility-gated polling fallback. The plugin
 * is inert until the first `subscribe()` call, so plain CRUD behaviour is
 * identical to the package's shared `useObjectStore` this module replaces.
 *
 * All Planninq stores/views must import `useObjectStore` from this module (not
 * from '@conduction/nextcloud-vue') so CRUD state and live-update refetches
 * land in the same store instance.
 *
 * SPDX-FileCopyrightText: 2026 Planninq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/realtime-updates.md
 */
import { createObjectStore } from '@conduction/nextcloud-vue'

export const useObjectStore = createObjectStore('planninq-objects')
