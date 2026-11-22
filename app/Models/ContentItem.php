<?php

namespace App\Models;

use App\Services\SiteNavigation;
use Illuminate\Database\Eloquent\Model;

/**
 * A piece of content (a text), apart from the menu. It is shown wherever the navigation item it is
 * assigned to is. Title and body are dialogs per language (content_title_{id}, content_body_{id}).
 */
class ContentItem extends Model
{
    protected $table = 'content_items';

    protected $casts = [
        'members_only' => 'boolean',
        'publish_at' => 'datetime',
        'archive_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn() => app(SiteNavigation::class)->flush());
        static::deleted(fn() => app(SiteNavigation::class)->flush());
    }

    public function navigation()
    {
        return $this->belongsTo(Navigation::class, 'navigation_id');
    }
}
