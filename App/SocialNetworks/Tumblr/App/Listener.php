<?php

namespace FSPoster\App\SocialNetworks\Tumblr\App;

use Exception;
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
use FSPoster\App\SocialNetworks\Tumblr\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Tumblr\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Tumblr\Api\Api;
use FSPoster\App\SocialNetworks\Tumblr\Api\AuthData;

class Listener
{

	/**
	 * @throws ChannelSessionException
	 * @throws Exception
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

		$response = $api->sendPost( $postingData );

		$snPostResponse = new ScheduleResponseObject();
		$snPostResponse->status         = 'success';
		$snPostResponse->remote_post_id = $response;

		return $snPostResponse;
	}

	/**
	 * @param array      $customPostData
	 * @param Channel    $channel
	 * @param string     $socialNetwork
	 *
	 * @return array
	 */
	public static function getCustomPostData ( array $customPostData, Collection $channel, string $socialNetwork )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $customPostData;

		$channelSettings = $channel->custom_settings_obj->custom_post_data;

		$customPostData["post_title"] = Settings::get( 'tumblr_post_title', '{post_title}' );
		$customPostData["send_tags"] = (bool)Settings::get( 'tumblr_send_tags', false );
		$customPostData["custom_tags"] = [];

		$customPostData["attach_link"] = (bool)Settings::get( 'tumblr_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'tumblr_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'tumblr_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'tumblr_post_content', "{post_content}" );

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

    public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appKey    = $request->require( 'consumer_key', RestRequest::TYPE_STRING, fsp__( 'Consumer Key is empty' ) );
        $appSecret = $request->require( 'consumer_secret', RestRequest::TYPE_STRING, fsp__( 'Secret Key is empty' ) );

        return [
            'consumer_key'    => $appKey,
            'consumer_secret' => $appSecret,
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

    public static function getAuthURL ( $url, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

	    $appKey = $app->data_obj->consumer_key;
	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

		return Api::getAuthURL( $appKey, $callbackUrl );
    }

    public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $oauthCode = Request::get( 'code', '', 'string' );

	    if ( empty( $oauthCode ) )
	    {
		    $error = Request::get( 'error', '', 'str' );
		    throw new SocialNetworkApiException( $error );
	    }

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    $authData = new AuthData();
	    $authData->appConsumerKey = $app->data_obj->consumer_key;
	    $authData->appConsumerSecret = $app->data_obj->consumer_secret;

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
        $accessToken  = Request::get( 'access_token', '', 'string' );
        $refreshToken = Request::get( 'refresh_token', '', 'string' );
        $expiresIn    = Request::get( 'expires_in', '', 'string' );

        if ( empty( $accessToken ) || empty( $refreshToken ) || empty( $expiresIn ) || $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        date_default_timezone_set( 'Asia/Baku' );

	    $authData = new AuthData();
	    $authData->accessToken = $accessToken;
	    $authData->refreshToken = urldecode( $refreshToken );
	    $authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . $expiresIn . ' seconds' );
	    $authData->appConsumerKey = $app->data_obj->consumer_key;
	    $authData->appConsumerSecret = $app->data_obj->consumer_secret;

	    $api = new Api();
	    $api = $api->setProxy( $proxy )
	               ->setAuthException( ChannelSessionException::class )
	               ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api );

	    return [
		    'channels' => $channels,
	    ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'https://tumblr.com/' . $scheduleObj->getChannel()->remote_id . '/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return 'https://' . esc_html( $channel->remote_id ) . '.tumblr.com';
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