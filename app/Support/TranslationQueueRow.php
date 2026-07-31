<?php

namespace App\Support;

/**
 * Typed boundary for one row returned by the cross-table translation queue.
 */
final readonly class TranslationQueueRow
{
    public function __construct(
        public int $id,
        public string $contentType,
        public ?string $titleData,
        public string $status,
    ) {}

    public static function fromRow(object $row): self
    {
        /** @var array<string, mixed> $values */
        $values = get_object_vars($row);

        return new self(
            id: (int) ($values['id'] ?? 0),
            contentType: (string) ($values['content_type'] ?? ''),
            titleData: isset($values['title_data']) ? (string) $values['title_data'] : null,
            status: (string) ($values['status'] ?? ''),
        );
    }
}
