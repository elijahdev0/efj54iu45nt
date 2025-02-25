<?php

namespace FSPoster\App\SocialNetworks\Reddit\App;

use FSPoster\App\Providers\Core\Route;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Bootstrap extends SocialNetworkAddon
{
    protected $slug = 'reddit';
    protected $name = 'Reddit';
    protected $icon = 'fab fa-reddit-alien';
    protected $sort = 80;

    public function init ()
    {
        Route::get( 'social-networks/reddit/channels', [ Controller::class, 'fetchChannels' ] );
        Route::post( 'social-networks/reddit/settings', [ Controller::class, 'saveSettings' ] );
        Route::get( 'social-networks/reddit/settings', [ Controller::class, 'getSettings' ] );
        Route::post( 'social-networks/reddit/get-subreddit-flairs', [ Controller::class, 'getSubredditFlairs' ] );
        Route::post( 'social-networks/reddit/search-subreddits', [ Controller::class, 'searchSubreddits' ] );

	    add_filter( 'fsp_share_post', [ Listener::class, 'sharePost' ], 10, 2 );
	    add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 10, 3 );

        add_filter( 'fsp_add_app', [ Listener::class, 'addApp' ], 10, 3 );
        add_filter( 'fsp_auth_get_url', [ Listener::class, 'getAuthURL' ], 10, 4 );
        add_filter( 'fsp_auth_get_channels', [ Listener::class, 'getAuthChannels' ], 10, 4 );
        add_filter( 'fsp_standard_app_get_channels', [ Listener::class, 'getStandardAppChannels' ], 10, 4 );
        add_filter( 'fsp_get_shared_post_link', [ Listener::class, 'getPostLink' ], 10, 2 );
        add_filter( 'fsp_get_channel', [ Listener::class, 'getChannel' ], 10, 4 );
        add_filter( 'fsp_get_channel_link', [ Listener::class, 'getChannelLink' ], 10, 3 );
	    add_filter( 'fsp_get_calendar_data', [ Listener::class, 'getCalendarData' ], 10, 2 );

        add_filter( 'fsp_refresh_channel', [ Listener::class, 'refreshChannel' ], 10, 4 );
        add_action( 'fsp_disable_channel', [ Listener::class, 'disableSocialChannel' ], 10, 3 );
    }
}