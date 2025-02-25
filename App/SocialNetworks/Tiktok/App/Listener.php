<?php

namespace FSPoster\App\SocialNetworks\Tiktok\App;

use FSPoster\App\Models\Channel;
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
use FSPoster\App\SocialNetworks\Tiktok\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Tiktok\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Tiktok\Api\Api;
use FSPoster\App\SocialNetworks\Tiktok\Api\AuthData;

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
			->setPostException( ScheduleShareException::class )
			->setAuthException( ChannelSessionException::class )
			->setAuthData( $authData );

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

		$customPostData["upload_media"] = true;
		$customPostData["upload_media_type"] = 'featured_image';

		$customPostData["privacy_level"] = Settings::get( 'tiktok_privacy_level', 'PUBLIC_TO_EVERYONE' );
		$customPostData["disable_duet"] = (bool)Settings::get( 'tiktok_disable_duet', false );
		$customPostData["disable_comment"] = (bool)Settings::get( 'tiktok_disable_comment', false );
		$customPostData["disable_stitch"] = (bool)Settings::get( 'tiktok_disable_stitch', false );
		$customPostData["auto_add_music_to_photo"] = (bool)Settings::get( 'tiktok_auto_add_music_to_photo', true );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'tiktok_post_content', '{post_title}' );

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

		$calendarData->content   = $postingData->getPostingDataMessage();
		$calendarData->mediaList = $postingData->getPostingDataUploadMedia();

		return $calendarData;
	}

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return sprintf( 'https://www.tiktok.com/@%s', $scheduleObj->getChannel()->data_obj->username ?? '' );
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return sprintf( 'https://www.tiktok.com/@%s', $channel->data_obj->username ?? '-' );
    }

	public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $data;

		$clientKey      = $request->require( 'client_key', RestRequest::TYPE_STRING, fsp__( 'client_key field is empty' ) );
		$clientSecret   = $request->require( 'client_secret', RestRequest::TYPE_STRING, fsp__( 'client_secret field is empty' ) );

		return [
			'client_key'        => $clientKey,
			'client_secret'     => $clientSecret,
		];
	}

	public static function getAuthURL ( $url, $socialNetwork, $app, $proxy )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();
		$state = Bootstrap::STATE;

		return Api::getAuthURL( $app->data_obj->client_key, $state, $callbackUrl );
	}

	public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
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

		$authData = new AuthData();
		$authData->appClientKey = $app->data_obj->client_key;
		$authData->appClientSecret = $app->data_obj->client_secret;

		$api = new Api();
		$api->setProxy( $proxy )
		    ->setAuthException( ChannelSessionException::class )
		    ->setAuthData( $authData );

		$api->fetchAccessToken( $code, $callbackUrl );

		$channels = ChannelAdapter::fetchChannels( $api );

		return [ 'channels' => $channels ];
	}

	public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
	{
		$refreshToken = Request::get( 'refresh_token', '', 'string' );
		$accessToken = Request::get( 'access_token', '', 'string' );
		$accessTokenExpiresIn = Request::get( 'access_token_expires_in', '', 'string' );

		if ( empty( $refreshToken ) || empty( $accessToken ) || empty( $accessTokenExpiresIn ) || $socialNetwork != Bootstrap::getInstance()->getSlug() )
			return $data;

		$authData = new AuthData();
		$authData->appClientKey = $app->data_obj->client_key;
		$authData->appClientSecret = $app->data_obj->client_secret;
		$authData->refreshToken = $refreshToken;
		$authData->accessToken = $accessToken;
		$authData->accessTokenExpiresOn = time() + $accessTokenExpiresIn;

		$api = new Api();
		$api->setProxy( $proxy )
		    ->setAuthException( ChannelSessionException::class )
		    ->setAuthData( $authData );

		$channels = ChannelAdapter::fetchChannels( $api );

		return [ 'channels' => $channels ];
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