<?php

namespace FSPoster\App\SocialNetworks\Threads\App;

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
use FSPoster\App\SocialNetworks\Threads\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Threads\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Threads\Api\ThreadsClient;
use FSPoster\App\SocialNetworks\Threads\Api\ThreadsClientAuthData;

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

        $api = new ThreadsClient(['proxy' => $scheduleObj->getChannelSession()->proxy ]);
        $api->authData = new ThreadsClientAuthData($authDataArray);
        $api->authException = ChannelSessionException::class;
        $api->postException = ScheduleShareException::class;
        $api->prepare();

        $postingDataAdapter = new PostingDataAdapter( $scheduleObj );
        $postingData = $postingDataAdapter->getPostingData();

		$post = $api->sendPost( $postingData );

		$result                 = new ScheduleResponseObject();
		$result->status         = 'success';
		$result->remote_post_id = $post['id'];

        if ( isset( $post['permalink'] ) )
            $result->data['permalink'] = $post['permalink'];

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

		$customPostData["attach_link"] = (bool)Settings::get( 'threads_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'threads_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'threads_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'threads_post_content', '{post_title}' );

		return $customPostData;
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

        if (!empty($scheduleObj->getSchedule()->data_obj->permalink))
            return $scheduleObj->getSchedule()->data_obj->permalink;

        return $scheduleObj->getSchedule()->data_obj->url ?? 'https://threads.net';
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return 'https://threads.net/@' . $channel->data_obj->username;
    }

	public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return;

		Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
	}

    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $updatedChannel;

	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

        $api = new ThreadsClient([ 'proxy' => $channelSession->proxy]);
        $api->authData = new ThreadsClientAuthData( $authDataArray );
        $api->authException = ChannelSessionException::class;
        $api->prepare();

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

    public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appId     = $request->require( 'app_id', RestRequest::TYPE_STRING, fsp__( 'App ID field is empty' ) );
        $appSecret = $request->require( 'app_secret', RestRequest::TYPE_STRING, fsp__( 'App Secret field is empty' ) );

        return [
            'app_id'     => $appId,
            'app_secret' => $appSecret,
        ];
    }

    public static function getAuthURL ( $url, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
            return $url;

        $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();
        $clientId = $app->data_obj->app_id;

        return "https://threads.net/oauth/authorize?client_id=" . $clientId . "&redirect_uri=" . $callbackUrl . "&response_type=code&scope=threads_basic,threads_content_publish";
    }

    public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();
        $code = Request::get( 'code', '', 'string' );

        $api = new ThreadsClient([
            'proxy' => $proxy
        ]);

        $api->authData->clientId = $app->data_obj->app_id;
        $api->authData->clientSecret = $app->data_obj->app_secret;
	    $api->authException = SocialNetworkApiException::class;

        $api->exchangeCodeForShortLivedAccessToken( $code, $callbackUrl );
        $api->exchangeShortLivedForLongLivedAccessToken();

        $channels = ChannelAdapter::fetchChannels( $api, 'app' );

        return [ 'channels' => $channels ];
    }

	public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
	{
		$accessToken = Request::get( 'access_token', '', 'string' );
		$accessTokenExpiresAt = Request::get( 'access_token_expires_at', '', 'string' );
		$userId = Request::get( 'user_id', '', 'string' );

		if ( empty( $accessToken ) || empty( $accessTokenExpiresAt ) || empty( $userId ) || $socialNetwork != Bootstrap::getInstance()->getSlug() )
			return $data;

		$api = new ThreadsClient([
			'proxy' => $proxy
		]);

		$api->authData->clientId = $app->data_obj->app_id;
		$api->authData->clientSecret = $app->data_obj->app_secret;
		$api->authData->userAccessToken = $accessToken;
		$api->authData->userAccessTokenExpiresAt = $accessTokenExpiresAt;
		$api->authData->userId = $userId;
		$api->authException = SocialNetworkApiException::class;

		$channels = ChannelAdapter::fetchChannels( $api, 'app' );

		return [ 'channels' => $channels ];
	}

}