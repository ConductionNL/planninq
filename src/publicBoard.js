/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public board bundle (projects-public-share task 2.1): small on purpose,
 * no router, no store and no app shell, because the visitor has no session.
 *
 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
 */

import { loadTranslations } from '@nextcloud/l10n'
import { createApp } from 'vue'
import PublicBoard from './views/PublicBoard.vue'

loadTranslations('planninq')
	.catch(() => {
		// Without the catalogue the board shows in English; it still works.
	})
	.finally(() => {
		createApp(PublicBoard).mount('#planninq-public-board')
	})
