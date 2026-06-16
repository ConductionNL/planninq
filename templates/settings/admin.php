<?php

use OCP\Util;

$appId = OCA\Planix\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-settings');
?>
<div id="planix-settings"></div>
