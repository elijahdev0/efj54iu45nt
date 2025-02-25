<?php

namespace FSPoster\App\Providers\Helpers;

use FSPoster\App\Providers\Core\LocalizationService;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\SocialNetwork\CallbackUrlHandler;
use FSPoster\App\Providers\Helpers\Date;

class AssetHelper
{

	public static function enqueueAssets ()
	{

		$globalState = [
			'restBaseUrl'       => esc_url_raw( rest_url() ) . 'fs-poster/',
			'restNonce'         => wp_create_nonce( 'wp_rest' ),
			'canLoadSettings'   => PluginHelper::canAccessToSettings(),
			'disabled'          => false,
			'isInstalled'       => PluginHelper::isPluginActivated(),
			'callbackUrls'      => CallbackUrlHandler::getCallbackUrlsByNetwors(),
            'userId'            => get_current_user_id(),
			'translations'      => LocalizationService::getAllStrings(),
            'timeZone' 			=> ['zone' => Date::getZone(), 'UTC' => Date::getUTC()]
		];

		if ( PluginHelper::isPluginDisabled() )
		{
			$globalState['disabled'] = true;
			$globalState['disabled_reason'] = Settings::get( 'plugin_alert', '' );
		}

		add_action( 'admin_enqueue_scripts', function () use ( $globalState )
		{
			wp_enqueue_media();
			wp_enqueue_style( 'fs-poster-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' );
			wp_add_inline_style( 'fs-poster-font', '
                #wpwrap:has(#fs-poster-dashboard) #wpcontent { padding: 0 !important; }
                #wpwrap:has(#fs-poster-dashboard) #wpfooter { display: none !important; }
                #wpbody-content:has(#fs-poster-dashboard) > *:not(#fs-poster-dashboard) { display: none !important; }
                #fs-poster-metabox-container .inside { padding: 0 !important; }
                .fs-poster-dashboard { position: fixed; top: 0; left: 160px;right: 0; bottom: 0; }
                html.wp-toolbar .fs-poster-dashboard { top: 32px; }
                .folded .fs-poster-dashboard { left: 36px; }
                @media screen and (max-width: 960px) { .auto-fold .fs-poster-dashboard { left: 36px; } }
                @media screen and (max-width: 782px) { .fs-poster-dashboard { left: 0 !important; } }
            ' );

			add_filter('script_loader_tag', function ($tag, $handle, $src)
			{
				if ( strpos( $handle, 'fs-poster' ) === 0 )
				{
					$tag = str_replace('></script>', ' type="module"></script>', $tag);
				}

				return $tag;
			} , 10, 3);

			wp_register_script( 'fs-poster-global-state', false );
			wp_enqueue_script( 'fs-poster-global-state' );
			wp_add_inline_script( 'fs-poster-global-state', 'window.FS_POSTER = ' . json_encode( $globalState ), 'before' );

			if ( PluginHelper::isDevelopmentMode() )
			{
				add_action( 'admin_head', function ()
				{
					echo '<script type="module">
                        import RefreshRuntime from "' . network_site_url() . ':3000/@react-refresh"
                        RefreshRuntime.injectIntoGlobalHook(window)
                        window.$RefreshReg$ = () => {}
                        window.$RefreshSig$ = () => (type) => type
                        window.__vite_plugin_react_preamble_installed__ = true
                    </script>';
					echo '<script type="module" src="' . network_site_url() . ':3000/@vite/client"></script>';
					echo '<script type="module" src="' . network_site_url() . ':3000/src/app.tsx"></script>';
				} );
			} else
			{
				wp_enqueue_script( 'fs-poster-portal', Helper::getFrontendAssetUrl('src/portal.tsx') );
				wp_add_inline_script( 'fs-poster-portal', 'window.FS_POSTER_STYLESHEET = "' . Helper::getFrontendAssetUrl('style.css') . '"', 'before' );
			}
		} );

		add_action( 'admin_footer', function ()
		{
			echo '<div id="fs-poster-portal"></div>';
		} );
	}

}