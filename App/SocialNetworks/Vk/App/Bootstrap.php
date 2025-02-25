<?php

namespace FSPoster\App\SocialNetworks\Vk\App;

use FSPoster\App\Providers\Core\Route;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Bootstrap extends SocialNetworkAddon
{
    protected $slug = 'vk';
    protected $name = 'VK';
    protected $icon = 'fab fa-vk';
    protected $sort = 120;

    public function init ()
    {
	    Route::post( 'social-networks/vk/fetch-channels', [ Controller::class, 'fetchChannels' ] );
        Route::post( 'social-networks/vk/settings', [ Controller::class, 'saveSettings' ] );
        Route::get( 'social-networks/vk/settings', [ Controller::class, 'getSettings' ] );

        add_filter( 'fsp_share_post', [ Listener::class, 'sharePost' ], 10, 2 );
	    add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 10, 3 );

	    add_filter( 'fsp_add_app', [ Listener::class, 'addApp' ], 10, 3 );
	    add_filter( 'fsp_get_shared_post_link', [ Listener::class, 'getPostLink' ], 10, 2 );
	    add_filter( 'fsp_get_channel_link', [ Listener::class, 'getChannelLink' ], 10, 3 );
	    add_filter( 'fsp_auth_get_url', [ Listener::class, 'getAuthURL' ], 10, 4 );
	    add_filter( 'fsp_get_calendar_data', [ Listener::class, 'getCalendarData' ], 10, 2 );

	    add_filter( 'fsp_refresh_channel', [ Listener::class, 'refreshChannel' ], 10, 4 );
        add_action( 'fsp_disable_channel', [ Listener::class, 'disableSocialChannel' ], 10, 3 );
    }
}