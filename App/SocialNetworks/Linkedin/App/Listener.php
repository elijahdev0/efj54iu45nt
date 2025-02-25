<?php

namespace FSPoster\App\SocialNetworks\Linkedin\App;

use Exception;
use FSPoster\App\Models\App;
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
use FSPoster\App\SocialNetworks\Linkedin\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Linkedin\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Linkedin\Api\Api;
use FSPoster\App\SocialNetworks\Linkedin\Api\AuthData;

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

		$customPostData["attach_link"] = (bool)Settings::get( 'linkedin_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'linkedin_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'linkedin_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'linkedin_post_content', '{post_title}' );

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
    public static function addApp ( array $data, $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appId     = $request->require( 'client_id', RestRequest::TYPE_STRING, fsp__( 'Client ID is empty' ) );
        $appSecret = $request->require( 'client_secret', RestRequest::TYPE_STRING, fsp__( 'Client Secret is empty' ) );

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

	/**
	 * @param string      $url
	 * @param string      $socialNetwork
	 * @param App  $app
	 * @param string|null $proxy
	 *
	 * @return string
	 */
    public static function getAuthURL ( string $url, string $socialNetwork, Collection $app, ?string $proxy ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

	    $appId = $app->data_obj->client_id;
	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return Api::getAuthURL( $appId, $callbackUrl );
    }

	/**
	 * @param array       $data
	 * @param string      $socialNetwork
	 * @param App  $app
	 * @param string|null $proxy
	 *
	 * @return array
	 * @throws SocialNetworkApiException
	 */
    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $code = Request::get( 'code', '', 'string' );

	    if ( empty( $code ) )
	    {
		    $error_description = Request::get( 'error_description', '', 'str' );

		    throw new SocialNetworkApiException( $error_description );
	    }

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

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

	/**
	 * @param array       $data
	 * @param string      $socialNetwork
	 * @param App  $app
	 * @param string|null $proxy
	 *
	 * @return array
	 * @throws SocialNetworkApiException
	 */
    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        $accessToken    = Request::get( 'access_token', '', 'string' );
        $expireIn       = Request::get( 'expires_in', '', 'string' );
        $refreshToken   = Request::get( 'refresh_token', '', 'string' );

        if ( empty( $accessToken ) || empty( $expireIn ) || empty( $refreshToken ) || $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

	    $authData = new AuthData();
	    $authData->appClientId = $app->data_obj->client_id;
	    $authData->appClientSecret = $app->data_obj->client_secret;
	    $authData->accessToken = $accessToken;
	    $authData->accessTokenExpiresOn = Date::dateTimeSQL( urldecode( $expireIn ) );
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

        return 'https://www.linkedin.com/feed/update/' . $scheduleObj->getSchedule()->remote_post_id . '/';
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

		if( $channel->channel_type === 'account' )
			return 'https://www.linkedin.com/in/';

        return 'https://www.linkedin.com/company/' . esc_html( $channel->remote_id );
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