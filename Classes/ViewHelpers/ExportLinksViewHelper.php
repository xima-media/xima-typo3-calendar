<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Service\CalendarExportService;

/**
 * Provides the calendar export links of an event or appointment to its children.
 *
 * Markup, labels and which of the targets to offer stay with the template — this only
 * answers where they point.
 *
 * ```
 * <calendar:exportLinks appointment="{appointment}" as="exportLinks">
 *     <a href="{exportLinks.ics}">…</a>
 * </calendar:exportLinks>
 * ```
 */
final class ExportLinksViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('event', Event::class, 'The event to export; ignored when an appointment is given');
        $this->registerArgument('appointment', EventAppointment::class, 'The single appointment to export');
        $this->registerArgument('as', 'string', 'Name of the variable the links are assigned to', false, 'exportLinks');
    }

    public function render(): string
    {
        $service = GeneralUtility::makeInstance(CalendarExportService::class);
        $appointment = $this->arguments['appointment'];
        $event = $this->arguments['event'];

        if ($appointment instanceof EventAppointment) {
            $links = $service->linksForAppointment($appointment);
        } elseif ($event instanceof Event) {
            $links = $service->linksForEvent($event);
        } else {
            throw new \InvalidArgumentException(
                'Either "event" or "appointment" must be given to the exportLinks ViewHelper.',
                1787900002
            );
        }

        $variableName = (string)$this->arguments['as'];
        $this->templateVariableContainer->add($variableName, $links);
        $output = (string)$this->renderChildren();
        $this->templateVariableContainer->remove($variableName);

        return $output;
    }
}
