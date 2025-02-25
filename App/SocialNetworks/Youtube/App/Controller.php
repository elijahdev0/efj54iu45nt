<?php

namespace FSPoster\App\SocialNetworks\Youtube\App;

use Exception;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\SocialNetworks\Youtube\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Youtube\Api\Api;
use FSPoster\App\SocialNetworks\Youtube\Api\AuthData;

class Controller
{
    /**
     * @throws Exception
     */
    public static function addChannel ( RestRequest $request ): array
    {
        $loginInfo     = $request->require( 'login_info', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'LOGIN_INFO' ] ) );
        $secure3ApiSid = $request->require( 'api_sid', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ '__Secure-3PAPISID' ] ) );
        $secure3PSid   = $request->require( 'p_sid', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ '__Secure-3PSID' ] ) );
        $secure3PSidTS = $request->require( 'p_sid_ts', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ '__Secure-3PSIDTS' ] ) );
        $proxy         = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

	    $authData = new AuthData();
	    $authData->cookies = [
		    'LOGIN_INFO'        => $loginInfo,
		    '__Secure-3PAPISID' => $secure3ApiSid,
		    '__Secure-3PSID'    => $secure3PSid,
		    '__Secure-3PSIDTS'  => $secure3PSidTS,
	    ];
		$authData->cookieLastUpdatedAt = time();

	    $api = new Api();
	    $api->setProxy( $proxy )
		    ->setAuthException( ChannelSessionException::class )
		    ->setAuthData( $authData );

		$channels = ChannelAdapter::fetchChannels( $api );

        return [ 'channels' => $channels ];
    }

    public static function saveSettings ( RestRequest $request ): array
    {
        $postText           = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'youtube_post_content', $postText );
	    Settings::set( 'youtube_upload_media', (int)$uploadMedia );
	    Settings::set( 'youtube_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_text'             => Settings::get( 'youtube_post_content', '{post_title}' ),
		    'upload_media'          => (bool)Settings::get( 'youtube_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'youtube_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }
}