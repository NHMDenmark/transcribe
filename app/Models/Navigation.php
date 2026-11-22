<?php

namespace App\Models;

use App\Services\SiteNavigation;
use Illuminate\Database\Eloquent\Model;

/**
 * One node of the menu tree: a group (a menu heading), a link, or a "contents" item, the place
 * where the contents assigned to it are shown. See App\Services\SiteNavigation for how it is read.
 */
class Navigation extends Model
{
    protected $table = 'navigation';

    protected $casts = [
        'active' => 'boolean',
        'show_in_menu' => 'boolean',
        'members_only' => 'boolean',
    ];

    public const TYPES = ['group', 'contents', 'link'];

    /** How the contents of a contents item are ordered: by hand, or automatically. */
    public const SORT_MODES = ['manual', 'newest', 'oldest', 'title'];

    protected static function booted(): void
    {
        // the tree is cached without expiry: every change must drop it
        static::saved(fn() => app(SiteNavigation::class)->flush());
        static::deleted(fn() => app(SiteNavigation::class)->flush());
    }

    public function contents()
    {
        return $this->hasMany(ContentItem::class, 'navigation_id')->orderBy('position')->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }
}
