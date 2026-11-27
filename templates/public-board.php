<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public board (projects-public-share task 2.1): no data in the page, the
 * bundle reads the board through the access link. noindex keeps it out of
 * search engines; the X-Robots-Tag header says the same.
 */

use OCP\Util;

$appId = OCA\Planninq\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-public-board');
Util::addHeader('meta', ['name' => 'robots', 'content' => 'noindex, nofollow']);
?>
<div id="planninq-public-board"></div>
