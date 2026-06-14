<?php

/**
 * Planix Application
 *
 * Main application class for the Planix Nextcloud app.
 *
 * @category AppInfo
 * @package  OCA\Planix\AppInfo
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

namespace OCA\Planix\AppInfo;

use OCA\Planix\Listener\DeepLinkRegistrationListener;
use OCA\Planix\Listener\TaskActivityListener;
use OCA\OpenRegister\Event\DeepLinkRegistrationEvent;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectDeletedEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\Planix\Activity\Filter;
use OCA\Planix\Activity\Provider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Main application class for the Planix Nextcloud app.
 */
class Application extends App implements IBootstrap
{
    public const APP_ID = 'planix';

    /**
     * Constructor for the Application class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(appName: self::APP_ID);
    }//end __construct()

    /**
     * Register event listeners and services.
     *
     * @param IRegistrationContext $context The registration context
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function register(IRegistrationContext $context): void
    {
        // Register deep link patterns with OpenRegister's unified search provider.
        // Only fires when OpenRegister is installed and dispatches the event.
        $context->registerEventListener(
            event: DeepLinkRegistrationEvent::class,
            listener: DeepLinkRegistrationListener::class
        );

        // Publish task lifecycle events to the Nextcloud Activity stream.
        // Scoped inside the listener to the planix register's `task` schema.
        $context->registerEventListener(
            event: ObjectCreatedEvent::class,
            listener: TaskActivityListener::class
        );
        $context->registerEventListener(
            event: ObjectUpdatedEvent::class,
            listener: TaskActivityListener::class
        );
        $context->registerEventListener(
            event: ObjectDeletedEvent::class,
            listener: TaskActivityListener::class
        );

        // Register the Activity provider + filter (the "Planix" Activity filter).
        $context->registerActivityFilter(Filter::class);
        $context->registerActivityProvider(Provider::class);

    }//end register()

    /**
     * Boot the application.
     *
     * @param IBootContext $context The boot context
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function boot(IBootContext $context): void
    {
    }//end boot()
}//end class
