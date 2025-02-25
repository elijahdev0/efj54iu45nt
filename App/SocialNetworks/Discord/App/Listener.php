<?php

namespace FSPoster\App\SocialNetworks\Discord\App;

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
use FSPoster\App\SocialNetworks\Discord\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Discord\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Discord\Api\AuthData;
use FSPoster\App\SocialNetworks\Discord\Api\Api;

class Listener
{

    public static function sharePost ( ScheduleResponseObject $result, ScheduleObject $scheduleObj ): ScheduleResponseObject
    {
	    if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
	    {
		    return $result;
	    }

	    $authDataArray = $scheduleObj->getChannelSession()->data_obj->auth_data ?? [];

	    $authData = new AuthData();
	    $authData->setFromArray( $authDataArray );

	    $postingData = new PostingDataAdapter( $scheduleObj );
	    $postingData = $postingData->getPostingData();

	    $lib = new Api();

	    $postId = $lib->setProxy( $scheduleObj->getChannelSession()->proxy )
	                    ->setAuthException( ChannelSessionException::class )
	                    ->setPostException( ScheduleShareException::class )
	                    ->setAuthData( $authData )
	                    ->sendPost( $postingData );

	    $result->status         = 'success';
	    $result->remote_post_id = $postId;

	    return $result;
    }

	public static function getCustomPostData ( array $customPostData, Collection $channel, string $socialNetwork ): array
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $customPostData;

		$channelSettings = $channel->custom_settings_obj->custom_post_data;

		$customPostData["upload_media"] = (bool)Settings::get( 'discord_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'discord_media_type_to_upload', 'featured_image' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'discord_post_content', '{post_title}' );

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

        $appId    = $request->require( 'app_id', RestRequest::TYPE_STRING, fsp__( 'app_id field is empty' ) );
        $botToken = $request->require( 'bot_token', RestRequest::TYPE_STRING, fsp__( 'bot_token field is empty' ) );

        return [
            'app_id'    => $appId,
            'bot_token' => $botToken,
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

    public static function getAuthURL ( $url, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

	    $clientId = $app->data_obj->app_id;
		$callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return Api::getAuthURL( $clientId, $callbackUrl );
    }

    public static function getAuthChannels ( $data, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

	    $guildID     = Request::get( 'guild_id', 0, 'int' );
	    $permissions = Request::get( 'permissions', 0, 'int', [ 51200 ] );

	    if ( $permissions === 0 )
		    throw new SocialNetworkApiException( fsp__( 'Required permission not given' ) );

	    if ( empty( $guildID ) || ! is_numeric( $guildID ) )
		    throw new SocialNetworkApiException( fsp__( 'Server not found' ) );

	    $authData = new AuthData();
	    $authData->clientId = $app->data_obj->app_id;
	    $authData->botToken = $app->data_obj->bot_token;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, $guildID );

	    return [
		    'channels' => $channels,
	    ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return sprintf( "https://discord.com/channels/%d/%d/%d", $scheduleObj->getChannelSession()->remote_id, $scheduleObj->getChannel()->remote_id, $scheduleObj->getSchedule()->remote_post_id );
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return sprintf( "https://discord.com/channels/%d/%d", $channel->channel_session->fetch()->remote_id, $channel->remote_id );
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


	    $refreshedChannels = ChannelAdapter::fetchChannels( $api, $channelSession->remote_id );

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