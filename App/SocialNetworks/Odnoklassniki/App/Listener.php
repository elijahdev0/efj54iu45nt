<?php

namespace FSPoster\App\SocialNetworks\Odnoklassniki\App;

use FSPoster\App\Models\Channel;
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
use FSPoster\App\SocialNetworks\Odnoklassniki\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Odnoklassniki\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Odnoklassniki\Api\Api;
use FSPoster\App\SocialNetworks\Odnoklassniki\Api\AuthData;

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

		$authData = new AuthData();
		$authData->setFromArray( $authDataArray );

		$postingDataAdapter = new PostingDataAdapter( $scheduleObj );
		$postingData = $postingDataAdapter->getPostingData();

		$api = new Api();

		$api->setProxy( $scheduleObj->getChannelSession()->proxy )
			  ->setAuthException( ChannelSessionException::class )
			  ->setPostException( ScheduleShareException::class )
              ->setAuthData( $authData );

		ChannelAdapter::updateAuthDataIfRefreshed( $scheduleObj->getChannelSession(), $authData );

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

		$customPostData["attach_link"] = (bool)Settings::get( 'ok_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'ok_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'ok_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'ok_post_content', '{post_title}' );

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }


    public static function addApp ( array $data, $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appId     = $request->require( 'app_id', RestRequest::TYPE_STRING, fsp__( 'App ID is empty' ) );
        $appKey    = $request->require( 'public_key', RestRequest::TYPE_STRING, fsp__( 'App Public Key is empty' ) );
        $appSecret = $request->require( 'secret_key', RestRequest::TYPE_STRING, fsp__( 'App Secret Key is empty' ) );

        return [
            'app_id'     => $appId,
            'public_key' => $appKey,
            'secret_key' => $appSecret,
        ];
    }

    public static function getInsights ( array $insights, string $social_network, Collection $schedule ): array
    {
        if ( $social_network !== Bootstrap::getInstance()->getSlug() )
            return $insights;

	    $channel = Channel::where( 'id', $schedule->channel_id )->fetch();
	    $channelSession = $channel->channel_session->fetch();

        $postId = explode( '/', $schedule->remote_post_id );
        $postId = end( $postId );

	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

	    $authData = new AuthData();
	    $authData->setFromArray( $authDataArray );

	    $api = new Api();
	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        $stats = $api->getStats( $postId );

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

	    $appId = $app->data_obj->app_id;
	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return Api::getAuthURL( $appId, $callbackUrl );
    }

    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $code = Request::get( 'code', '', 'string' );

	    if ( empty( $code ) )
	    {
		    $error_message = Request::get( 'error_message', '', 'str' );
		    throw new SocialNetworkApiException( $error_message ?: 'Error' );
	    }

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    $authData = new AuthData();
	    $authData->appId = $app->data_obj->app_id;
	    $authData->appSecret = $app->data_obj->secret_key;
	    $authData->appPublicKey = $app->data_obj->public_key;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    // fetch access token via temporary token and update auth data
	    $api->fetchAccessToken( $code, $callbackUrl );

	    $channels = ChannelAdapter::fetchChannels( $api );

	    return [
		    'channels' => $channels,
	    ];
    }

    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        $access_token = Request::get( 'access_token', '', 'string' );
        $refreshToken = Request::get( 'refresh_token', '', 'string' );
        $expiresIn    = Request::get( 'expires_in', '', 'string' );

        if ( empty( $access_token ) || empty( $refreshToken ) || empty( $expiresIn ) || $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

	    $authData = new AuthData();
	    $authData->appId = $app->data_obj->app_id;
	    $authData->appSecret = $app->data_obj->secret_key;
	    $authData->appPublicKey = $app->data_obj->public_key;
	    $authData->accessToken = $access_token;
	    $authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . $expiresIn . ' seconds' );
	    $authData->refreshToken = $refreshToken;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api );

	    return [ 'channels' => $channels ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        if ( $scheduleObj->getChannel()->channel_type === 'account' )
            return 'https://ok.ru/profile/' . $scheduleObj->getSchedule()->remote_post_id;

        return 'https://ok.ru/group/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        if ( $channel->channel_type === 'account' )
            return 'https://ok.ru/profile/' . $channel->remote_id;

        return 'https://ok.ru/group/' . $channel->remote_id;
    }

    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $updatedChannel;

	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

	    $authData = new AuthData();
	    $authData->setFromArray( $authDataArray );

	    $api = new Api();
	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    ChannelAdapter::updateAuthDataIfRefreshed( $channelSession, $authData );

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