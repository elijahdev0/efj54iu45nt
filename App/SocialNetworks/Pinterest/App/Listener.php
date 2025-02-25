<?php

namespace FSPoster\App\SocialNetworks\Pinterest\App;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Pinterest\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Pinterest\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Pinterest\Api\AppMethod\Api as AppMethodApi;
use FSPoster\App\SocialNetworks\Pinterest\Api\AppMethod\AuthData as AppMethodAuthData;
use FSPoster\App\SocialNetworks\Pinterest\Api\CookieMethod\Api as CookieMethodApi;
use FSPoster\App\SocialNetworks\Pinterest\Api\CookieMethod\AuthData as CookieMethodAuthData;

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
			return $result;

		$authDataArray = $scheduleObj->getChannelSession()->data_obj->auth_data ?? [];
		$postingDataAdapter = new PostingDataAdapter( $scheduleObj );
		$postingData = $postingDataAdapter->getPostingData();

		if( $scheduleObj->getChannelSession()->method === 'app' )
		{
			$authData = new AppMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new AppMethodApi();

			$api->setProxy( $scheduleObj->getChannelSession()->proxy )
			    ->setAuthException( ChannelSessionException::class )
			    ->setPostException( ScheduleShareException::class )
			    ->setAuthData( $authData );

			ChannelAdapter::updateAuthDataIfRefreshed( $scheduleObj->getChannelSession(), $authData );
		}
		else
		{
			$authData = new CookieMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new CookieMethodApi();

			$api->setProxy( $scheduleObj->getChannelSession()->proxy )
			    ->setAuthException( ChannelSessionException::class )
			    ->setPostException( ScheduleShareException::class )
			    ->setAuthData( $authData );
		}

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

		$customPostData['post_title'] = Settings::get( 'pinterest_post_title', '{post_title}' );
		$customPostData['alt_text'] = Settings::get( 'pinterest_alt_text', '' );
		$customPostData["attach_link"] = (bool)Settings::get( 'pinterest_attach_link', true );
		$customPostData["upload_media_type"] = 'all_images';

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings['post_content'];
		else
			$customPostData['post_content'] = Settings::get( 'pinterest_post_content', '{post_content limit="497"}' );

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

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

        return [
            'app_id'     => $appId,
            'app_secret' => $appSecret,
        ];
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content   = $postingData->getPostingDataTitle();
		$calendarData->mediaList = $postingData->getPostingDataUploadMedia();

		return $calendarData;
	}

    public static function getAuthURL ( string $url, string $socialNetwork, Collection $app, ?string $proxy ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

		$appId = $app->data_obj->app_id;
		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();
		$state = Bootstrap::STATE;

        return AppMethodApi::getAuthURL( $appId, $callbackUrl, $state );
    }

    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $code = Request::get( 'code', '', 'string' );

	    if ( empty( $code ) )
	    {
		    $error_message = Request::get( 'error_message', '', 'str' );
		    throw new SocialNetworkApiException( $error_message );
	    }

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    $authData = new AppMethodAuthData();
	    $authData->appId = $app->data_obj->app_id;
	    $authData->appSecret = $app->data_obj->app_secret;

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    // fetch access token via temporary token and update auth data
	    $api->fetchAccessToken( $code, $callbackUrl );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [
		    'channels' => $channels,
	    ];
    }

    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        $accessToken  = Request::get( 'access_token', '', 'string' );
        $refreshToken = Request::get( 'refresh_token', '', 'string' );
        $expiresIn    = Request::get( 'expires_in', '', 'string' );

        if ( empty( $accessToken ) || empty( $refreshToken ) || empty( $expiresIn ) || $socialNetwork !== Bootstrap::getInstance()->getSlug() )
        {
            return $data;
        }

        $refreshToken = urldecode( $refreshToken );

	    $authData = new AppMethodAuthData();
	    $authData->appId = $app->data_obj->app_id;
	    $authData->appSecret = $app->data_obj->app_secret;
	    $authData->accessToken = urldecode( $accessToken );
	    $authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . $expiresIn . ' seconds' );
	    $authData->refreshToken = urldecode( $refreshToken );

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [ 'channels' => $channels ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'https://www.pinterest.com/pin/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        $channelSession = ChannelSession::get( $channel[ 'channel_session_id' ] );

        if ( $channelSession->method === 'app' )
            return 'https://www.pinterest.com/' . $channel->remote_id;

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

	    if( $channelSession->method === 'app' )
	    {
		    $authData = new AppMethodAuthData();
		    $authData->setFromArray( $authDataArray );

		    $api = new AppMethodApi();
		    $api->setProxy( $channelSession->proxy )
		        ->setAuthException( ChannelSessionException::class )
		        ->setAuthData( $authData );

		    ChannelAdapter::updateAuthDataIfRefreshed( $channelSession, $authData );
	    }
		else
		{
			$authData = new CookieMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new CookieMethodApi();
			$api->setProxy( $channelSession->proxy )
			    ->setAuthException( ChannelSessionException::class )
			    ->setAuthData( $authData );
		}

	    $refreshedChannels = ChannelAdapter::fetchChannels( $api, $channelSession->method );

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