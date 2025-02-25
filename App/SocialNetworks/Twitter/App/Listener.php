<?php

namespace FSPoster\App\SocialNetworks\Twitter\App;

use FSPoster\App\Models\Channel;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\Session;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Twitter\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Twitter\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Twitter\Api\AppMethod\Api as AppMethodApi;
use FSPoster\App\SocialNetworks\Twitter\Api\AppMethod\AuthData as AppMethodAuthData;
use FSPoster\App\SocialNetworks\Twitter\Api\CookieMethod\Api as CookieMethodApi;
use FSPoster\App\SocialNetworks\Twitter\Api\CookieMethod\AuthData as CookieMethodAuthData;

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

			$api = new AppMethodApi();
		}
		else
		{
			$authData = new CookieMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new CookieMethodApi();
		}

		$api->setProxy( $scheduleObj->getChannelSession()->proxy )
		    ->setAuthException( ChannelSessionException::class )
		    ->setPostException( ScheduleShareException::class )
		    ->setAuthData( $authData );

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

		$customPostData["attach_link"] = (bool)Settings::get( 'twitter_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'twitter_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'twitter_media_type_to_upload', 'featured_image' );

		if( Settings::get( 'twitter_share_to_first_comment', false ) )
			$customPostData["first_comment"] = Settings::get( 'twitter_first_comment_text', '' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'twitter_post_content', '{post_title}' );

		return $customPostData;
	}

    public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appKey    = $request->require( 'api_key', 'string', fsp__( 'API Key is empty' ) );
        $appSecret = $request->require( 'api_key_secret', 'string', fsp__( 'API Secret Key is empty' ) );

        return [
            'api_key'        => $appKey,
            'api_key_secret' => $appSecret,
        ];
    }

    public static function getChannelSessionData ( $sessionData, string $socialNetwork, Collection $channelSession, Collection $channel)
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $channelSession->method !== 'cookie' )
        {
            return $sessionData;
        }

        return $channelSession->data_obj->auth_data ?? null;
    }

    public static function getInsights ( array $insights, string $social_network, Collection $schedule ): array
    {
        if ( $social_network !== Bootstrap::getInstance()->getSlug() )
            return $insights;

        $channel = Channel::where( 'id', $schedule->channel_id )->fetch();
	    $channelSession = $channel->channel_session->fetch();

		if( $channelSession->method !== 'app' )
			return $insights;

	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

	    $authData = new AppMethodAuthData();
	    $authData->setFromArray( $authDataArray );

	    $api = new AppMethodApi();
	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        $stats = $api->getStats( $schedule->remote_post_id );

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

		$appKey = $app->data_obj->api_key;
		$appSecret = $app->data_obj->api_key_secret;
		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

		$authData = AppMethodApi::getAuthData( $appKey, $appSecret, $proxy, $callbackUrl );

	    Session::set( 'oauth_token', $authData[ 'oauth_token' ] );
	    Session::set( 'oauth_token_secret', $authData[ 'oauth_token_secret' ] );

		return $authData['oauth_url'];
    }

    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $oauthVerifier = Request::get( 'oauth_verifier', '', 'str' );
	    $oauthToken    = Request::get( 'oauth_token', '', 'str' );

	    if ( empty( $oauthVerifier ) || empty( $oauthToken ) )
	    {
		    throw new SocialNetworkApiException();
	    }

	    $oauthToken        = Session::get( 'oauth_token' );
	    $oauthTokenSecret = Session::get( 'oauth_token_secret' );

	    Session::remove( 'oauth_token' );
	    Session::remove( 'oauth_token_secret' );

	    $authData = new AppMethodAuthData();
	    $authData->appKey = $app->data_obj->api_key;
	    $authData->appSecret = $app->data_obj->api_key_secret;

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    // fetch access token via temporary token and update auth data
	    $api->fetchAccessToken( $oauthToken, $oauthTokenSecret, $oauthVerifier );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [
		    'channels' => $channels,
	    ];
    }

    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        $oauthToken        = Request::get( 'oauth_token', '', 'string' );
        $oauthTokenSecret = Request::get( 'oauth_token_secret', '', 'string' );

        if ( empty( $oauthToken ) || empty( $oauthTokenSecret ) || $socialNetwork != Bootstrap::getInstance()->getSlug() )
            return $data;

	    $authData = new AppMethodAuthData();
	    $authData->appKey = $app->data_obj->api_key;
	    $authData->appSecret = $app->data_obj->api_key_secret;
		$authData->accessToken = $oauthToken;
		$authData->accessTokenSecret = $oauthTokenSecret;

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [
		    'channels' => $channels,
	    ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'https://twitter.com/' . $scheduleObj->getChannel()->data_obj->username . '/status/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return 'https://twitter.com/' . esc_html( $channel->data_obj->username );
    }

	public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return;

		Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
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