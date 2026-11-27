<?php

/**
 * Unit tests for PublicBoardController.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-25-projects-public-share/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\Controller\PublicBoardController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The public board page: an empty, chrome-less shell marked noindex.
 *
 * @covers \OCA\Planninq\Controller\PublicBoardController
 */
class PublicBoardControllerTest extends TestCase {

	/**
	 * The page for an anchor.
	 *
	 * @return TemplateResponse
	 */
	private function page(): TemplateResponse {
		$controller = new PublicBoardController(request: $this->createMock(originalClassName: IRequest::class));

		return $controller->show(anchor: 'Ab12Cd34');
	}//end page()

	/**
	 * The page renders the public-board template without the signed-in chrome.
	 *
	 * @return void
	 */
	public function testShowRendersThePublicBoardTemplateWithoutChrome(): void {
		$response = $this->page();

		$this->assertSame(expected: 'public-board', actual: $response->getTemplateName());
		$this->assertSame(expected: TemplateResponse::RENDER_AS_BASE, actual: $response->getRenderAs());
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
	}//end testShowRendersThePublicBoardTemplateWithoutChrome()

	/**
	 * The page tells search engines not to index or follow it.
	 *
	 * @return void
	 */
	public function testShowMarksThePageNoindex(): void {
		// The getHeaders() call merges the server's security headers and needs a booted
		// Nextcloud; the headers this controller set are read from the response itself.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($this->page());

		$this->assertSame(expected: 'noindex, nofollow', actual: ($headers['X-Robots-Tag'] ?? null));
	}//end testShowMarksThePageNoindex()

	/**
	 * The page carries no data: the anchor is not looked up or echoed, so a
	 * live and an unknown anchor answer the same.
	 *
	 * @return void
	 */
	public function testShowPassesNoDataToTheTemplate(): void {
		$this->assertSame(expected: [], actual: $this->page()->getParams());
	}//end testShowPassesNoDataToTheTemplate()

	/**
	 * The route is public, rate limited and needs no CSRF token (a link is opened from anywhere).
	 *
	 * @return void
	 */
	public function testShowIsAPublicRateLimitedPage(): void {
		$method = new ReflectionMethod(PublicBoardController::class, 'show');
		$names  = array_map(static fn ($attribute): string => $attribute->getName(), $method->getAttributes());

		$this->assertContains(needle: PublicPage::class, haystack: $names);
		$this->assertContains(needle: NoCSRFRequired::class, haystack: $names);
		$this->assertContains(needle: AnonRateLimit::class, haystack: $names);
	}//end testShowIsAPublicRateLimitedPage()
}//end class
