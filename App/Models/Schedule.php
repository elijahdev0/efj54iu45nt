<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\BlogScope;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\DB\QueryBuilder;

/**
 * @property int             $id
 * @property int             $blog_id
 * @property int             $wp_post_id
 * @property int             $user_id
 * @property int             $channel_id
 * @property Channel         $channel
 * @property string          $group_id
 * @property string          $customization_data
 * @property-read Collection $customization_data_obj
 * @property string          $send_time
 * @property int             $visit_count
 * @property int             $planner_id
 * @property string          $status = {not_sent, sending, success, error}
 * @property string|null     $error_msg
 * @property string|null     $remote_post_id
 * @property string|null     $data
 * @property Collection      $data_obj
 */
class Schedule extends Model
{
	use BlogScope
	{
		booted as private blogBoot;
	}

    public static array $writeableColumns = [
        'id',
        'blog_id',
        'wp_post_id',
        'user_id',
        'channel_id',
        'status',
        'error_msg',
        'send_time',//doit edge var tablede. silinmelidi
        'remote_post_id',
        'visit_count',
        'planner_id',
        'data',
        'customization_data',
        'group_id',
    ];

    public static $relations = [
        'channel' => [ Channel::class, 'id', 'channel_id' ],
    ];

    /**
     * @param Schedule $scheduleInf
     *
     * @return Collection
     */
    public function getDataObjAttribute ( Collection $scheduleInf ): Collection
    {
        $arr = json_decode( $scheduleInf->data ?? '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }

    /**
     * @param Schedule $scheduleInf
     *
     * @return Collection
     */
    public function getCustomizationDataObjAttribute ( Collection $scheduleInf ): Collection
    {
        $arr = json_decode( $scheduleInf->customization_data ?? '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }

    public static function booted ()
    {
        self::blogBoot();

        self::addGlobalScope( 'my_schedules', function ( QueryBuilder $builder, $queryType )
        {
            if ( $queryType !== 'select' && $queryType !== 'update' && $queryType !== 'delete' )
                return;

            if ( !is_user_logged_in() )
                return;

            $builder->where( function ( $query )
            {
                $query->where( 'channel_id', 'in', Channel::withoutGlobalScope('soft_delete')->select( 'id' ) );
            } );
        } );
    }

}
