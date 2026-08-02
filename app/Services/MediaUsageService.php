<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\MediaAsset;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaUsageService
{
    /**
     * Rich-text fields that may directly reference a library URL.
     *
     * @var array<class-string<Model>, array{label: string, title: string, fields: list<string>, route: string}>
     */
    private const CONTENT = [
        News::class => [
            'label' => 'Новость',
            'title' => 'title',
            'fields' => ['body'],
            'route' => '/news/%d/edit',
        ],
        Page::class => [
            'label' => 'Страница',
            'title' => 'title',
            'fields' => ['body'],
            'route' => '/pages/%d/edit',
        ],
        Project::class => [
            'label' => 'Проект',
            'title' => 'title',
            'fields' => ['body'],
            'route' => '/projects/%d/edit',
        ],
        Instruction::class => [
            'label' => 'Инструкция',
            'title' => 'name',
            'fields' => ['body', 'sections'],
            'route' => '/instructions/%d/edit',
        ],
        Announcement::class => [
            'label' => 'Объявление',
            'title' => 'title',
            'fields' => ['body'],
            'route' => '/announcements/%d/edit',
        ],
        Alert::class => [
            'label' => 'Предупреждение',
            'title' => 'title',
            'fields' => ['body', 'instructions'],
            'route' => '/alerts/%d/edit',
        ],
    ];

    /**
     * @return list<array{type: string, id: int, title: string, edit_url: string}>
     */
    public function usages(Media $media): array
    {
        if ($media->model_type !== MediaAsset::class) {
            $owner = $media->model;

            return $owner instanceof Model ? [$this->presentOwner($owner)] : [];
        }

        $usages = [];

        Media::query()
            ->with('model')
            ->whereKeyNot($media->getKey())
            ->where('custom_properties->source_media_id', $media->getKey())
            ->get()
            ->each(function (Media $copy) use (&$usages): void {
                if ($copy->model instanceof Model) {
                    $this->addUnique($usages, $this->presentOwner($copy->model));
                }
            });

        // Без версии: media-library подписывает URL как `?v=updated_at`, а тело
        // материала хранит ту версию, что была на момент вставки. Сравнение по
        // полному URL означало бы, что любая правка самого файла (подпись,
        // alt, фокус) делает ссылку невидимой — и файл, стоящий в статье,
        // начинает считаться свободным, то есть его разрешено удалить.
        $url = Str::before($media->getUrl(), '?');
        if ($url === '') {
            return array_values($usages);
        }

        foreach (self::CONTENT as $modelClass => $config) {
            $modelClass::query()
                ->where(function ($query) use ($config, $url): void {
                    foreach ($config['fields'] as $field) {
                        $query->orWhere($field, 'like', "%{$url}%");
                    }
                })
                ->get()
                ->each(function (Model $owner) use (&$usages): void {
                    $this->addUnique($usages, $this->presentOwner($owner));
                });
        }

        return array_values($usages);
    }

    /**
     * @param  array<string, array{type: string, id: int, title: string, edit_url: string}>  $usages
     * @param  array{type: string, id: int, title: string, edit_url: string}  $usage
     */
    private function addUnique(array &$usages, array $usage): void
    {
        $usages[$usage['type'].':'.$usage['id']] = $usage;
    }

    /**
     * @return array{type: string, id: int, title: string, edit_url: string}
     */
    private function presentOwner(Model $owner): array
    {
        $config = self::CONTENT[$owner::class] ?? null;
        $id = (int) $owner->getKey();

        if ($config === null) {
            return [
                'type' => class_basename($owner),
                'id' => $id,
                'title' => class_basename($owner)." #{$id}",
                'edit_url' => '#',
            ];
        }

        return [
            'type' => $config['label'],
            'id' => $id,
            'title' => $this->title($owner, $config['title']),
            'edit_url' => sprintf($config['route'], $id),
        ];
    }

    private function title(Model $owner, string $attribute): string
    {
        if (method_exists($owner, 'getTranslation')) {
            $title = $owner->getTranslation($attribute, 'ru', false);
            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        $value = $owner->getAttribute($attribute);

        return is_string($value) && $value !== ''
            ? $value
            : class_basename($owner).' #'.$owner->getKey();
    }
}
