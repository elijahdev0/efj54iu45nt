<?php

namespace FSPoster\App\AI\App;

use FSPoster\App\Providers\Core\Route;

class Bootstrap
{

    public static function init()
    {
	    Route::post( 'ai/templates', [ Controller::class, 'saveTemplate' ] );
	    Route::get( 'ai/templates/(?P<id>\d+)', [ Controller::class, 'get' ] );
	    Route::get( 'ai/templates', [ Controller::class, 'listTemplates' ] );
	    // doit
	    Route::post( 'ai/templates/delete', [ Controller::class, 'deleteTemplates' ] );

		add_action( 'fsp_save_settings', [Listener::class, 'saveSocialNetworkSettings'], 10, 2 );
		add_filter( 'fsp_get_settings', [Listener::class, 'getSocialNetworkSettings'], 10, 2 );
	    add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 99, 3 );

	    add_filter( 'fsp_add_short_code', [Listener::class, 'addAIToShortCodes'] );
	    add_filter( 'fsp_schedule_media_list_to_upload', [Listener::class, 'addAIToMediaList'], 10, 2 );
	    add_filter( 'fsp_ai_save_template', [Listener::class, 'save'], 10, 2 );
        add_filter( 'fsp_ai_text_generator', [Listener::class, 'ShortCodeAITextGenerator'], 10, 4 );
        add_filter( 'fsp_ai_image_generator', [Listener::class, 'AIImageGenerator'], 10, 4 );
    }

}