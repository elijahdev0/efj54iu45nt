<?php

namespace FSPoster\App\SocialNetworks\Linkedin\App;

use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;

class Controller
{
    public static function saveSettings ( RestRequest $request ): array
    {
        $postText           = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $cutPostText        = (int)$request->param( 'cut_post_text', false, RestRequest::TYPE_BOOL );
	    $attachLink         = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'linkedin_post_content', $postText );
        Settings::set( 'linkedin_cut_post_text', $cutPostText );
	    Settings::set( 'linkedin_attach_link', (int)$attachLink );
	    Settings::set( 'linkedin_upload_media', (int)$uploadMedia );
	    Settings::set( 'linkedin_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_text'             => Settings::get( 'linkedin_post_content', '{post_title}' ),
		    'cut_post_text'         => (bool)Settings::get( 'linkedin_cut_post_text', true ),
		    'attach_link'           => (bool)Settings::get( 'linkedin_attach_link', true ),
		    'upload_media'          => (bool)Settings::get( 'linkedin_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'linkedin_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }
}