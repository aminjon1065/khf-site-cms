<?php

namespace App\Services;

use App\Models\EditorialRevision;
use App\Models\User;
use App\Support\EditorialContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EditorialRevisionService
{
    public function __construct(private readonly EditorialContent $content) {}

    public function capture(
        Model $model,
        ?User $user,
        string $source = 'manual',
        ?string $draftKey = null,
    ): EditorialRevision {
        $type = $this->content->typeFor($model);

        if ($type === null) {
            throw new InvalidArgumentException('Unsupported editorial model.');
        }

        return EditorialRevision::query()->create([
            'content_type' => $type,
            'content_id' => $model->getKey(),
            'user_id' => $user?->getKey(),
            'draft_key' => $draftKey ?? (string) Str::uuid(),
            'data' => $this->content->snapshot($model),
            'base_version' => $this->content->version($model),
            'source' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function autosave(
        string $type,
        ?int $contentId,
        User $user,
        string $draftKey,
        array $data,
        ?string $baseVersion,
    ): EditorialRevision {
        return EditorialRevision::query()->create([
            'content_type' => $type,
            'content_id' => $contentId,
            'user_id' => $user->getKey(),
            'draft_key' => $draftKey,
            'data' => $data,
            'base_version' => $baseVersion,
            'source' => 'autosave',
        ]);
    }
}
