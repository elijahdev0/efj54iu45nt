<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\DB\QueryBuilder;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read int $blog_id
 * @property-read int $created_by
 * @property-read string $color
 */
class ChannelLabel extends Model
{
	use BlogScope {
		booted as private blogBoot;
	}

    public static array $writeableColumns = [
        'id',
        'name',
        'blog_id',
        'created_by',
        'color',
    ];

    public static function booted()
    {
        self::blogBoot();
        self::addGlobalScope('my_labels', function ( QueryBuilder $builder, $queryType )
        {
            if( $queryType !== 'select' && $queryType !== 'delete' && $queryType !== 'update' )
                return;

            if( ! is_user_logged_in() )
                return;

	        $user = wp_get_current_user();

            $builder->where( function ( $query ) use ( $queryType, $user )
            {
	            $query->where('created_by', $user->ID);

				if( $queryType == 'select' )
				{
					$subQuery = ChannelLabelsData::select('label_id')->where('channel_id', 'in', Channel::select('id'));

					$query->orWhere( 'id', 'in', $subQuery );
				}
            });
        });
    }
}