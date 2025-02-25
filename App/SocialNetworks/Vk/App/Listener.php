<?php

namespace FSPoster\App\SocialNetworks\Vk\App;

use Exception;
use FSPoster\App\Models\App;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Vk\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Vk\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Vk\Api\Api;
use FSPoster\App\SocialNetworks\Vk\Api\AuthData;

class Listener
{

    /**
     * @param ScheduleResponseObject $result
     * @param ScheduleObject         $scheduleObj
     *
     * @return ScheduleResponseObject
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

        $postId = $api->setProxy( $scheduleObj->getChannelSession()->proxy )
                    ->setPostException( ScheduleShareException::class )
                    ->setAuthException( ChannelSessionException::class )
                    ->setAuthData( $authData )
                    ->sendPost( $postingData );

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

		$customPostData["attach_link"] = (bool)Settings::get( 'vk_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'vk_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'vk_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'vk_post_content', '{post_title}' );

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

    /**
     * @throws Exception
     */
    public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appId     = $request->require( 'app_id', 'string', fsp__( 'App ID is empty' ) );
        $secureKey = $request->require( 'secure_key', 'string', fsp__( 'Secure Key is empty' ) );

        return [
            'app_id'     => $appId,
            'secure_key' => $secureKey,
        ];
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

    /**
     * @param string      $url
     * @param string      $socialNetwork
     * @param App         $app
     * @param string|null $proxy
     *
     * @return string
     */
    public static function getAuthURL ( string $url, string $socialNetwork, Collection $app, ?string $proxy ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

		$callBackUrl = 'https://oauth.vk.com/blank.html';

        return Api::getAuthUrl( $app->data_obj->app_id, $callBackUrl );
    }

    /**
     * @param string   $postLink
     * @param string   $socialNetwork
     * @param Schedule $schedule
     * @param Channel  $channel
     *
     * @return string
     */
    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() ) return $postLink;

        return 'https://vk.com/wall' . $scheduleObj->getSchedule()->remote_post_id;
    }

    /**
     * @param string  $channelLink
     * @param string  $socialNetwork
     * @param Channel $channel
     *
     * @return string
     */
    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() ) return $channelLink;

        if ( $channel->channel_type === 'account' )
        {
            return 'https://vk.com/id' . esc_html( $channel->remote_id );
        }

        return 'https://vk.com/' . esc_html( $channel->data_obj->username );
    }

    /**
     * @param array          $updatedChannel
     * @param string         $socialNetwork
     * @param Channel        $channel
     * @param ChannelSession $channelSession
     *
     * @return array
     * @throws SocialNetworkApiException
     */
    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession ): array
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
            ->setAuthData( $authData );

        $refreshedChannels = ChannelAdapter::fetchChannels( $api );

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