<?php

namespace App\Models;

use App\Concerns\TracksTranslationCompleteness;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $type
 * @property array<string, string> $title
 * @property bool $enabled
 * @property int $sort
 * @property array<string, mixed>|null $config
 */
class HomeBlock extends Model
{
    use HasTranslations, LogsActivity, TracksTranslationCompleteness;

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * Block types the public site doesn't render: not offered to editors, so
     * no switch in the CMS pretends to change the home page.
     *
     * @var list<string>
     */
    public const HIDDEN_TYPES = ['emergency_contacts'];

    /**
     * How many items each list block can show on the public home page — its
     * layout caps them (see khf-site-front components/public/home). The CMS
     * never promises more.
     *
     * @var array<string, int>
     */
    public const MAX_ITEMS = [
        'active_alerts' => 6,
        'latest_news' => 5,
        'instructions' => 3,
        'documents' => 6,
        'announcements' => 6,
        'projects' => 4,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'title', 'enabled', 'sort', 'config'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'title', 'enabled', 'sort', 'config'])
            ->logOnlyDirty()
            ->useLogName('home_blocks');
    }
}
