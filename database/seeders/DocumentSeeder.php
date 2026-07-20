<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\DocType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, int> $users */
        $users = User::query()->pluck('id', 'email')->all();

        $docs = [
            [
                'ru' => 'Закон РТ «О защите населения и территорий от чрезвычайных ситуаций»',
                'tg' => 'Қонуни ҶТ «Дар бораи ҳифзи аҳолӣ ва ҳудудҳо аз ҳолатҳои фавқулодда»',
                'type' => DocType::Law, 'number' => '№ 1432', 'date' => '2026-03-02', 'section' => 'Законодательство',
                'status' => ContentStatus::Published, 'author' => 'f.nazarov@khf.tj',
            ],
            [
                'ru' => 'Национальная стратегия снижения риска бедствий на 2026–2030 годы',
                'tg' => 'Стратегияи миллии коҳиши хатари офатҳо барои солҳои 2026–2030',
                'type' => DocType::Resolution, 'number' => '№ 218', 'date' => '2026-06-11', 'section' => 'Стратегии',
                'status' => ContentStatus::Published, 'author' => 'f.nazarov@khf.tj',
            ],
            [
                'ru' => 'Отчёт о деятельности Комитета за первое полугодие 2026 года',
                'tg' => 'Ҳисобот дар бораи фаъолияти Кумита барои нимсолаи аввали соли 2026',
                'type' => DocType::Report, 'number' => null, 'date' => '2026-07-17', 'section' => 'Отчёты',
                'status' => ContentStatus::Review, 'author' => 'z.nazarova@khf.tj',
            ],
            [
                'ru' => 'План эвакуационных мероприятий по Хатлонской области',
                'tg' => 'Нақшаи чорабиниҳои эвакуатсионӣ оид ба вилояти Хатлон',
                'type' => DocType::Plan, 'number' => '№ 87-ДСП', 'date' => '2026-07-05', 'section' => 'Планы',
                'status' => ContentStatus::Published, 'author' => 'sh.karimov@khf.tj',
            ],
            [
                'ru' => 'Приказ о проведении месячника гражданской обороны',
                'tg' => 'Фармон дар бораи гузаронидани моҳномаи мудофиаи граждани',
                'type' => DocType::Order, 'number' => '№ 156', 'date' => '2026-07-15', 'section' => 'Приказы',
                'status' => ContentStatus::Draft, 'author' => 'f.nazarov@khf.tj', 'noFile' => true,
            ],
            [
                'ru' => 'Форма заявки на обучение по программе ГО для организаций',
                'tg' => 'Шакли ариза барои омӯзиш аз рӯи барномаи МГ',
                'type' => DocType::Form, 'number' => null, 'date' => '2026-07-01', 'section' => 'Формы',
                'status' => ContentStatus::Published, 'author' => 'a.usmonov@khf.tj',
            ],
        ];

        foreach ($docs as $d) {
            $published = $d['status'] === ContentStatus::Published;

            $attributes = [
                'name' => ['ru' => $d['ru'], 'tg' => $d['tg'], 'en' => ''],
                'doc_type' => $d['type'],
                'number' => $d['number'],
                'doc_date' => Carbon::parse($d['date']),
                'section' => $d['section'],
                'status' => $d['status'],
                'published_at' => $published ? Carbon::parse($d['date']) : null,
                'author_id' => $users[$d['author']] ?? null,
            ];

            $document = Document::query()->where('name->ru', $d['ru'])->first();

            if ($document) {
                $document->update($attributes);
            } else {
                $document = Document::create($attributes);
            }

            if ($published && empty($d['noFile'])) {
                $this->attachSampleFiles($document, $d['type'] === DocType::Resolution);
            }
        }
    }

    /**
     * Attach a small placeholder PDF per language so the public library has
     * working downloads. Idempotent: skips a collection that already has a file.
     */
    private function attachSampleFiles(Document $document, bool $withEn): void
    {
        $locales = $withEn ? ['tg', 'ru', 'en'] : ['tg', 'ru'];

        foreach ($locales as $locale) {
            $collection = "file_{$locale}";

            if ($document->hasMedia($collection)) {
                continue;
            }

            $document->addMediaFromString($this->samplePdf())
                ->usingFileName("document-{$locale}.pdf")
                ->toMediaCollection($collection);
        }
    }

    /**
     * A minimal, valid, single blank A4 page PDF (placeholder for seed data).
     */
    private function samplePdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n"
            .'%%EOF';
    }
}
