<?php

/**
 * Planix Time Entry Controller
 *
 * Controller that enforces ownership before delegating time entry
 * deletes to OpenRegister — prevents IDOR on client-side-only guard.
 *
 * @category Controller
 * @package  OCA\Planix\Controller
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Controller;

use OCA\Planix\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller that validates ownership before deleting a time entry.
 *
 * The frontend ObjectStore calls OpenRegister directly for most CRUD, but
 * DELETE is routed through this controller so the server can verify that the
 * requesting user is the owner of the time entry before the delete is executed.
 */
class TimeEntryController extends Controller
{
    /**
     * Constructor for TimeEntryController.
     *
     * @param IRequest           $request     The request object
     * @param IUserSession       $userSession The user session
     * @param ContainerInterface $container   The DI container
     * @param LoggerInterface    $logger      The logger
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private IUserSession $userSession,
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);
    }//end __construct()

    /**
     * Delete a time entry after verifying that the caller is its owner.
     *
     * Returns 401 when called without a session, 403 when the caller does not
     * own the entry, 404 when no entry with the given ID exists, and 200 on
     * success.
     *
     * @param string $id The UUID of the time entry to delete
     *
     * @return JSONResponse
     *
     * @NoAdminRequired
     */
    public function destroy(string $id): JSONResponse
    {
        $currentUser = $this->userSession->getUser();
        if ($currentUser === null) {
            return new JSONResponse(['error' => 'Unauthenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $currentUid = $currentUser->getUID();

        try {
            $objectService = $this->container->get('OCA\OpenRegister\Service\ObjectService');

            // Fetch the entry to verify ownership before deleting.
            $entry = $objectService->getObject(
                register: 'planix',
                schema: 'timeEntry',
                id: $id
            );

            if ($entry === null) {
                return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
            }

            // Normalise: service may return an entity object or an array.
            $entryUser = null;
            if (is_array($entry) === true) {
                $entryUser = ($entry['user'] ?? null);
            } else if (is_object($entry) === true && method_exists($entry, 'getObject') === true) {
                $entryUser = ($entry->getObject()['user'] ?? null);
            } else if (is_object($entry) === true) {
                $entryUser = ($entry->user ?? null);
            }

            if ($entryUser !== $currentUid) {
                return new JSONResponse(
                    ['error' => 'You may only delete your own time entries.'],
                    Http::STATUS_FORBIDDEN
                );
            }

            $objectService->deleteObject(
                register: 'planix',
                schema: 'timeEntry',
                id: $id
            );

            return new JSONResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Planix: TimeEntry delete failed',
                ['id' => $id, 'exception' => $e->getMessage()]
            );
            return new JSONResponse(
                ['error' => 'Failed to delete time entry'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }//end try
    }//end destroy()
}//end class
