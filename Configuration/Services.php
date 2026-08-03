<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Widgets\CalendarWidget;
use Xima\XimaTypo3Calendar\Widgets\Provider\CanceledAppointmentsDataProvider;
use Xima\XimaTypo3Calendar\Widgets\Provider\ModuleButtonProvider;
use Xima\XimaTypo3Calendar\Widgets\Provider\ReadyToPublishEventsDataProvider;
use Xima\XimaTypo3Calendar\Widgets\Provider\SoonNeededRequirementsDataProvider;
use Xima\XimaTypo3Calendar\Widgets\Provider\UpcomingAppointmentsDataProvider;

return static function (ContainerConfigurator $containerConfigurator): void {
    $lll = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:';

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->load('Xima\\XimaTypo3Calendar\\', '../Classes/*')
        ->exclude('../Classes/Domain/Model/*');

    $services->set(ReadyToPublishEventsDataProvider::class)
        ->arg('$limit', 8);
    $services->set(SoonNeededRequirementsDataProvider::class)
        ->arg('$daysInPreview', 2)
        ->arg('$limit', 10);

    $services->set('xima_typo3_calendar_widget.button.calendar_events_event', ModuleButtonProvider::class)
        ->arg('$routeIdentifier', 'calendar_events')
        ->arg('$table', 'tx_ximatypo3calendar_domain_model_event')
        ->arg('$title', $lll . 'widget.button.calendar_events');
    $services->set('xima_typo3_calendar_widget.button.calendar_events_entry', ModuleButtonProvider::class)
        ->arg('$routeIdentifier', 'calendar_events')
        ->arg('$table', 'tx_ximatypo3calendar_domain_model_entry')
        ->arg('$title', $lll . 'widget.button.calendar_appointments');

    // The requirements management feature flag defaults to disabled and is only
    // available once the extension has been configured.
    try {
        $requirementsManagementEnabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('xima_typo3_calendar', 'features/requirementsManagement');
    } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
        $requirementsManagementEnabled = false;
    }

    $services->set('dashboard.widget.xima_readytopublishevents', CalendarWidget::class)
        ->arg('$buttonProvider', service('xima_typo3_calendar_widget.button.calendar_events_event'))
        ->arg('$dataProvider', service(ReadyToPublishEventsDataProvider::class))
        ->arg('$options', ['refreshAvailable' => true])
        ->tag('dashboard.widget', [
            'identifier' => 'xima_readytopublishevents',
            'groupNames' => 'xima_typo3_calendar',
            'title' => $lll . 'widget.ready_to_publish_events.title',
            'description' => $lll . 'widget.ready_to_publish_events.description',
            'iconIdentifier' => 'content-widget-list',
            'height' => 'large',
            'width' => 'medium',
        ]);

    $services->set('dashboard.widget.xima_upcomingappointments', CalendarWidget::class)
        ->arg('$buttonProvider', service('xima_typo3_calendar_widget.button.calendar_events_entry'))
        ->arg('$dataProvider', service(UpcomingAppointmentsDataProvider::class))
        ->arg('$options', ['refreshAvailable' => true])
        ->tag('dashboard.widget', [
            'identifier' => 'xima_upcomingappointments',
            'groupNames' => 'xima_typo3_calendar',
            'title' => $lll . 'widget.upcoming_appointments.title',
            'description' => $lll . 'widget.upcoming_appointments.description',
            'iconIdentifier' => 'content-widget-list',
            'height' => 'large',
            'width' => 'medium',
        ]);

    $services->set('dashboard.widget.xima_canceledappointments', CalendarWidget::class)
        ->arg('$buttonProvider', service('xima_typo3_calendar_widget.button.calendar_events_event'))
        ->arg('$dataProvider', service(CanceledAppointmentsDataProvider::class))
        ->arg('$options', ['refreshAvailable' => true])
        ->tag('dashboard.widget', [
            'identifier' => 'xima_canceledappointments',
            'groupNames' => 'xima_typo3_calendar',
            'title' => $lll . 'widget.canceled_appointments.title',
            'description' => $lll . 'widget.canceled_appointments.description',
            'iconIdentifier' => 'content-widget-list',
            'height' => 'medium',
            'width' => 'large',
        ]);

    // Gated on features/requirementsManagement.
    if ($requirementsManagementEnabled) {
        $services->set('dashboard.widget.xima_soonneededrequirements', CalendarWidget::class)
            ->arg('$buttonProvider', service('xima_typo3_calendar_widget.button.calendar_events_entry'))
            ->arg('$dataProvider', service(SoonNeededRequirementsDataProvider::class))
            ->arg('$options', ['refreshAvailable' => true])
            ->tag('dashboard.widget', [
                'identifier' => 'xima_soonneededrequirements',
                'groupNames' => 'xima_typo3_calendar',
                'title' => $lll . 'widget.soon_needed_requirements.title',
                'description' => $lll . 'widget.soon_needed_requirements.description',
                'iconIdentifier' => 'content-widget-list',
                'height' => 'medium',
                'width' => 'large',
            ]);
    }
};
