<?php

namespace FSPoster\App\SocialNetworks\Vk\App;

use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\SocialNetworks\Vk\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Vk\Api\Api;
use FSPoster\App\SocialNetworks\Vk\Api\AuthData;

class Controller
{

	public static function fetchChannels ( RestRequest $request ): array
	{
		$url    = $request->require( 'url', RestRequest::TYPE_STRING, fsp__( 'Please enter the URL' ) );
		$proxy  = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

		preg_match( "/access_token=([^&]+)/", $url, $accessTokenMatch );

		$accessToken = $accessTokenMatch[1] ?? $url;

		$authData = new AuthData();
		$authData->accessToken = $accessToken;

		$api = new Api();
		$api->setProxy( $proxy )
			->setAuthException( ChannelSessionException::class )
			->setAuthData( $authData );

		$channels = ChannelAdapter::fetchChannels( $api );

		return [ 'channels' => $channels ];
	}

    public static function saveSettings ( RestRequest $request ): array
    {
        $postContent        = $request->param( 'post_content', '', RestRequest::TYPE_STRING );
	    $attachLink         = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'vk_post_content', $postContent );
        Settings::set( 'vk_attach_link', (int)$attachLink );
        Settings::set( 'vk_upload_media', (int)$uploadMedia );
        Settings::set( 'vk_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_content'          => Settings::get( 'vk_post_content', '{post_title}' ),
		    'attach_link'           => (bool)Settings::get( 'vk_attach_link', true ),
		    'upload_media'          => (bool)Settings::get( 'vk_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'vk_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }
}