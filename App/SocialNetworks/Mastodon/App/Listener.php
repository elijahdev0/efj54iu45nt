<?php

namespace FSPoster\App\SocialNetworks\Mastodon\App;

use Exception;
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
use FSPoster\App\SocialNetworks\Mastodon\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Mastodon\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Mastodon\Api\Api;
use FSPoster\App\SocialNetworks\Mastodon\Api\AuthData;

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

		$customPostData["attach_link"] = (bool)Settings::get( 'mastodon_attach_link', true );
		$customPostData["upload_media"] = (bool)Settings::get( 'mastodon_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'mastodon_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'mastodon_post_content', '{post_title}' );

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

        $server    = $request->require( 'server', RestRequest::TYPE_STRING, 'Mastodon server field is empty' );
        $appKey    = $request->require( 'client_key', RestRequest::TYPE_STRING, fsp__( 'client_key field is empty' ) );
        $appSecret = $request->require( 'client_secret', RestRequest::TYPE_STRING, fsp__( 'client_secret field is empty' ) );

        $server = trim( $server, '/' );

        if ( !filter_var( $server, FILTER_VALIDATE_URL ) )
            throw new Exception( fsp__( 'URL must be a valid URL' ) );

        return [
            'client_key'    => $appKey,
            'client_secret' => $appSecret,
            'server'        => $server,
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

	    $appId  = $app->data_obj->client_key;
	    $server = $app->data_obj->server;
	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return Api::getAuthURL( $appId, $server, $callbackUrl );
    }

    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $code = Request::get( 'code', '', 'string' );
	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

	    if ( empty( $code ) )
		    throw new SocialNetworkApiException( fsp__('Code is empty!') );

	    $authData = new AuthData();
	    $authData->server = $app->data_obj->server;
	    $authData->appClientKey = $app->data_obj->client_key;
	    $authData->appClientSecret = $app->data_obj->client_secret;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    // fetch access token via temporary token and update auth data
	    $api->fetchAccessToken( $code, $callbackUrl );

	    $channels = ChannelAdapter::fetchChannels( $api );

	    return [
		    'channels' => $channels
	    ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return ($scheduleObj->getChannelSession()->data_obj->auth_data['server'] ?? '') . '/@' . $scheduleObj->getChannel()->data_obj->username . '/' . $scheduleObj->getSchedule()->remote_post_id;
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return ($channel->channel_session->fetch()->data_obj->auth_data['server'] ?? '') . '/@' . $channel->data_obj->username;
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