<?php

namespace FSPoster\App\SocialNetworks\Tumblr\App;

use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;

class Controller
{

    public static function saveSettings ( RestRequest $request ): array
    {
        $sendTags           = (int)$request->param( 'send_tags', false, RestRequest::TYPE_BOOL );
        $postTitle          = $request->param( 'post_title', '', RestRequest::TYPE_STRING );
        $postText           = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
	    $attachLink         = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'tumblr_send_tags', $sendTags );
        Settings::set( 'tumblr_post_title', $postTitle );
        Settings::set( 'tumblr_post_content', $postText );
	    Settings::set( 'tumblr_attach_link', (int)$attachLink );
	    Settings::set( 'tumblr_upload_media', (int)$uploadMedia );
	    Settings::set( 'tumblr_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'send_tags'             => (bool)Settings::get( 'tumblr_send_tags', false ),
		    'post_title'            => Settings::get( 'tumblr_post_title', '{post_title}' ),
		    'post_text'             => Settings::get( 'tumblr_post_content', '{post_content}' ),
		    'attach_link'           => (bool)Settings::get( 'tumblr_attach_link', true ),
		    'upload_media'          => (bool)Settings::get( 'tumblr_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'tumblr_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }

}