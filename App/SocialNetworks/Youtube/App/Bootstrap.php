<?php

namespace FSPoster\App\SocialNetworks\Youtube\App;

use FSPoster\App\Providers\Core\Route;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Bootstrap extends SocialNetworkAddon
{
    protected $slug = 'youtube';
    protected $name = 'Youtube Community';
    protected $icon = 'fab fa-youtube-square';
    protected $sort = 90;

    public function init ()
    {
        Route::post( 'social-networks/youtube/channel', [ Controller::class, 'addChannel' ] );
        Route::post( 'social-networks/youtube/settings', [ Controller::class, 'saveSettings' ] );
        Route::get( 'social-networks/youtube/settings', [ Controller::class, 'getSettings' ] );

	    add_filter( 'fsp_share_post', [ Listener::class, 'sharePost' ], 10, 2 );
	    add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 10, 3 );

        add_filter( 'fsp_get_channel_session_data', [ Listener::class, 'getChannelSessionData' ], 10, 4 );
        add_filter( 'fsp_get_shared_post_link', [ Listener::class, 'getPostLink' ], 10, 2 );
        add_filter( 'fsp_get_channel_link', [ Listener::class, 'getChannelLink' ], 10, 3 );
	    add_filter( 'fsp_get_calendar_data', [ Listener::class, 'getCalendarData' ], 10, 2 );

        add_filter( 'fsp_refresh_channel', [ Listener::class, 'refreshChannel' ], 10, 4 );
        add_action( 'fsp_disable_channel', [ Listener::class, 'disableSocialChannel' ], 10, 3 );
    }
}