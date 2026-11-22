<?php

namespace App\Models;

use App\Services\SiteFooter;
use Illuminate\Database\Eloquent\Model;

/**
 * One block of the page footer: a free text, or a list of links to menu items. See App\Services\SiteFooter.
 */
class FooterBlock extends Model
{
    protected $table = 'footer_blocks';

    public const TYPES = ['text', 'links'];
    public const WIDTHS = [3, 4, 6, 12];

    protected $casts = [
        'active' => 'boolean',
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn() => app(SiteFooter::class)->flush());
        static::deleted(fn() => app(SiteFooter::class)->flush());
    }
}
