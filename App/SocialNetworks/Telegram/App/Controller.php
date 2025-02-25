<?php

namespace FSPoster\App\SocialNetworks\Telegram\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\SocialNetworks\Telegram\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Telegram\Api\Api;
use FSPoster\App\SocialNetworks\Telegram\Api\AuthData;

class Controller
{

    public static function fetchChannels ( RestRequest $request ): array
    {
        $botToken = $request->require( 'bot_token', RestRequest::TYPE_STRING, fsp__( 'Please enter your Bot Token' ) );
        $proxy    = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

        $authData        = new AuthData();
        $authData->token = $botToken;

        $data = ( new Api() )->setProxy( $proxy )
                            ->setAuthException( ChannelSessionException::class )
                            ->setAuthData( $authData )
                            ->getBotInfo();

        if ( empty( $data[ 'id' ] ) )
        {
            throw new ChannelSessionException( fsp__( 'The entered Bot Token is invalid' ) );
        }

        $channelSession = ChannelSession::where( 'remote_id', $data[ 'id' ] )->fetch();

        if ( !$channelSession )
        {
            return [
                'channels' => [],
            ];
        }

        $channels = Channel::where( 'channel_session_id', $channelSession->id )->fetchAll();

        foreach ( $channels as &$channel )
        {
            $channel = $channel->toArray();
            $channel['social_network'] = 'telegram';
        }

        return [
            'channels' => $channels,
        ];
    }

    public static function checkChat ( RestRequest $request ): array
    {
        $botToken = $request->require( 'bot_token', RestRequest::TYPE_STRING, fsp__( 'Please enter your Bot Token' ) );
        $chat_id  = $request->param( 'chat_id', '', RestRequest::TYPE_STRING );
        $proxy    = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

        $authData         = new AuthData();
        $authData->token  = $botToken;
        $authData->chatId = $chat_id;

        $tgSDK = new Api();
        $tgSDK->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

        $res = ChannelAdapter::fetchChannels( $tgSDK );

        return [ 'channels' => $res ];
    }

    /**
     * @throws Exception
     */
    public static function fetchActiveChats ( RestRequest $request ): array
    {
        $token = $request->require( 'bot_token', RestRequest::TYPE_STRING, fsp__( 'Please enter your Bot Token' ) );
        $proxy = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

        $authData        = new AuthData();
        $authData->token = $token;

        $tgSDK = new Api();
        $tgSDK->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

        $channels = ChannelAdapter::fetchChannels( $tgSDK );

        return [ 'channels' => $channels ];
    }

    public static function saveSettings ( RestRequest $request ): array
    {
        $postContent            = $request->param( 'post_content', '', RestRequest::TYPE_STRING );
        $cutPostContent         = (int)$request->param( 'cut_post_content', true, RestRequest::TYPE_BOOL );
        $silentNotifications    = (int)$request->param( 'silent_notifications', false, RestRequest::TYPE_BOOL );
        $addReadMoreButton      = (int)$request->param( 'add_read_more_button', false, RestRequest::TYPE_BOOL );
        $readMoreButtonText     = $request->param( 'read_more_button_text', '', RestRequest::TYPE_STRING );
	    $uploadMedia            = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload      = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'telegram_post_content', $postContent );
        Settings::set( 'telegram_cut_post_content', $cutPostContent );
        Settings::set( 'telegram_silent_notifications', $silentNotifications );
        Settings::set( 'telegram_add_read_more_button', $addReadMoreButton );
        Settings::set( 'telegram_read_more_button_text', $readMoreButtonText );
	    Settings::set( 'telegram_upload_media', (int)$uploadMedia );
	    Settings::set( 'telegram_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_content'          => Settings::get( 'telegram_post_content', '{post_title}' ),
		    'cut_post_content'      => (bool)Settings::get( 'telegram_cut_post_content', true ),
		    'silent_notifications'  => (bool)Settings::get( 'telegram_silent_notifications', false ),
		    'add_read_more_button'  => (bool)Settings::get( 'telegram_add_read_more_button', false ),
		    'read_more_button_text' => Settings::get( 'telegram_read_more_button_text', '' ),
		    'upload_media'          => (bool)Settings::get( 'telegram_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'telegram_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }

}