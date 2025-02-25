<?php

namespace FSPoster\App\SocialNetworks\Webhook\App;

use FSPoster\App\Providers\Core\Route;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Bootstrap extends SocialNetworkAddon
{
	protected $slug = 'webhook';
	protected $name = 'Webhook';
	protected $icon = 'fas fa-atlas';
	protected $sort = 160;

	public function init()
	{
		Route::post('social-networks/webhook/channel', [ Controller::class, 'addChannel']);
		Route::post('social-networks/webhook/send-test-request', [ Controller::class, 'sendTestRequest']);

		add_filter( 'fsp_share_post', [ Listener::class, 'sharePost' ], 10, 2 );
		add_filter( 'fsp_channel_custom_post_data', [ Listener::class, 'getCustomPostData' ], 10, 3 );

        add_filter( 'fsp_get_shared_post_link', [ Listener::class, 'getPostLink' ], 10, 2 );
        add_filter( 'fsp_get_channel_link', [ Listener::class, 'getChannelLink' ], 10, 3 );
		add_filter( 'fsp_get_calendar_data', [ Listener::class, 'getCalendarData' ], 10, 2 );
    }
}