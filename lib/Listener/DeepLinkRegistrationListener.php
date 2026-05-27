<?php

/**
 * Planix DeepLinkRegistrationListener
 *
 * Registers Planix's deep link URL patterns with OpenRegister's search provider.
 *
 * @category Listener
 * @package  OCA\Planix\Listener
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\Planix\Listener;

use OCA\OpenRegister\Event\DeepLinkRegistrationEvent;
use OCA\Planix\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Registers Planix's deep link URL patterns with OpenRegister's search provider.
 *
 * When a user searches in Nextcloud's unified search, results for Planix schemas
 * will link directly to the relevant detail views in the app.
 *
 * URL templates use history-mode paths (no leading #) to match the Vue Router
 * configuration in src/router/index.js (mode: 'history', base: /apps/planix).
 * Tasks and projects have dedicated routes; columns, labels, and time entries
 * route to their parent project board since there are no standalone detail views.
 *
 * @implements IEventListener<Event>
 */
class DeepLinkRegistrationListener implements IEventListener
{
    /**
     * Handle the deep link registration event.
     *
     * @param Event $event The event to handle
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-3
     */
    public function handle(Event $event): void
    {
        if ($event instanceof DeepLinkRegistrationEvent === false) {
            return;
        }

        // Tasks link to the project board filtered by task UUID.
        $event->register(
            appId: Application::APP_ID,
            registerSlug: Application::APP_ID,
            schemaSlug: 'task',
            urlTemplate: '/apps/'.Application::APP_ID.'/projects/{project}?task={uuid}'
        );

        // Projects link directly to the project board.
        $event->register(
            appId: Application::APP_ID,
            registerSlug: Application::APP_ID,
            schemaSlug: 'project',
            urlTemplate: '/apps/'.Application::APP_ID.'/projects/{uuid}'
        );

        // Columns have no standalone route — route to the parent project board.
        $event->register(
            appId: Application::APP_ID,
            registerSlug: Application::APP_ID,
            schemaSlug: 'column',
            urlTemplate: '/apps/'.Application::APP_ID.'/projects/{project}'
        );

        // Labels have no standalone route — fall back to the project list.
        $event->register(
            appId: Application::APP_ID,
            registerSlug: Application::APP_ID,
            schemaSlug: 'label',
            urlTemplate: '/apps/'.Application::APP_ID.'/projects'
        );

        // Time entries have no standalone route — route to the task's project board.
        $event->register(
            appId: Application::APP_ID,
            registerSlug: Application::APP_ID,
            schemaSlug: 'timeEntry',
            urlTemplate: '/apps/'.Application::APP_ID.'/projects/{project}?task={task}'
        );

    }//end handle()
}//end class
