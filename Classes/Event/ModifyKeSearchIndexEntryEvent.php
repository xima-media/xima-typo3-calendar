<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

/**
 * Extension point for a single ke_search index entry.
 *
 * Dispatched by Indexer\EventIndexer once per event, immediately before the entry is stored, and
 * re-read afterwards. `$params` defaults to the arguments of the extension's own
 * EventDetail plugin; a project that renders the detail view differently rewrites them here.
 * `$targetPid` starts at the page configured in the indexer configuration.
 */
class ModifyKeSearchIndexEntryEvent
{
    /**
     * @param array<string, mixed> $eventRow
     * @param list<array<string, mixed>> $appointmentRows
     * @param array<string, mixed> $additionalFields
     */
    public function __construct(
        private string $title,
        private string $content,
        private string $abstract,
        private string $tags,
        private string $params,
        private int $targetPid,
        private array $additionalFields,
        private readonly array $eventRow,
        private readonly array $appointmentRows,
        private readonly int $languageId,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getAbstract(): string
    {
        return $this->abstract;
    }

    public function setAbstract(string $abstract): void
    {
        $this->abstract = $abstract;
    }

    public function getTags(): string
    {
        return $this->tags;
    }

    public function setTags(string $tags): void
    {
        $this->tags = $tags;
    }

    public function getParams(): string
    {
        return $this->params;
    }

    public function setParams(string $params): void
    {
        $this->params = $params;
    }

    public function getTargetPid(): int
    {
        return $this->targetPid;
    }

    public function setTargetPid(int $targetPid): void
    {
        $this->targetPid = $targetPid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalFields(): array
    {
        return $this->additionalFields;
    }

    /**
     * @param array<string, mixed> $additionalFields
     */
    public function setAdditionalFields(array $additionalFields): void
    {
        $this->additionalFields = $additionalFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventRow(): array
    {
        return $this->eventRow;
    }

    /**
     * The appointments the indexer query admitted, including those already over.
     *
     * @return list<array<string, mixed>>
     */
    public function getAppointmentRows(): array
    {
        return $this->appointmentRows;
    }

    public function getLanguageId(): int
    {
        return $this->languageId;
    }
}
