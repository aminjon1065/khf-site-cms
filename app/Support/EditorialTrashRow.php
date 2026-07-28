<?php

namespace App\Support;

/**
 * Одна строка union-запроса по корзинам всех редакционных типов.
 *
 * Запрос собирается через `DB::query()->fromSub()` и возвращает безликие
 * `stdClass`, в которых драйвер отдаёт числа строками (SQLite) или числами
 * (MySQL). Приводим их к нормальным типам ровно один раз, на границе, — дальше
 * с данными работает типизированный код, а не набор непроверяемых обращений
 * к свойствам произвольного объекта.
 */
final readonly class EditorialTrashRow
{
    public function __construct(
        public int $id,
        public string $contentType,
        public ?string $titleData,
        public string $status,
        public ?int $authorId,
        public string $deletedAt,
    ) {}

    public static function fromRow(object $row): self
    {
        /** @var array<string, mixed> $values */
        $values = get_object_vars($row);

        $authorId = $values['author_id'] ?? null;

        return new self(
            id: (int) ($values['id'] ?? 0),
            contentType: (string) ($values['content_type'] ?? ''),
            titleData: isset($values['title_data']) ? (string) $values['title_data'] : null,
            status: (string) ($values['status'] ?? ''),
            authorId: $authorId === null ? null : (int) $authorId,
            deletedAt: (string) ($values['deleted_at'] ?? ''),
        );
    }
}
