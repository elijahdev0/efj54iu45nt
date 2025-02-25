<?php

namespace FSPoster\App\SocialNetworks\Instagram\App;

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
use FSPoster\App\SocialNetworks\Instagram\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Instagram\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Instagram\Api\AppMethod\Api as AppMethodApi;
use FSPoster\App\SocialNetworks\Instagram\Api\AppMethod\AuthData as AppMethodAuthData;
use FSPoster\App\SocialNetworks\Instagram\Api\CookieMethod\Api as CookieMethodApi;
use FSPoster\App\SocialNetworks\Instagram\Api\CookieMethod\AuthData as CookieMethodAuthData;
use FSPoster\App\SocialNetworks\Instagram\Api\LoginPassMethod\Api as LoginPassMethodApi;
use FSPoster\App\SocialNetworks\Instagram\Api\LoginPassMethod\AuthData as LoginPassMethodAuthData;

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
		else if( $scheduleObj->getChannelSession()->method === 'cookie' )
		{
			$authData = new CookieMethodAuthData();
			$authData->setFromArray( $authDataArray );

			$api = new CookieMethodApi();
		}
	    else
	    {
		    $authData = new LoginPassMethodAuthData();
		    $authData->setFromArray( $authDataArray );

		    $api = new LoginPassMethodApi();
	    }

	    $api->setProxy( $scheduleObj->getChannelSession()->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setPostException( ScheduleShareException::class )
	        ->setAuthData( $authData );

	    return $api->sendPost( $postingData );
    }

	public static function getCustomPostData( array $customPostData, Collection $channel, string $socialNetwork )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $customPostData;

		$channelSettings = $channel->custom_settings_obj->custom_post_data;

		$edge = $channel->channel_type === 'account_story' ? 'story' : 'feed';

		if( $edge === 'story' )
		{
			$mediaTypeToUpload = 'featured_image';
			$attachLink = (bool)Settings::get( 'instagram_story_send_link', false );
			$postContent = Settings::get( 'instagram_story_text', '{post_title}' );

			$hashtag = Settings::get( 'instagram_story_customization_hashtag_text', '' );
			$sendHashtag = (bool)Settings::get('instagram_story_customization_add_hashtag', false);

			if( $sendHashtag && ! empty( $hashtag ) )
				$customPostData["story_hashtag"] = $hashtag;
		}
		else
		{
			$mediaTypeToUpload = Settings::get( 'instagram_media_type_to_upload', 'featured_image' );
			$attachLink = false;
			$postContent = Settings::get( 'instagram_post_text', '{post_title}' );

			if( isset( $channelSettings[ 'pin_the_post' ] ) )
				$customPostData['pin_the_post'] = (bool)$channelSettings['pin_the_post'];
			else
				$customPostData["pin_the_post"] = (bool)Settings::get( 'instagram_pin_the_post', false );

			if( Settings::get( 'instagram_share_to_first_comment', false ) )
				$customPostData["first_comment"] = Settings::get( 'instagram_first_comment_text', '' );
		}

		$customPostData["attach_link"] = $attachLink;
		$customPostData["upload_media"] = true;
		$customPostData["upload_media_type"] = $mediaTypeToUpload;

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = $postContent;

		return $customPostData;
	}

    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

    public static function getChannelSessionData ( $sessionData, string $socialNetwork, Collection $channelSession, Collection $channel)
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $channelSession->method !== 'cookie' )
        {
            return $sessionData;
        }

        return $channelSession->data_obj->auth_data ?? null;
    }

    public static function addApp ( array $data, string $socialNetwork, RestRequest $request ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return $data;

        $appId     = $request->require( 'app_id', RestRequest::TYPE_STRING, fsp__( 'app_id field is empty' ) );
        $appSecret = $request->require( 'app_secret', RestRequest::TYPE_STRING, fsp__( 'app_secret field is empty' ) );

		if( ! AppMethodApi::checkApp( $appId, $appSecret ) )
			throw new \Exception( fsp__( "App credentials are invalid" ) );

        return [
            'app_id'     => $appId,
            'app_secret' => $appSecret,
        ];
    }

    public static function getInsights ( array $insights, string $social_network, Collection $schedule ): array
    {
        if ( $social_network !== Bootstrap::getInstance()->getSlug() )
            return $insights;

        $channel        = Channel::where( 'id', $schedule->channel_id )->fetch();
        $channelSession = $channel->channel_session->fetch();
	    $authDataArray = $channelSession->data_obj->auth_data ?? [];

	    $postId = $schedule->remote_post_id;

        if ( $channelSession->method === 'app' )
        {
	        $authData = new AppMethodAuthData();
	        $authData->setFromArray( $authDataArray );

            $api = new AppMethodApi();
        }
		else if ( $channelSession->method === 'cookie' )
        {
	        $authData = new CookieMethodAuthData();
	        $authData->setFromArray( $authDataArray );

            $api = new CookieMethodApi();

	        $postId = explode('_', $postId);
	        $postId = $postId[0];
        }
		else
        {
	        $authData = new LoginPassMethodAuthData();
	        $authData->setFromArray( $authDataArray );

            $api = new LoginPassMethodApi();
        }

	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        $stats = $api->getStats( $postId );

        return array_merge( $insights, $stats );
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content   = $postingData->getPostingDataMessage();
		$calendarData->mediaList = $postingData->getPostingDataUploadMedia('', '');

		return $calendarData;
	}

    public static function getAuthURL ( $url, $socialNetwork, $app, $proxy )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

	    $callbackUrl = Bootstrap::getInstance()->getCallbackUrl();

        return AppMethodApi::getAuthURL( $app->data_obj->app_id, $callbackUrl );
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

	    $authData = new AppMethodAuthData();
	    $authData->appClientId = $app->data_obj->app_id;
	    $authData->appClientSecret = $app->data_obj->app_secret;

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $api->fetchAccessToken( $code, $callbackUrl );

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [ 'channels' => $channels ];
    }

    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        $accessToken = Request::get( 'access_token', '', 'string' );

        if ( empty( $accessToken ) || $socialNetwork != Bootstrap::getInstance()->getSlug() )
            return $data;

	    $authData = new AppMethodAuthData();
	    $authData->appClientId = $app->data_obj->app_id;
	    $authData->appClientSecret = $app->data_obj->app_secret;
	    $authData->accessToken = $accessToken;

	    $api = new AppMethodApi();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $authData->accessTokenExpiresOn = $api->getAccessTokenExpiresDate();

	    $channels = ChannelAdapter::fetchChannels( $api, 'app' );

	    return [ 'channels' => $channels ];
    }

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

	    $edge = $scheduleObj->getChannel()->channel_type === 'account_story' ? 'story' : 'feed';

        if ( $edge === 'story' )
            return 'https://www.instagram.com/stories/' . $scheduleObj->getChannel()->data_obj[ 'username' ] . '/';

        return (string)($scheduleObj->getSchedule()->data_obj[ 'url' ] ?? '');
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return 'https://instagram.com/' . esc_html( $channel->data_obj->username );
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
	    else if ( $channelSession->method === 'cookie' )
	    {
		    $authData = new CookieMethodAuthData();
		    $authData->setFromArray( $authDataArray );

		    $api = new CookieMethodApi();
	    }
	    else
	    {
		    $authData = new LoginPassMethodAuthData();
		    $authData->setFromArray( $authDataArray );

		    $api = new LoginPassMethodApi();
	    }

	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $refreshedChannels = ChannelAdapter::fetchChannels( $api, $channelSession->method );

        foreach ( $refreshedChannels as $refreshedChannel )
        {
            if ( $refreshedChannel[ 'remote_id' ] == $channel->remote_id && $refreshedChannel['channel_type'] == $channel->channel_type )
            {
                return $refreshedChannel;
            }
        }

        return $updatedChannel;
    }

}