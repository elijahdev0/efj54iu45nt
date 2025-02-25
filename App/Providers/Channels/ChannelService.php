<?php

namespace FSPoster\App\Providers\Channels;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelLabelsData;
use FSPoster\App\Models\ChannelPermission;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\PostComment;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;

class ChannelService
{

    public static function addChannelSession ( array $channelSession ): int
    {
        $userID = get_current_user_id();

        if ( empty( $userID ) )
            throw new SocialNetworkApiException( fsp__( 'The current WordPress user ID is not available. Please, check if your security plugins prevent user authorization.' ) );

        $existingSession = ChannelSession::where( 'remote_id', $channelSession[ 'remote_id' ] )
            ->where( 'method', $channelSession[ 'method' ] )
            ->where( 'social_network', $channelSession[ 'social_network' ] )// doit ferqli proxy ile elave etmek istese? ele case ola bilermi vobshe? yoxlamag lazimdi
            ->where( 'created_by', $userID )
            ->fetch();

        $channelSession[ 'updated_at' ] = Date::dateTimeSQL();

        if ( empty( $existingSession ) )
        {
            $channelSession[ 'created_at' ] = Date::dateTimeSQL();
        } else
        {
            $existingData = $existingSession->data_obj->toArray();

            $channelSession[ 'data' ] = array_merge( $existingData, $channelSession[ 'data' ] );
        }

        if ( empty( $existingSession ) )
        {
            $channelSession[ 'created_by' ] = get_current_user_id();

            $channelSession = apply_filters( 'fsp_add_channel_session', $channelSession );

            $channelSession[ 'data' ] = json_encode( $channelSession[ 'data' ] ?? [] );

            ChannelSession::insert( $channelSession );
            $lastID = Channel::lastId();
        } else
        {
            $channelSession = apply_filters( 'fsp_update_channel_session', $channelSession );

            $channelSession[ 'data' ] = json_encode( $channelSession[ 'data' ] );

            $lastID = $existingSession[ 'id' ];
            ChannelSession::where( 'id', $lastID )->update( $channelSession );
        }

        return $lastID;
    }

    /**
     * @param $wpPostId
     *
     * @return Channel[]
     */
    public static function getActiveChannelsToAutoShare ( $wpPostId ): array
    {
	    $wpPost = \WP_Post::get_instance( $wpPostId );
	    $postAuthor = $wpPost->post_author;

        $finalChannels = [];

	    /**
	     * Bu hisseye APi ile ve ya programmatically gire biler. Bu halda Logged In user olmayacag ve
	     * permissionlari check eden my_channels error verecek ve ya sehv result qaytaracag.
	     * Ona gore hemen scopeni sondurub, permissionlai post_author ile check etmek lazimdi.
	     */
        $channels = Channel::withoutGlobalScope('my_channels')
                            ->where( 'auto_share', 1 )
				            ->where( 'status', 1 )
				            ->fetchAll();

        foreach ( $channels as $channel )
        {
			if( ! $channel->hasPermissionByUserId( $postAuthor, 'can_share' ) )
				continue;

            $channelCustomSettings = json_decode( $channel->custom_settings, true );

            if ( !empty( $channel->auto_share ) && isset( $channelCustomSettings[ 'post_filter_type' ] ) && $channelCustomSettings[ 'post_filter_type' ] !== 'all' && !empty( $channelCustomSettings[ 'post_filter_terms' ] ) )
            {
                $termsInQuery = DB::DB()->prepare( '( ' . implode( ',', array_fill( 0, count( $channelCustomSettings[ 'post_filter_terms' ] ), '%d' ) ) . ' )', $channelCustomSettings[ 'post_filter_terms' ] );

                $result = DB::DB()->get_row( 'SELECT count(0) AS r_count FROM `' . DB::WPtable( 'term_relationships', true ) . '` WHERE object_id=' . (int)$wpPostId . ' AND `term_taxonomy_id` IN (SELECT `term_taxonomy_id` FROM `' . DB::WPtable( 'term_taxonomy', true ) . '` WHERE `term_id` IN ' . $termsInQuery . ')', ARRAY_A );

                if ( ( $channelCustomSettings[ 'post_filter_type' ] == 'in' && $result[ 'r_count' ] == 0 ) || ( $channelCustomSettings[ 'post_filter_type' ] == 'out' && $result[ 'r_count' ] > 0 ) )
                {
                    continue;
                }
            }

            $finalChannels[] = $channel;
        }

        return $finalChannels;
    }

	public static function deleteChannel ( $channelId )
	{
		$channelId = (int)$channelId;

		do_action( 'fsp_delete_channel', $channelId );

		// silinəcək hesabları plannerlərdən silsin
		Planner::update( [
			'channels' => DB::field( "TRIM(BOTH ',' FROM replace(concat(',',`channels`,','), ',$channelId,',','))" ),
		] );
		//doit bosh planneri silsin getsin

		$checkIfChannelHaveNotSharedSchedules = Schedule::withoutGlobalScope( 'blog' )
		                                                ->withoutGlobalScope( 'my_schedules' )
		                                                ->where( 'channel_id', $channelId )
		                                                ->where( 'status', 'IN', [ 'success', 'error' ] )
		                                                ->count();

		if( $checkIfChannelHaveNotSharedSchedules > 0 )
		{
			Channel::withoutGlobalScope( 'my_channels' )
			       ->where( 'id', $channelId )
			       ->update( [ 'is_deleted' => 1 ] );
		}
		else
		{
			Channel::withoutGlobalScope( 'my_channels' )
			       ->where( 'id', $channelId )
			       ->delete();
		}

		// silinəcək channellərə aid paylaşılmamış schedulelər silinsin
		Schedule::withoutGlobalScope( 'my_schedules' )
		        ->withoutGlobalScope( 'blog' )
		        ->where( 'channel_id', $channelId )
				->where( 'status', 'not in', [ 'success', 'error' ] )
		        ->delete();

		$allMyChannelsSubQuery = Channel::withoutGlobalScope( 'my_channels' )
		                                ->withoutGlobalScope( 'soft_delete' );

		// channeli olmayan label data silinsin
		ChannelLabelsData::where( 'channel_id', 'not in', $allMyChannelsSubQuery->select( 'id', true ) )->delete();

		// Post Comments silinsin doit silinmelidi?
		PostComment::where( 'channel_id', 'not in', $allMyChannelsSubQuery->select( 'id', true ) )->delete();

		// channeli olmayan sessionlar silinsin
		ChannelPermission::where( 'channel_id', 'not in', $allMyChannelsSubQuery->select( 'id', true ) )->delete();

		// channeli olmayan channel sessions silinsin
		ChannelSession::where( 'id', 'not in', $allMyChannelsSubQuery->select( 'channel_session_id', true ) )->delete();
	}

}