<?php

namespace FSPoster\App\SocialNetworks\Medium\App;

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
use FSPoster\App\SocialNetworks\Medium\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Medium\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Medium\Api\Api;
use FSPoster\App\SocialNetworks\Medium\Api\AuthData;

class Listener
{

	public static function sharePost ( ScheduleResponseObject $result, ScheduleObject $scheduleObj ): ScheduleResponseObject
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $result;

		$postingDataAdapter = new PostingDataAdapter( $scheduleObj );
		$postingData = $postingDataAdapter->getPostingData();
		$authDataArray = $scheduleObj->getChannelSession()->data_obj->auth_data ?? [];

		$authData = new AuthData();
		$authData->setFromArray( $authDataArray );

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

	public static function getCustomPostData( array $customPostData, Collection $channel, string $socialNetwork )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $customPostData;

		$channelSettings = $channel->custom_settings_obj->custom_post_data;

		$customPostData['post_title'] = Settings::get( 'medium_post_title', '{post_title}' );
		$customPostData['send_tags'] = (bool)Settings::get( 'medium_send_tags', false );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'medium_post_content', "<img src=\"{post_featured_image_url}\">\n\n{post_content}\n\n<a href=\"{post_url}\">{post_url}</a>" );

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content = $postingData->getPostingDataTitle();

		return $calendarData;
	}

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'https://medium.com/p/' . esc_html( $scheduleObj->getSchedule()->remote_post_id );
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        if ( $channel->channel_type === 'account' )
            return 'https://medium.com/@' . $channel->data_obj->username;

        return 'https://medium.com/' . $channel->data_obj->username;
    }

	/**
	 * @throws \Exception
	 */
	public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $data;

		$appClientId     = $request->require( 'client_id', RestRequest::TYPE_STRING, fsp__( 'Client ID is empty' ) );
		$appClientSecret = $request->require( 'client_secret', RestRequest::TYPE_STRING, fsp__( 'Client Secret is empty' ) );

		return [
			'client_id'     => $appClientId,
			'client_secret' => $appClientSecret,
		];
	}

    public static function getChannelSessionData ( $sessionData, string $socialNetwork, Collection $channelSession, Collection $channel)
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $channelSession->method !== 'integration_token' )
        {
            return $sessionData;
        }

        return $channelSession->data_obj->auth_data ?? null;
    }

	public static function getAuthURL ( $url, $socialNetwork, $app, $proxy )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

		return Api::getAuthURL( $app->data_obj->client_id, $callbackUrl );
	}

	public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

		$oauthCode = Request::get( 'code', '', 'str' );
		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

		$authData = new AuthData();
		$authData->appClientId = $app->data_obj->client_id;
		$authData->appClientSecret = $app->data_obj->client_secret;

		$api = new Api();
		$api->setProxy( $proxy )
		    ->setAuthException( ChannelSessionException::class )
		    ->setAuthData( $authData );

		// fetch access token via temporary token and update auth data
		$api->fetchAccessToken( $oauthCode, $callbackUrl );

		$channels = ChannelAdapter::fetchChannels( $api );

		return [
			'channels' => $channels,
		];
	}

	public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
	{
		if( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $data;

		$access_token = Request::get( 'access_token', '', 'string' );
		$refreshToken = Request::get( 'refresh_token', '', 'string' );
		$expiresAt    = Request::get( 'expires_at', '', 'int' );

		if ( empty( $access_token ) || empty( $refreshToken ) || empty( $expiresAt ) )
			return $data;

		$authData = new AuthData();
		$authData->accessToken = $access_token;
		$authData->refreshToken = $refreshToken;
		$authData->accessTokenExpiresOn = Date::dateTimeSQL( intval($expiresAt / 1000) );
		$authData->appClientId = $app->data_obj->client_id;
		$authData->appClientSecret = $app->data_obj->client_secret;

		$api = new Api();
		$api = $api->setProxy( $proxy )
		           ->setAuthException( ChannelSessionException::class )
		           ->setAuthData( $authData );

		$channels = ChannelAdapter::fetchChannels( $api );

		return [
			'channels' => $channels,
		];
	}

    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession ): array
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