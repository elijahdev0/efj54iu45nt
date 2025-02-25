<?php

namespace FSPoster\App\SocialNetworks\Pinterest\App;

use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\Route;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Bootstrap extends SocialNetworkAddon
{
    protected $slug = 'pinterest';
    protected $name = 'Pinterest';
    protected $icon = 'fab fa-pinterest-p';
    protected $sort = 60;

	const STATE = 'fs-poster_pinterest_state';

	public function getCallbackUrl() : ?string
	{
		return self_admin_url('admin.php');
	}

	public function checkIsCallbackRequest () : bool
	{
		$code   = Request::get('code', '', 'string');
		$state  = Request::get('state', '', 'string');

		return ! empty( $code ) && $state == self::STATE;
	}

    public function init ()
    {
        Route::post( 'social-networks/pinterest/channel-via-cookie', [ Controller::class, 'addChannelViaCookie' ] );
        Route::post( 'social-networks/pinterest/settings', [ Controller::class, 'saveSettings' ] );
        Route::get( 'social-networks/pinterest/settings', [ Controller::class, 'getSettings' ] );

	    add_filter( 'fsp_share_post', [ Listener::class, 'sharePost' ], 10, 2 );
	    add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 10, 3 );

	    add_filter( 'fsp_add_app', [ Listener::class, 'addApp' ], 10, 3 );
        add_filter( 'fsp_auth_get_url', [ Listener::class, 'getAuthURL' ], 10, 4 );
        add_filter( 'fsp_auth_get_channels', [ Listener::class, 'getAuthChannels' ], 10, 4 );
        add_filter( 'fsp_standard_app_get_channels', [ Listener::class, 'getStandardAppChannels' ], 10, 4 );
        add_filter( 'fsp_get_channel_session_data', [ Listener::class, 'getChannelSessionData' ], 10, 4 );
        add_filter( 'fsp_get_shared_post_link', [ Listener::class, 'getPostLink' ], 10, 2 );
        add_filter( 'fsp_get_channel_link', [ Listener::class, 'getChannelLink' ], 10, 3 );
	    add_filter( 'fsp_get_calendar_data', [ Listener::class, 'getCalendarData' ], 10, 2 );

        add_filter( 'fsp_refresh_channel', [ Listener::class, 'refreshChannel' ], 10, 4 );
        add_action( 'fsp_disable_channel', [ Listener::class, 'disableSocialChannel' ], 10, 3 );
    }
}