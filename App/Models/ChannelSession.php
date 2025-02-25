<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\Model;

/**
 * @property int $id
 * @property int $blog_id
 * @property int $created_by
 * @property string $social_network
 * @property string $name
 * @property string $remote_id
 * @property string $method
 * @property string $data
 * @property Collection $data_obj
 * @property string $proxy
 * @property string $created_at
 * @property string $updated_at
 */

class ChannelSession extends Model
{
	use BlogScope;

    public static array $writeableColumns = [
         'id',
         'blog_id',
         'created_by',
         'social_network',
         'name',
         'remote_id',
         'method',
         'data',
         'proxy',
         'created_at',
         'updated_at',
    ];

    public function getDataObjAttribute( Collection $channelSessionInfo ) : Collection
    {
        $arr = json_decode( $channelSessionInfo->data ?? '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }

}