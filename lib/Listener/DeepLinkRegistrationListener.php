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
 */

declare(strict_types=1);

namespace OCA\Planix\Listener;

use OCA\OpenRegister\Event\DeepLinkRegistrationEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Registers Planix's deep link URL patterns with OpenRegister's search provider.
 *
 * When a user searches in Nextcloud's unified search, results for Planix schemas
 * will link directly to the relevant detail views in the app.
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
     */
    public function handle(Event $event): void
    {
        if ($event instanceof DeepLinkRegistrationEvent === false) {
            return;
        }

        $event->register(
            appId: 'planix',
            registerSlug: 'planix',
            schemaSlug: 'task',
            urlTemplate: '/apps/planix/#/tasks/{uuid}'
        );

        $event->register(
            appId: 'planix',
            registerSlug: 'planix',
            schemaSlug: 'project',
            urlTemplate: '/apps/planix/#/projects/{uuid}'
        );

        $event->register(
            appId: 'planix',
            registerSlug: 'planix',
            schemaSlug: 'column',
            urlTemplate: '/apps/planix/#/columns/{uuid}'
        );

        $event->register(
            appId: 'planix',
            registerSlug: 'planix',
            schemaSlug: 'label',
            urlTemplate: '/apps/planix/#/labels/{uuid}'
        );

        $event->register(
            appId: 'planix',
            registerSlug: 'planix',
            schemaSlug: 'timeEntry',
            urlTemplate: '/apps/planix/#/time-entries/{uuid}'
        );

    }//end handle()
}//end class
