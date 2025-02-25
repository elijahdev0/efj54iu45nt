<?php

namespace FSPoster\App\SocialNetworks\Xing\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Xing\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Xing\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Xing\Api\Api;
use FSPoster\App\SocialNetworks\Xing\Api\AuthData;

class Listener
{
    /**
     * @throws ChannelSessionException
     * @throws Exception
     */
    public static function sharePost ( ScheduleResponseObject $result, ScheduleObject $scheduleObj ): ScheduleResponseObject
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
        {
            return $result;
        }

        $authDataArray = $scheduleObj->getChannelSession()->data_obj->auth_data ?? [];

        $authData = new AuthData();
        $authData->setFromArray( $authDataArray );

        $postingDataAdapter = new PostingDataAdapter( $scheduleObj );
        $postingData = $postingDataAdapter->getPostingData();

        $api = new Api();

        $api->setProxy( $scheduleObj->getChannelSession()->proxy )
            ->setAuthException( ChannelSessionException::class )
            ->setPostException( ScheduleShareException::class )
            ->setAuthData( $authData )
            ->setClient();

        $postId = $api->sendPost( $postingData );

        $result                 = new ScheduleResponseObject();
        $result->status         = 'success';
        $result->remote_post_id = $postId;

        return $result;
    }

    /**
     * @param array      $customPostData
     * @param Channel    $channel
     * @param string     $socialNetwork
     *
     * @return array
     */
    public static function getCustomPostData( array $customPostData, Collection $channel, string $socialNetwork )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $customPostData;

        $channelSettings = $channel->custom_settings_obj->custom_post_data;

        $customPostData["visibility"] = Settings::get( 'xing_post_visibility', 'public' );
        $customPostData["attach_link"] = (bool)Settings::get( 'xing_attach_link', true );
        $customPostData["upload_media"] = (bool)Settings::get( 'xing_upload_media', false );
        $customPostData["upload_media_type"] = Settings::get( 'xing_media_type_to_upload', 'featured_image' );

        if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
            $customPostData['post_content'] = $channelSettings[ 'post_content' ];
        else
            $customPostData['post_content'] = Settings::get( 'xing_post_content', '{post_title}' );

        return $customPostData;
    }

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
        {
            return;
        }

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

    public static function getChannelSessionData ( $sessionData, string $socialNetwork, Collection $channelSession, Collection $channel)
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
        {
            return $sessionData;
        }

        return $channelSession->data_obj->auth_data ?? null;
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content   = $postingData->getPostingDataMessage();
		$calendarData->mediaList = $postingData->getPostingDataUploadMedia();

		return $calendarData;
	}

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        $postId = $scheduleObj->getSchedule()->remote_post_id;

        return 'https://www.xing.com/discover/detail-activities/' . $postId;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() ) return $channelLink;

        return $channel->data_obj->url;
    }

    /**
     * @throws SocialNetworkApiException
     * @throws ChannelSessionException
     */
    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
        {
            return $updatedChannel;
        }

        $authDataArray = $channelSession->data_obj->auth_data ?? [];

        $authData = new AuthData();
        $authData->setFromArray( $authDataArray );

        $api = new Api();
        $api->setProxy( $channelSession->proxy )
            ->setAuthException( ChannelSessionException::class )
            ->setAuthData($authData)
            ->setClient();

        $refreshedChannels = ChannelAdapter::fetchChannels($api);

        foreach ( $refreshedChannels as $refreshedChannel )
        {
            if ( $refreshedChannel[ 'remote_id' ] == $channel->remote_id )
            {
                return $refreshedChannel;
            }
        }

        return $updatedChannel;
    }

}