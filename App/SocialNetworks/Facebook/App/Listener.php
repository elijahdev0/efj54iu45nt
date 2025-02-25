<?php

namespace FSPoster\App\SocialNetworks\Facebook\App;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Facebook\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Facebook\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Facebook\Api\AppMethod\Api as AppMethodApi;
use FSPoster\App\SocialNetworks\Facebook\Api\AppMethod\AuthData as AppMethodAuthData;
use FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod\Api as CookieMethodApi;
use FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod\AuthData as CookieMethodAuthData;

class Listener
{

    public static function sharePost ( ScheduleResponseObject $result, ScheduleObject $scheduleObj ): ScheduleResponseObject
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
            return $result;

	    $postingDataAdapter = new PostingDataAdapter( $scheduleObj );
	    $postingData = $postingDataAdapter->getPostingData();
	    $authDataArray = $scheduleObj->getChannelSession()->data_obj->auth_data ?? [];

        if ( $scheduleObj->getChannelSession()->method === 'app' )
        {
	        $authData = new AppMethodAuthData();
	        $authData->setFromArray( $authDataArray );

            if ( $scheduleObj->getChannel()->channel_type === 'ownpage' )
	            $authData->accessToken = $scheduleObj->getChannel()->data_obj->access_token;

	        $api = new AppMethodApi();
	        $api->setProxy( $scheduleObj->getChannelSession()->proxy )
		        ->setAuthException( ChannelSessionException::class )
                ->setPostException( ScheduleShareException::class )
                ->setAuthData( $authData );
        }
		else
        {
	        $authData = new CookieMethodAuthData();
	        $authData->setFromArray( $authDataArray );

	        if( in_array( $scheduleObj->getChannel()->channel_type, ['ownpage', 'ownpage_story'] ) )
	        {
		        $authData->newPageID = empty( $scheduleObj->getChannel()->data_obj->delegate_page_id ) ? null : $scheduleObj->getChannel()->remote_id;
	        }
	        else if( ! empty( $scheduleObj->getChannel()->custom_settings_obj->group_poster ) )
	        {
		        $posterChannelId = $scheduleObj->getChannel()->custom_settings_obj->group_poster;
		        $posterChannel = Channel::get( $posterChannelId );
		        $authData->newPageID = empty( $posterChannel->data_obj->delegate_page_id ) ? null : $posterChannel->remote_id;
	        }

	        $api = new CookieMethodApi();
	        $api->setProxy( $scheduleObj->getChannelSession()->proxy )
	            ->setAuthException( ChannelSessionException::class )
	            ->setPostException( ScheduleShareException::class )
	            ->setAuthData( $authData );
        }

		$postId = $api->sendPost( $postingData );

		if( $scheduleObj->getChannelSession()->method === 'cookie' && $api->needsSessionUpdate )
			ChannelAdapter::updateChannelCookies( $scheduleObj->getChannelSession()->id, $api->authData );

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

		$edge = in_array( $channel->channel_type, ['account_story', 'ownpage_story'] ) ? 'story' : 'feed';

		if( $edge === 'story' )
		{
			$uploadMedia = true;
			$mediaTypeToUpload = 'featured_image';
			$attachLink = (bool)Settings::get( 'fb_story_attach_link', false );
			$postContent = Settings::get( 'fb_story_text', '{post_title}' );
		}
		else
		{
			$uploadMedia = (bool)Settings::get( 'fb_post_upload_media', false );
			$mediaTypeToUpload = Settings::get( 'fb_post_media_type_to_upload', 'featured_image' );
			$attachLink = (bool)Settings::get( 'fb_post_attach_link', true );
			$postContent = Settings::get( 'fb_post_text', '{post_title}' );

			if( Settings::get( 'fb_share_to_first_comment', false ) )
				$customPostData["first_comment"] = Settings::get( 'fb_first_comment_text', '' );
		}

		$customPostData["attach_link"] = $attachLink;
		$customPostData["upload_media"] = $uploadMedia;
		$customPostData["upload_media_type"] = $mediaTypeToUpload;

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = $postContent;

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        if ( $channelSession->method === 'app' )
        {
	        $authData = new AppMethodAuthData();
	        $authData->setFromArray( $channelSession->data_obj->auth_data ?? [] );

	        $api = new AppMethodApi();
	        $api->setProxy( $channelSession->proxy )
	            ->setAuthException( ChannelSessionException::class )
	            ->setAuthData( $authData );

            try
            {
                $checkChannelSession = $api->getMe();
            } catch ( ChannelSessionException $e )
            {
                $checkChannelSession = false;
            }

            if ( $checkChannelSession )
                Channel::where( 'id', $channel->id )->update( [ 'status' => 0 ] );
			else
                Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
        }
		else
            Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

    public static function getChannelSessionData ( $sessionData, string $socialNetwork, Collection $channelSession, Collection $channel)
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $channelSession->method !== 'cookie' )
        {
            return $sessionData;
        }

        return $channelSession->data_obj->auth_data ?? null;
    }

    public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appId     = $request->require( 'app_id', RestRequest::TYPE_STRING, fsp__( 'app_id field is empty' ) );
        $appSecret = $request->require( 'app_secret', RestRequest::TYPE_STRING, fsp__( 'app_secret field is empty' ) );

        if( ! AppMethodApi::checkApp( $appId, $appSecret ) )
			throw new \Exception( fsp__( "App credentials are invalid" ) );

        return [
            'app_id'     => $appId,
            'app_secret' => $appSecret,
        ];
    }

	/**
	 * @param array     $insights
	 * @param string    $social_network
	 * @param Schedule  $schedule
	 *
	 * @return array
	 */
    public static function getInsights ( array $insights, string $social_network, Collection $schedule ): array
    {
        if ( $social_network !== Bootstrap::getInstance()->getSlug() )
            return $insights;

        $channel = Channel::where( 'id', $schedule->channel_id )->fetch();
        $channelSession = $channel->channel_session->fetch();

	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

		if( $channelSession->method === 'app' )
		{
			$authData = new AppMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new AppMethodApi();
		}
		else
		{
			$authData = new CookieMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new CookieMethodApi();
		}

	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        $stats = $api->getStats( $channel->remote_id, $schedule->remote_post_id );

	    if( $channelSession->method === 'cookie' && $api->needsSessionUpdate )
		    ChannelAdapter::updateChannelCookies( $channelSession->id, $api->authData );

        return array_merge( $insights, $stats );
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

    public static function getAuthURL ( string $url, string $socialNetwork, Collection $app, ?string $proxy ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return AppMethodApi::getAuthURL( $app->data_obj->app_id, $callbackUrl );
    }

    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $code = Request::get( 'code', '', 'string' );

	    if ( empty( $code ) )
	    {
		    $errorMsg = Request::get( 'error_message', '', 'str' );

		    throw new SocialNetworkApiException( $errorMsg ?: 'Unknown error!' );
	    }

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    $authData = new AppMethodAuthData();
	    $authData->appClientId = $app->data_obj->app_id;
	    $authData->appClientSecret = $app->data_obj->app_secret;

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $api->fetchAccessToken( $code, $callbackUrl );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [ 'channels' => $channels ];
    }

    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        $accessToken = Request::get( 'access_token', '', 'string' );

        if ( empty( $accessToken ) || $socialNetwork != Bootstrap::getInstance()->getSlug() )
            return $data;

	    $authData = new AppMethodAuthData();
	    $authData->appClientId = $app->data_obj->app_id;
	    $authData->appClientSecret = $app->data_obj->app_secret;
	    $authData->accessToken = $accessToken;

        $api = new AppMethodApi();
        $api->setProxy( $proxy )
            ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

        $authData->accessTokenExpiresOn = $api->getAccessTokenExpiresDate();

        $api = $api->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [ 'channels' => $channels ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'https://fb.com/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannel ( array $channelInfo, string $socialNetwork, Collection $channel, Collection $channelSession ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelInfo;

        $channelOptions = $channel->custom_settings_obj->toArray()[ 'customization_data' ] ?? [];

        if ( $channel->channel_type === 'group' )
        {
            $channelInfo[ 'poster_name' ] = !empty( $channelOptions[ 'group_poster' ] ) ?
                ( Channel::get( $channelOptions[ 'group_poster' ] )->name ?? $channelSession->name )
                : $channelSession->name;
        } else
        {
            $channelInfo[ 'poster_name' ] = null;
        }

        return $channelInfo;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return 'https://fb.com/' . esc_html( $channel->remote_id );
    }

    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $updatedChannel;

		$authDataArray = $channelSession->data_obj->auth_data ?? [];

        if ( $channelSession->method === 'app' )
        {
	        $authData = new AppMethodAuthData();
	        $authData->setFromArray( $authDataArray );

	        $api = new AppMethodApi();
        }
		else
        {
	        $authData = new CookieMethodAuthData();
	        $authData->setFromArray( $authDataArray );

	        $api = new CookieMethodApi();
        }

	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $refreshedChannels = ChannelAdapter::fetchChannels( $api, $channelSession->method );

	    if( $channelSession->method === 'cookie' && $api->needsSessionUpdate )
		    ChannelAdapter::updateChannelCookies( $channelSession->id, $api->authData );

        foreach ( $refreshedChannels as $refreshedChannel )
        {
            if ( $refreshedChannel[ 'remote_id' ] == $channel->remote_id && $refreshedChannel['channel_type'] == $channel->channel_type )
            {
                return $refreshedChannel;
            }
        }

        return $updatedChannel;
    }

}