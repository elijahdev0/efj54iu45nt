<?php

namespace FSPoster\App\SocialNetworks\Reddit\App;

use FSPoster\App\Models\Channel;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Session;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Reddit\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Reddit\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Reddit\Api\Api;
use FSPoster\App\SocialNetworks\Reddit\Api\AuthData;

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

		$customPostData['post_title'] = Settings::get( 'reddit_post_title', '{post_title}' );

		if ( Settings::get( 'reddit_share_to_first_comment', true ) )
			$customPostData['first_comment'] = Settings::get( 'reddit_first_comment_text', '' );

		$customPostData["attach_link"] = (bool)Settings::get( 'reddit_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'reddit_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'reddit_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'reddit_post_content', '{post_title}' );

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

        $appId     = $request->require( 'app_id', RestRequest::TYPE_STRING, fsp__( 'App ID is empty' ) );
        $appSecret = $request->require( 'app_secret', RestRequest::TYPE_STRING, fsp__( 'App Secret is empty' ) );

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
	    $state = md5( rand( 111111111, 911111111 ) );

	    Session::set( 'state', $state );

        return Api::getAuthURL( $appId, $callbackUrl, $state );
    }

    public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $stateSess = Session::get( 'state' );

	    if ( empty( $stateSess ) )
		    throw new SocialNetworkApiException();

	    $code  = Request::get( 'code', '', 'string' );
	    $state = Request::get( 'state', '', 'string' );

	    if ( empty( $code ) || $state != $stateSess )
	    {
		    $error_message = Request::get( 'error_message', '', 'str' );

		    throw new SocialNetworkApiException( $error_message );
	    }

	    Session::remove( 'state' );

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    $authData = new AuthData();
	    $authData->appId = $app->data_obj->app_id;
	    $authData->appSecret = $app->data_obj->app_secret;

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
        $accessToken  = Request::get( 'access_token', '', 'string' );
        $refreshToken = Request::get( 'refresh_token', '', 'string' );
        $expiresIn    = Request::get( 'expires_in', '', 'string' );

        if ( empty( $accessToken ) || empty( $refreshToken ) || empty( $expiresIn ) || $socialNetwork != Bootstrap::getInstance()->getSlug() )
            return $data;

	    $authData = new AuthData();
	    $authData->appId = $app->data_obj->app_id;
	    $authData->appSecret = $app->data_obj->app_secret;
	    $authData->accessToken = $accessToken;
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
		    $channelLink = 'https://www.reddit.com/u/' . esc_html( $scheduleObj->getChannel()->data_obj->username );
		else
	        $channelLink = 'https://www.reddit.com/r/' . esc_html( explode( ':', $scheduleObj->getChannel()->remote_id )[0] );

        return $channelLink . '/comments/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannel ( array $channelInfo, string $socialNetwork, Collection $channel, Collection $channelSession ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelInfo;

        $flair = $channel->data_obj->flair ?? [];

        if ( !empty( $flair ) )
            $channelInfo[ 'flair' ] = $flair;

        return $channelInfo;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        if ( $channel->channel_type === 'account' )
            return 'https://www.reddit.com/u/' . esc_html( $channel->data_obj->username );

        return 'https://www.reddit.com/r/' . esc_html( explode( ':', $channel->remote_id )[0] );
    }

    /**
     * @throws SocialNetworkApiException
     * @throws ChannelSessionException
     * @throws SocialNetworkApiException
     */
    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
        {
            return $updatedChannel;
        }

	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

	    $authData = new AuthData();
	    $authData->setFromArray( $authDataArray );

	    $api = new Api();
	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    ChannelAdapter::updateAuthDataIfRefreshed( $channelSession, $authData );

	    if ( $channel->channel_type === 'account' )
        {
            $refreshedChannels = ChannelAdapter::fetchChannels( $api );

            foreach ( $refreshedChannels as $refreshedChannel )
            {
                if ( $refreshedChannel[ 'remote_id' ] == $channel->remote_id )
                {
                    return $refreshedChannel;
                }
            }
        }
		else
        {
            $remoteId = explode( ':', $channel->remote_id )[0];
            foreach ( $api->searchExactSubreddit( $remoteId ) as $subreddit )
            {
                if ( $remoteId == $subreddit['name'] )
                {
                    return [ 'picture' => $subreddit['icon_img'] ?? '' ];
                }
            }
        }

        return $updatedChannel;
    }
}