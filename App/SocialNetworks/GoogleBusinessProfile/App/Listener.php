<?php

namespace FSPoster\App\SocialNetworks\GoogleBusinessProfile\App;

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
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\Api\AuthData;
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\Api\Api;

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

		$customPostData["attach_link"] = (bool)Settings::get( 'google_b_add_button', true );
		$customPostData["attach_link_type"] = Settings::get( 'google_b_button_type', 'LEARN_MORE' );
		$customPostData["upload_media"] = (bool)Settings::get( 'google_b_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'google_b_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'google_b_post_content', '{post_title}' );

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

        $appId     = $request->require( 'client_id', RestRequest::TYPE_STRING, fsp__( 'client_id field is empty' ) );
        $appSecret = $request->require( 'client_secret', RestRequest::TYPE_STRING, fsp__( 'client_secret field is empty' ) );

        return [
            'client_id'     => $appId,
            'client_secret' => $appSecret,
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

    public static function getAuthURL ( string $url, string $socialNetwork, Collection $app, ?string $proxy ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

	    $clientId = $app->data_obj->client_id;
		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return Api::getAuthURL( $clientId, $callbackUrl );
    }

    public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $code = Request::get( 'code', '', 'str' );
		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    if ( empty( $code ) )
		    throw new SocialNetworkApiException( fsp__('Code is empty!') );

	    $authData = new AuthData();
	    $authData->appClientId = $app->data_obj->client_id;
	    $authData->appClientSecret = $app->data_obj->client_secret;

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
        {
            return $data;
        }

        $refreshToken = urldecode( $refreshToken );

	    $authData = new AuthData();
	    $authData->appClientId = $app->data_obj->client_id;
	    $authData->appClientSecret = $app->data_obj->client_secret;
	    $authData->accessToken = $access_token;
		$authData->refreshToken = $refreshToken;
		$authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . $expiresIn . ' seconds' );

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

        return 'https://local.google.com/place?use=posts&lpsid=' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        $node_id = explode( '/', $channel->remote_id );
        $node_id = end( $node_id );

        return 'https://business.google.com/n/'.$node_id.'/searchprofile';
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