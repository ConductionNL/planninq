<?php

use OCP\Util;

$appId = OCA\Planninq\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-main');

// Host element for the Vue 3 SPA. Deliberately NOT `id="content"`: that id
// duplicates Nextcloud's own layout.user.php wrapper. Vue 2's $mount()
// REPLACED the matched element, so the duplication never showed; Vue 3's
// mount() renders INSIDE it, which would nest the whole app inside core's
// #content and break the NcContent layout. See src/main.js.
?>
<div id="planninq-app"></div>
