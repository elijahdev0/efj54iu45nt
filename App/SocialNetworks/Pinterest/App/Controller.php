<?php

namespace FSPoster\App\SocialNetworks\Pinterest\App;

use Exception;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Pinterest\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Pinterest\Api\CookieMethod\Api;
use FSPoster\App\SocialNetworks\Pinterest\Api\CookieMethod\AuthData;

class Controller
{
    /**
     * @throws SocialNetworkApiException
     * @throws Exception
     */
    public static function addChannelViaCookie ( RestRequest $request ): array
    {
        $cookie_sess = $request->require( 'cookie_pinterest_sess', RestRequest::TYPE_STRING, fsp__( 'The cookie _pinterest_sess is required' ) );
        $proxy       = $request->param( 'proxy', '', 'string' );

	    $authData = new AuthData();
	    $authData->cookieSess = $cookie_sess;

        $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, 'cookie' );

        return [ 'channels' => $channels ];
    }

    public static function saveSettings ( RestRequest $request ): array
    {
        $postTitle      = $request->param( 'post_title', '', RestRequest::TYPE_STRING );
        $postText       = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $maxImagesCount = (int)$request->param( 'max_images_count', 1, RestRequest::TYPE_INTEGER );
        $altText        = $request->param( 'alt_text', '', RestRequest::TYPE_STRING );
	    $attachLink     = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );

        Settings::set( 'pinterest_post_title', $postTitle );
        Settings::set( 'pinterest_post_content', $postText );
        Settings::set( 'pinterest_max_images_count', $maxImagesCount );
        Settings::set( 'pinterest_alt_text', $altText );
	    Settings::set( 'pinterest_attach_link', (int)$attachLink );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_title'        => Settings::get( 'pinterest_post_title', '{post_title}' ),
		    'post_text'         => Settings::get( 'pinterest_post_content', '{post_content limit="497"}' ),
		    'max_images_count'  => (int)Settings::get( 'pinterest_max_images_count', 1 ),
		    'alt_text'          => Settings::get( 'pinterest_alt_text', '' ),
		    'attach_link'       => (bool)Settings::get( 'pinterest_attach_link', true )
	    ], Bootstrap::getInstance()->getSlug());
    }
}