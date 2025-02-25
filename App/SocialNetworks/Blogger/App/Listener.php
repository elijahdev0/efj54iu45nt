<?php

namespace FSPoster\App\SocialNetworks\Blogger\App;

use Exception;
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
use FSPoster\App\SocialNetworks\Blogger\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Blogger\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Blogger\Api\Api;
use FSPoster\App\SocialNetworks\Blogger\Api\AuthData;

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
	    $snPostResponse->remote_post_id = $response[ 'id' ];
	    $snPostResponse->data           = [
		    'id'        => $response['id'],
		    'url'       => $response['url'],
		    'feed_type' => $postingData->kind
	    ];

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

		$customPostData["is_draft"] = Settings::get( 'blogger_post_status', 'draft' ) === 'draft';
		$customPostData["kind"] = '';
		$customPostData["send_pages_as_page"] = (bool)Settings::get( 'blogger_send_pages_as_page', true );
		$customPostData["post_title"] = Settings::get( 'blogger_post_title', '{post_title}' );
		$customPostData["custom_labels"] = [];
		$customPostData["send_categories"] = (bool)Settings::get( 'blogger_send_categories', false );
		$customPostData["send_tags"] = (bool)Settings::get( 'blogger_send_tags', false );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'blogger_post_content', "<img src='{post_featured_image_url}'>\n\n{post_content} \n\n<a href='{post_url}'>{post_url}</a>" );

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

        $appClientId     = $request->require( 'client_id', RestRequest::TYPE_STRING, fsp__( 'Client ID is empty' ) );
        $appClientSecret = $request->require( 'client_secret', RestRequest::TYPE_STRING, fsp__( 'Client Secret is empty' ) );

        return [
            'client_id'     => $appClientId,
            'client_secret' => $appClientSecret,
        ];
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content = $postingData->getPostingDataTitle();

		return $calendarData;
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
        $expiresIn    = Request::get( 'expires_in', '', 'string' );

        if ( empty( $access_token ) || empty( $refreshToken ) || empty( $expiresIn ) )
            return $data;

	    $authData = new AuthData();
	    $authData->accessToken = $access_token;
		$authData->refreshToken = urldecode( $refreshToken );
		$authData->accessTokenExpiresOn = Date::dateTimeSQL( 'now', '+' . $expiresIn . ' seconds' );
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

	/**
	 * @param array      $updatedChannel
	 * @param string     $socialNetwork
	 * @param Channel $channel
	 * @param ChannelSession $channelSession
	 *
	 * @return array
	 */
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

    public static function getPostLink ( string $channelLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return $scheduleObj->getSchedule()->data_obj[ 'url' ];
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return $channel->data_obj->url ?? 'https://www.blogger.com/profile/' . $channel->remote_id;
    }

}