<?php

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off data fix: addresses longer than the site can hold (Slug::MAX_LENGTH)
 * broke its production build and its cache tags. They are cut between words;
 * the full address stays as a redirect (RemembersOldSlugs), so shared links
 * keep working. The edit date is left alone — the text didn't change.
 */
return new class extends Migration
{
    /**
     * @var list<class-string<Model>>
     */
    private const MATERIALS = [
        Alert::class,
        Announcement::class,
        Instruction::class,
        News::class,
        Page::class,
        Project::class,
    ];

    public function up(): void
    {
        foreach (self::MATERIALS as $class) {
            $overlong = $class::withTrashed()
                ->whereNotNull('slug')
                ->pluck('slug', 'id')
                ->filter(fn (mixed $slug): bool => mb_strlen((string) $slug) > Slug::MAX_LENGTH)
                ->keys();

            foreach ($class::withTrashed()->whereKey($overlong)->get() as $material) {
                $id = $material->getKey();
                $material->setAttribute('slug', Slug::unique(
                    (string) $material->getAttribute('slug'),
                    fn (string $slug): bool => $class::withTrashed()->where('slug', $slug)->whereKeyNot($id)->exists(),
                ));
                $material->timestamps = false;
                $material->save();
            }
        }
    }

    public function down(): void
    {
        // Content fix only.
    }
};
