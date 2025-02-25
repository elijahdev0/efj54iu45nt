<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\Model;

/**
 * @property-read int $id
 * @property-read string $social_network
 * @property-read string $name
 * @property-read string $slug
 * @property-read int $blog_id
 * @property-read int $created_by
 * @property-read string $data
 * @property-read Collection $data_obj
 */
class App extends Model
{
	use BlogScope;

    public static array $writeableColumns = [
         'id',
         'social_network',
         'name',
         'slug',
         'blog_id',
         'created_by',
         'data',
    ];

    public function getDataObjAttribute( Collection $appInf ) : Collection
    {
        $arr = json_decode( $appInf->data ?? '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }

}
