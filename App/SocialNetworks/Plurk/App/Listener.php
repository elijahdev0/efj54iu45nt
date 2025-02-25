<?php

namespace FSPoster\App\SocialNetworks\Plurk\App;

use Exception;
use FSPoster\App\Models\App;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Schedule;
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
use FSPoster\App\SocialNetworks\Plurk\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Plurk\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Plurk\Api\AuthData;
use FSPoster\App\SocialNetworks\Plurk\Api\Api;
use FSPoster\GuzzleHttp\Exception\GuzzleException;

class Listener
{

    /**
     * @param ScheduleResponseObject $result
     * @param ScheduleObject         $scheduleObj
     *
     * @return ScheduleResponseObject
     * @throws GuzzleException
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

		$customPostData['qualifier'] = Settings::get( 'plurk_qualifier', ':' );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings['post_content'];
		else
			$customPostData['post_content'] = Settings::get( 'plurk_post_content', "{post_title}\n{post_featured_image_url}\n{post_content limit=\"200\"}" );

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

        $appKey    = $request->require( 'app_key', RestRequest::TYPE_STRING, fsp__( 'App Key is empty' ) );
        $appSecret = $request->require( 'app_secret', RestRequest::TYPE_STRING, fsp__( 'App Secret is empty' ) );

        return [
            'app_key'    => $appKey,
            'app_secret' => $appSecret,
        ];
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content = $postingData->getPostingDataMessage();

		return $calendarData;
	}

    /**
     * @param string      $url
     * @param string      $socialNetwork
     * @param App         $app
     * @param string|null $proxy
     *
     * @return string
     * @throws Exception
     */
    public static function getAuthURL ( string $url, string $socialNetwork, Collection $app, ?string $proxy ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $url;

        $authData = new AuthData();
	    $authData->appKey = $app->data_obj->app_key;
	    $authData->appSecret = $app->data_obj->app_secret;

        $api = new Api();
        $api->setProxy( $proxy )
            ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        $requestToken = $api->fetchAccessToken( 'request' );

        Session::set( 'request_token', $requestToken[ 'token' ] );
        Session::set( 'request_token_secret', $requestToken[ 'secret' ] );

        return Api::AUTH_APP_LINK . $requestToken[ 'token' ];
    }

    /**
     * @param array       $data
     * @param string      $socialNetwork
     * @param App         $app
     * @param string|null $proxy
     *
     * @return array
     */
    public static function getAuthChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

        $requestToken       = Session::get( 'request_token' );
        $requestTokenSecret = Session::get( 'request_token_secret' );

        Session::remove( 'request_token' );
        Session::remove( 'request_token_secret' );

        $verifier = Request::get( 'oauth_verifier', '', Request::TYPE_STRING );

        if ( empty( $verifier ) )
            throw new Exception( 'Oauth verifier is empty' );

        $authData = new AuthData();
	    $authData->appKey            = $app->data_obj->app_key;
	    $authData->appSecret         = $app->data_obj->app_secret;
        $authData->accessToken       = $requestToken;
        $authData->accessTokenSecret = $requestTokenSecret;

        $api = new Api();
        $api->setProxy( $proxy )
            ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        // fetch access token via temporary token and update auth data
        $fetchedAccessToken = $api->fetchAccessToken( 'access', $verifier );

        $authData->accessToken       = $fetchedAccessToken['token'];
        $authData->accessTokenSecret = $fetchedAccessToken['secret'];

        $channels = ChannelAdapter::fetchChannels( $api );

        return [ 'channels' => $channels ];
    }

    /**
     * @param array       $data
     * @param string      $socialNetwork
     * @param App         $app
     * @param string|null $proxy
     *
     * @return array
     */
    public static function getStandardAppChannels ( array $data, string $socialNetwork, Collection $app, ?string $proxy ): array
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() || $app->social_network !== Bootstrap::getInstance()->getSlug() )
			return $data;

        $accessToken       = Request::get( 'access_token', '', 'string' );
        $accessTokenSecret = Request::get( 'access_token_secret', '', 'string' );

        $authData = new AuthData();
        $authData->appKey = $app->data_obj->app_key;
        $authData->appSecret = $app->data_obj->app_secret;
        $authData->accessToken = $accessToken;
        $authData->accessTokenSecret = $accessTokenSecret;

        $api = new Api();
        $api->setProxy( $proxy )
            ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

        $channels = ChannelAdapter::fetchChannels( $api );

        return [ 'channels' => $channels ];
    }

    /**
     * @param array          $insights
     * @param string         $social_network
     * @param Schedule       $schedule
     * @param Channel        $channel
     * @param ChannelSession $channelSession
     *
     * @return array
     */
    public static function getInsights ( array $insights, string $social_network, Collection $schedule, Collection $channel, Collection $channelSession ): array
    {
        if ( $social_network !== Bootstrap::getInstance()->getSlug() )
            return $insights;

        try
        {
	        $authDataArray = $channelSession->data_obj->auth_data ?? [];

	        $authData = new AuthData();
	        $authData->setFromArray( $authDataArray );

            $api = new Api();
            $api->setAuthException( ChannelSessionException::class )
	            ->setAuthData( $authData );

	        try
	        {
		        $stats = $api->getStats( $schedule->remote_post_id );
	        } catch ( GuzzleException $e )
	        {
		        $stats = [];
	        }

	        $stats = [
		        [
			        'label' => fsp__( 'Comments' ),
			        'value' => $stats->plurk->response_count ?? 0,
		        ],
		        [
			        'label' => fsp__( 'Likes' ),
			        'value' => $stats->plurk->favorite_count ?? 0,
		        ],
		        [
			        'label' => fsp__( 'Shares' ),
			        'value' => $stats->plurk->replurkers_count ?? 0,
		        ],
	        ];

            return array_merge( $insights, $stats );
        } catch ( Exception $e )
        {
            return $insights;
        }
    }

    /**
     * @param string   $postLink
     * @param string   $socialNetwork
     * @param Schedule $schedule
     * @param Channel  $channel
     *
     * @return string
     */
    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'https://plurk.com/p/' . base_convert( $scheduleObj->getSchedule()->remote_post_id, 10, 36 );
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return 'https://plurk.com/' . esc_html( $channel->data_obj->username );
    }

    /**
     * @param array          $updatedChannel
     * @param string         $socialNetwork
     * @param Channel        $channel
     * @param ChannelSession $channelSession
     *
     * @return array
     * @throws SocialNetworkApiException
     */
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