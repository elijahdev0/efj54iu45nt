<?php

namespace FSPoster\App\SocialNetworks\Medium\App;

use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\SocialNetworks\Medium\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Medium\Api\Api;
use FSPoster\App\SocialNetworks\Medium\Api\AuthData;

class Controller
{

    public static function addChannelViaToken ( RestRequest $request ): array
    {
        $accessToken = $request->require( 'access_token', RestRequest::TYPE_STRING, fsp__( 'Access token is required' ) );
        $proxy       = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

	    $authData = new AuthData();
	    $authData->accessToken = $accessToken;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api );

	    return [
		    'channels' => $channels
	    ];
    }

    public static function saveSettings ( RestRequest $request ): array
    {
        $postTitle = $request->param( 'post_title', '', RestRequest::TYPE_STRING );
        $postText  = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $sendTags  = (int)$request->param( 'send_tags', false, RestRequest::TYPE_BOOL );

        Settings::set( 'medium_post_title', $postTitle );
        Settings::set( 'medium_post_content', $postText );
        Settings::set( 'medium_send_tags', $sendTags );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_title' => Settings::get( 'medium_post_title', '{post_title}' ),
		    'post_text'  => Settings::get( 'medium_post_content', "<img src=\"{post_featured_image_url}\">\n\n{post_content}\n\n<a href=\"{post_url}\">{post_url}</a>" ),
		    'send_tags'  => (bool)Settings::get( 'medium_send_tags', false ),
	    ], Bootstrap::getInstance()->getSlug());
    }

}