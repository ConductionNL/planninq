<?php

/**
 * Planix Metrics Controller
 *
 * Controller for exposing Prometheus metrics in text exposition format.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Controller for exposing Prometheus metrics.
 *
 * Admin-only: no @NoAdminRequired — NC middleware enforces admin access.
 */
class MetricsController extends Controller
{
    /**
     * Constructor for the MetricsController.
     *
     * @param IRequest $request The request object
     * @param IConfig  $config  The config service
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private readonly IConfig $config,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Expose Prometheus metrics.
     *
     * @NoCSRFRequired
     *
     * @spec exclude observability endpoint per ADR-006 (metrics plumbing, no business capability)
     *
     * @return TextPlainResponse Plain text response with Prometheus metrics.
     */
    public function index(): TextPlainResponse
    {
        $lines = [];

        $appVersion = $this->config->getAppValue(Application::APP_ID, 'installed_version', '0.0.0');
        $phpVersion = PHP_VERSION;
        $ncVersion  = $this->config->getSystemValueString('version', '0.0.0');

        // Info gauge.
        $lines[] = '# HELP planix_info Application information';
        $lines[] = '# TYPE planix_info gauge';
        $labels  = sprintf(
            'version="%s",php_version="%s",nextcloud_version="%s"',
            $appVersion,
            $phpVersion,
            $ncVersion
        );
        $lines[] = 'planix_info{'.$labels.'} 1';

        // Up gauge.
        $lines[] = '# HELP planix_up Whether the application is up';
        $lines[] = '# TYPE planix_up gauge';
        $lines[] = 'planix_up 1';

        $body     = implode("\n", $lines)."\n";
        $response = new TextPlainResponse($body);
        $response->addHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        return $response;
    }//end index()
}//end class
