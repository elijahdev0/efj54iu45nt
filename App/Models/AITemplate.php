<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\DB\QueryBuilder;

/**
 * @property-read string $id
 * @property-read string $title
 * @property-read string $provider
 * @property-read string $prompt
 * @property-read string $fallback_text
 * @property-read string $type
 * @property-read string $ai_model
 * @property-read string $config
 * @property-read int $created_by
 * @property-read int $blog_id
 * @property-read Collection $config_obj
 */
class AITemplate extends Model
{
	use BlogScope;

    public static ?string $tableName = 'ai_templates';

    public static array $writeableColumns = [
        'id',
        'title',
        'provider',
        'prompt',
        'fallback_text',
        'ai_model',
        'type',
        'config',
        'blog_id',
        'created_by',
    ];

    public function getConfigObjAttribute( Collection $template ) : Collection
    {
        $arr = json_decode( $template->config ?? '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }

}