<?php

namespace FSPoster\App\Models;

use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\DB\QueryBuilder;

/**
 * @property int            $id
 * @property int            $channel_session_id
 * @property string         $name
 * @property string         $channel_type
 * @property string         $remote_id
 * @property string         $picture
 * @property int            $status
 * @property string         $data
 * @property Collection     $data_obj
 * @property int            $auto_share
 * @property string         $custom_settings
 * @property Collection     $custom_settings_obj
 * @property string         $created_at
 * @property string         $updated_at
 * @property int            $is_deleted
 * @property ChannelSession $channel_session
 */
class Channel extends Model
{
    public static $relations = [
        'channel_session' => [ ChannelSession::class, 'id', 'channel_session_id' ],
    ];

    public static array $writeableColumns = [
        'id',
        'channel_session_id',
        'name',
        'channel_type',
        'remote_id',
        'picture',
        'status',
        'data',
        'auto_share',
        'custom_settings',
        'created_at',
        'updated_at',
        'is_deleted',
    ];

	public function casts () :array {
		return [
			'is_deleted' => 'integer',
		];
	}


    public function getDataObjAttribute ( Collection $channelInf ): Collection
    {
        $arr = json_decode( $channelInf->data ?: '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }

    /**
     * @param Channel $channelInf
     *
     * @return Collection
     */
    public function getCustomSettingsObjAttribute ( Collection $channelInf ): Collection
    {
        $arr = json_decode( $channelInf->custom_settings ?: '[]', true );
        $arr = is_array( $arr ) ? $arr : [];

        return new Collection( $arr );
    }


	/**
	 * @param $permission
	 * @param Channel $channelInf
	 *
	 * @return bool
	 */
	public static function hasPermission ( $permission, Collection $channelInf = null ): bool
	{
		if ( ! is_user_logged_in() )
			return false;

		return self::hasPermissionByUserId( get_current_user_id(), $permission, $channelInf );
	}

	/**
	 * @param                 $userId
	 * @param                 $permission
	 * @param Collection|null $channelInf
	 *
	 * @return bool
	 */
	public static function hasPermissionByUserId ( $userId, $permission, Collection $channelInf = null )
	{
		$user = get_user_by( 'id', $userId );

		$channelSession = $channelInf->channel_session->fetch();

		if( $channelSession->created_by == $user->ID )
			return true;

		if( empty( $user->roles ) )
			return false;

		$checkPermission = ChannelPermission::where('channel_id', $channelInf->id)
		                                    ->where('user_role', 'in', $user->roles);

		/**
		 * can_share uchun sorgu atilibsa, o halda where filterine hech gerek yoxdu.
		 * Chunki en kichik permissiondu, tablede row varsa demek kimi, minimum can_share-di.
		 * Eks halda where filteri qalsa, full_access-i seche bilmeyecek.
		 */
		if( ! empty( $permission ) && $permission != 'can_share' )
			$checkPermission = $checkPermission->where('permission', $permission);

		$checkPermission = $checkPermission->fetch();

		return ! empty( $checkPermission );
	}

    public static function booted ()
    {
        self::addGlobalScope( 'my_channels', function ( QueryBuilder $builder, $queryType )
        {
            if ( $queryType !== 'select' && $queryType !== 'delete' && $queryType !== 'update' )
                return;

            if ( !is_user_logged_in() )
                return;

            $builder->where( function ( $query ) use ( $queryType )
            {
                $user            = wp_get_current_user();
                $channelSessions = ChannelSession::where( 'id', DB::field( 'channel_session_id', 'channels' ) )->where( 'created_by', $user->ID )->select( 'id' );
                $query->where( 'channel_session_id', 'in', $channelSessions );

                if ( !empty( $user->roles ) )
                {
                    $permissions = $queryType === 'select' ? [ 'can_share', 'full_access' ] : [ 'full_access' ];
                    $subQuery    = ChannelPermission::select( 'DISTINCT channel_id', true )->where( 'user_role', 'in', $user->roles )->where( 'permission', 'in', $permissions );
                    $query->orWhere( self::getField( 'id' ), 'in', $subQuery );
                }
            } );
        } );

	    self::addGlobalScope( 'soft_delete', function ( QueryBuilder $builder, $queryType )
	    {
		    if ( $queryType === 'select' )
			    $builder->where( 'is_deleted', 0 );
	    } );
    }

}
