<?php

namespace FSPoster\App\SocialNetworks\WordPress\App;

use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\WordPress\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\WordPress\Api\Api;
use FSPoster\App\SocialNetworks\WordPress\Api\AuthData;

class Controller
{

    public static function addChannel ( RestRequest $request ): array
    {
        $site_url = $request->require( 'site_url', RestRequest::TYPE_STRING, fsp__( 'Please enter the website URL' ) );
        $username = $request->require( 'username', RestRequest::TYPE_STRING, fsp__( 'Please enter the username' ) );
        $password = $request->require( 'password', RestRequest::TYPE_STRING, fsp__( 'Please enter the password' ) );
        $proxy    = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

        if ( !preg_match( '/^http(s|):\/\//i', $site_url ) )
            throw new SocialNetworkApiException( fsp__( 'The URL must start with http(s)' ) );


	    $authData = new AuthData();
	    $authData->siteUrl = $site_url;
		$authData->username = $username;
		$authData->password = $password;

	    $api = new Api();
	    $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api );

        return [ 'channels' => $channels ];
    }

    public static function saveSettings ( RestRequest $request ): array
    {
        $postTitle          = $request->param( 'post_title', '', RestRequest::TYPE_STRING );
        $postExcerpt        = $request->param( 'post_excerpt', '', RestRequest::TYPE_STRING );
        $postText           = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );
        $postStatus         = $request->param( 'post_status', '', RestRequest::TYPE_STRING );
        $preservePostType   = (int)$request->param( 'preserve_post_type', false, RestRequest::TYPE_BOOL );
        $sendCategories     = (int)$request->param( 'send_categories', false, RestRequest::TYPE_BOOL );
        $sendTags           = (int)$request->param( 'send_tags', false, RestRequest::TYPE_BOOL );

        Settings::set( 'wordpress_post_title', $postTitle );
        Settings::set( 'wordpress_post_excerpt', $postExcerpt );
        Settings::set( 'wordpress_post_content', $postText );
	    Settings::set( 'wordpress_upload_media', (int)$uploadMedia );
	    Settings::set( 'wordpress_media_type_to_upload', $mediaTypeToUpload );
        Settings::set( 'wordpress_post_status', $postStatus );
        Settings::set( 'wordpress_preserve_post_type', $preservePostType );
        Settings::set( 'wordpress_send_categories', $sendCategories );
        Settings::set( 'wordpress_send_tags', $sendTags );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_title'            => Settings::get( 'wordpress_post_title', '{post_title}' ),
		    'post_excerpt'          => Settings::get( 'wordpress_post_excerpt', '{post_excerpt}' ),
		    'post_text'             => Settings::get( 'wordpress_post_content', '{post_content}' ),
		    'upload_media'          => (bool)Settings::get( 'wordpress_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'wordpress_media_type_to_upload', 'featured_image' ),
		    'post_status'           => Settings::get( 'wordpress_post_status', 'publish' ),
		    'post_status_options'   => [
			    [
				    'label' => fsp__( 'Publish' ),
				    'value' => 'publish',
			    ],
			    [
				    'label' => fsp__( 'Private' ),
				    'value' => 'private',
			    ],
			    [
				    'label' => fsp__( 'Draft' ),
				    'value' => 'draft',
			    ],
			    [
				    'label' => fsp__( 'Pending' ),
				    'value' => 'pending',
			    ],
		    ],
		    'preserve_post_type'    => (bool)Settings::get( 'wordpress_preserve_post_type', true ),
		    'send_categories'       => (bool)Settings::get( 'wordpress_send_categories', true ),
		    'send_tags'             => (bool)Settings::get( 'wordpress_send_tags', true ),
	    ], Bootstrap::getInstance()->getSlug());
    }
}