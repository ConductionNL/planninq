<?php

/**
 * Planninq PublicBoardController
 *
 * The page a project's read-only access link opens for people without an account.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\Kernel\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

/**
 * Serves the empty shell of the public board (projects-public-share design
 * decision 2). The page holds no data: its bundle reads the board from
 * OpenRegister's access-link endpoint, which checks the anchor, the end date
 * and the password, answers 404 for a revoked or expired link and drops the
 * properties a link holder may not read.
 *
 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
 */
class PublicBoardController extends Controller {

	/**
	 * The robots header that keeps a shared board out of search engines.
	 */
	public const ROBOTS = 'noindex, nofollow';

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(IRequest $request) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The public board page for one access-link anchor.
	 *
	 * The anchor is not looked up here, so this page answers the same for a
	 * live, a revoked and an unknown anchor and reveals nothing about which
	 * links exist; the bundle shows the outcome OpenRegister gives.
	 *
	 * @param string $anchor The access link's anchor from the URL.
	 *
	 * @return TemplateResponse The page without Nextcloud chrome, marked noindex.
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @spec openspec/changes/projects-public-share/tasks.md#task-2.1
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 60, period: 60)]
	public function show(string $anchor): TemplateResponse {
		$response = new TemplateResponse(
			appName: AppIdentity::APP_ID,
			templateName: 'public-board',
			params: [],
			renderAs: TemplateResponse::RENDER_AS_BASE
		);
		$response->addHeader(name: 'X-Robots-Tag', value: self::ROBOTS);

		return $response;
	}//end show()
}//end class
