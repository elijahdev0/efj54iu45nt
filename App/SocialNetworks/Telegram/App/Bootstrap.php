<?php

namespace FSPoster\App\SocialNetworks\Telegram\App;

use FSPoster\App\Providers\Core\Route;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Bootstrap extends SocialNetworkAddon
{
    protected $slug = 'telegram';
    protected $name = 'Telegram';
    protected $icon = 'fab fa-telegram-plane';
    protected $sort = 70;

    public function init ()
    {
        Route::post( 'social-networks/telegram/fetch-channels', [ Controller::class, 'fetchChannels' ] );
        Route::post( 'social-networks/telegram/fetch-active-chats', [ Controller::class, 'fetchActiveChats' ] );
        Route::post( 'social-networks/telegram/check-chat', [ Controller::class, 'checkChat' ] );
        Route::post( 'social-networks/telegram/settings', [ Controller::class, 'saveSettings' ] );
        Route::get( 'social-networks/telegram/settings', [ Controller::class, 'getSettings' ] );

	    add_filter( 'fsp_share_post', [ Listener::class, 'sharePost' ], 10, 2 );
	    add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 10, 3 );

        add_filter( 'fsp_get_calendar_data', [ Listener::class, 'getCalendarData' ], 10, 2 );
        add_filter( 'fsp_get_shared_post_link', [ Listener::class, 'getPostLink' ], 10, 2 );
        add_filter( 'fsp_get_channel_link', [ Listener::class, 'getChannelLink' ], 10, 3 );

        add_filter( 'fsp_refresh_channel', [ Listener::class, 'refreshChannel' ], 10, 4 );
        // doit bu birdene FB uchundu, orda page ve ya profil meselesine gore accesstoken, birdene FB-e gore hami edir bunu...
        add_action( 'fsp_disable_channel', [ Listener::class, 'disableSocialChannel' ], 10, 3 );
    }

}