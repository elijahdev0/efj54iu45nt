<?php

namespace FSPoster\App\SocialNetworks\GoogleBusinessProfile\App;

use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;

class Controller
{
    public static function saveSettings ( RestRequest $request ): array
    {
        $postText           = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $cutPostText        = (int)$request->param( 'cut_post_text', false, RestRequest::TYPE_BOOL );
        $addButton          = (int)$request->param( 'add_button', false, RestRequest::TYPE_BOOL );
        $buttonType         = $request->param( 'button_type', 'BOOK', RestRequest::TYPE_STRING );
	    $uploadMedia        = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload  = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        Settings::set( 'google_b_post_content', $postText );
        Settings::set( 'google_b_cut_post_text', $cutPostText );
        Settings::set( 'google_b_add_button', $addButton );
        Settings::set( 'google_b_button_type', $buttonType );
	    Settings::set( 'google_b_upload_media', (int)$uploadMedia );
	    Settings::set( 'google_b_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_text'            => Settings::get( 'google_b_post_content', '{post_title}' ),
		    'cut_post_text'        => (bool)Settings::get( 'google_b_cut_post_text', true ),
		    'add_button'           => (bool)Settings::get( 'google_b_add_button', true ),
		    'button_type'          => Settings::get( 'google_b_button_type', 'LEARN_MORE' ),
		    'button_type_options'  => [
			    [ 'label' => fsp__( 'BOOK' ), 'value' => 'BOOK' ],
			    [ 'label' => fsp__( 'ORDER' ), 'value' => 'ORDER' ],
			    [ 'label' => fsp__( 'SHOP' ), 'value' => 'SHOP' ],
			    [ 'label' => fsp__( 'LEARN MORE' ), 'value' => 'LEARN_MORE' ],
			    [ 'label' => fsp__( 'SIGN UP' ), 'value' => 'SIGN_UP' ],
			    [ 'label' => fsp__( 'WATCH VIDEO' ), 'value' => 'WATCH_VIDEO' ],
			    [ 'label' => fsp__( 'RESERVE' ), 'value' => 'RESERVE' ],
			    [ 'label' => fsp__( 'GET OFFER' ), 'value' => 'GET_OFFER' ],
			    [ 'label' => fsp__( 'CALL' ), 'value' => 'CALL' ],
		    ],
		    'upload_media'          => (bool)Settings::get( 'google_b_upload_media', false ),
		    'media_type_to_upload'  => Settings::get( 'google_b_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }
}