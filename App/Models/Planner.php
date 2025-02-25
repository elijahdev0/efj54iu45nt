<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\DB\QueryBuilder;

/**
 * @property-read int        $id
 * @property-read string     $title
 * @property-read string     $post_type
 * @property-read string     $status
 * @property-read string     $channels
 * @property-read string     $customization_data
 * @property-read Collection $customization_data_obj
 * @property-read string     $share_type
 * @property-read string     $sort_by
 * @property-read string     $start_at
 * @property-read string     $next_execute_at
 * @property-read string     $selected_posts
 * @property-read string     $shared_posts
 * @property-read int        $repeating
 * @property-read int        $created_by
 * @property-read string     $created_at
 * @property-read int        $blog_id
 * @property-read string     $post_filters_date_range_from
 * @property-read string     $post_filters_date_range_to
 * @property-read string     $post_filters_term
 * @property-read int        $post_filters_skip_oos_products
 * @property-read int        $schedule_interval
 * @property-read string     $sleep_time_start
 * @property-read string     $sleep_time_end
 * @property-read string     $weekly
 * @property-read Collection $data_obj
 */
class Planner extends Model
{
	use BlogScope
	{
		booted as private blogBoot;
	}

    public static array $writeableColumns = [
        'id',
        'title',
        'post_type',
        'status',
        'channels',
        'customization_data',
        'share_type',
        'sort_by',
        'start_at',
        'next_execute_at',
        'selected_posts',
        'shared_posts',
        'repeating',
        'created_by',
        'created_at',
        'blog_id',
        'post_filters_date_range_from',
        'post_filters_date_range_to',
        'post_filters_term',
        'post_filters_skip_oos_products',
        'schedule_interval',
        'sleep_time_start',
        'sleep_time_end',
        'weekly',
    ];

    public static function booted ()
    {
		self::blogBoot();
		
        self::addGlobalScope( 'my_planners', function ( QueryBuilder $builder, $queryType )
        {
            if ( $queryType !== 'select' && $queryType !== 'delete' && $queryType !== 'update' )
                return;

            if ( !is_user_logged_in() )
                return;

            $builder->where( function ( $query ) use ( $queryType )
            {
                $user = wp_get_current_user();
                $query->where( 'created_by', $user->ID );
            } );
        } );
    }

    /**
     * @param Planner $planner
     *
     * @return Collection
     */
    public function getCustomizationDataObjAttribute ( Collection $planner ): Collection
    {
        $arr = json_decode( $planner->customization_data ?? '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }
}