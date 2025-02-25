<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\App\Models\Planner;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\PluginHelper;
use FSPoster\App\AI\App\Bootstrap AS AIBootstrap;
use FSPoster\App\Providers\License\LicenseAdapter;
use FSPoster\App\Providers\SocialNetwork\CallbackUrlHandler;

class Bootstrap
{
    /**
     * Bootstrap constructor.
     */
    public function __construct ()
    {
        $this->registerDefines();
        $this->registerActivationHook();
	    $this->loadPluginTextdomain();
	    $this->loadPluginLinks();
	    $this->registerCustomPostTypesAndMetas();
	    $this->createPostSaveEvent();
	    $this->registerUserDeletedEvent();
		$this->registerCallbackUrlHandler();

	    Route::init();
	    AIBootstrap::init();

        if ( PluginHelper::isPluginActivated() )
        {
	        LicenseAdapter::fetchAndRunMigrationData();
            CronJob::init();
        }

        add_action( 'init', function ()
        {
            if ( is_admin() )
                new BackEnd();
            else
                new FrontEnd();
        } );

	    /**
	     * Ayri init-e salinmasinda sebeb, priority yuxari qoymagdiki, en sonda check edib header`i set etsin.
	     */
	    add_action( 'init', function ()
	    {
		    if ( is_admin() && Request::get('page', '', 'string') === FSP_PLUGIN_MENU_SLUG ) {
			    Helper::setCrossOriginOpenerPolicyHeaderIfNeed();
		    }
	    }, 9999999 );
    }

    private function registerDefines ()
    {
		define( 'FSP_PLUGIN_SLUG', 'fs-poster' );
		define( 'FSP_PLUGIN_MENU_SLUG', 'fs-poster' );
        define( 'FSP_ROOT_DIR', dirname( __DIR__, 3 ) );
        define( 'FSP_ROOT_DIR_URL', dirname( plugin_dir_url( __DIR__ ), 2 ) );
        define( 'FSP_API_URL', 'https://www.fs-poster.com/api/' );
    }

    private function loadPluginLinks ()
    {
        add_filter( 'plugin_action_links_fs-poster/init.php', function ( $links )
        {
            $newLinks = [
                '<a href="https://support.fs-code.com" target="_blank">' . fsp__( 'Support' ) . '</a>',
                '<a href="https://www.fs-poster.com/documentation/" target="_blank">' . fsp__( 'Documentation' ) . '</a>',
            ];

            return array_merge( $newLinks, $links );
        } );
    }

    private function loadPluginTextdomain ()
    {
        add_action( 'plugins_loaded', [LocalizationService::class, 'loadTextdomain']);
    }

    private function registerCustomPostTypesAndMetas ()
    {
        add_action( 'init', function ()
        {

            register_post_type( 'fsp_post', [
                'labels'      => [
                    'name'          => fsp__( 'FS Posts' ),
                    'singular_name' => fsp__( 'FS Post' ),
                ],
                'public'      => false,
                'has_archive' => true,
            ] );

        } );
    }

    private function createPostSaveEvent ()
    {
        add_action( 'delete_post', [ 'FSPoster\App\Providers\WPPost\WPPostService', 'deletePostSchedules' ] );

        add_action( 'save_post', [ 'FSPoster\App\Providers\WPPost\WPPostService', 'postSaved' ], 10, 3 );
        add_action( 'pre_post_update', [ 'FSPoster\App\Providers\WPPost\WPPostService', 'postPreUpdated' ], 10, 2 );
        add_action( 'post_updated', [ 'FSPoster\App\Providers\WPPost\WPPostService', 'postUpdated' ], 10, 3 );

		add_action( 'set_object_terms', [ 'FSPoster\App\Providers\WPPost\WPPostService', 'setObjectTerms' ], 10, 6 );
    }

    private function registerActivationHook ()
    {
        register_activation_hook( FSP_ROOT_DIR . '/init.php', function ()
        {
            if ( Settings::get( 'installed_version', '0', true ) )
            {
                $nowDateTime = Date::dateTimeSQL();
                Planner::where( 'status', 'active' )->where( 'share_type', 'interval' )->where( 'next_execute_at', '<=', $nowDateTime )->update( [
                    'next_execute_at' => DB::field( DB::raw( 'DATE_ADD(`next_execute_at`, INTERVAL ((TIMESTAMPDIFF(MINUTE, `next_execute_at`, %s) DIV (schedule_interval DIV 60) ) + 1) * (schedule_interval DIV 60) minute)', [ $nowDateTime ] ) ),
                ] );
            }
        } );
    }

	private function registerUserDeletedEvent ()
	{
		add_action( 'deleted_user', [ Helper::class, 'clearUserAllData' ] );
	}

	private function registerCallbackUrlHandler ()
	{
		add_action( 'init', [ CallbackUrlHandler::class, 'handleCallbackRequest' ] );
	}

}
