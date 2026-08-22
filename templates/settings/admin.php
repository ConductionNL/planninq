<?php

use OCP\Util;

$appId = OCA\Planninq\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-settings');
?>
<div id="planninq-settings"></div>
