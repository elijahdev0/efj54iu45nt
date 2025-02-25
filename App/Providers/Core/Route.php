<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\App\Providers\Helpers\PluginHelper;
use WP_REST_Request;

class Route
{
    const API_VER = 'v1';

    public static function init ()
    {
        // General
        Route::post( 'plugin/fetch-statistics', [ \FSPoster\App\Pages\Base\Controller::class, 'fetchStatisticsOptions' ] );
        Route::post( 'plugin/activate', [ \FSPoster\App\Pages\Base\Controller::class, 'activateApp' ] );
        Route::post( 'plugin/remove-license', [ \FSPoster\App\Pages\Base\Controller::class, 'removeLicense' ] );
        Route::post( 'plugin/system-requirements', [ \FSPoster\App\Pages\Base\Controller::class, 'systemRequirements' ] );

        if ( PluginHelper::isPluginActivated() )
        {
	        // General
	        Route::get( 'taxonomies', [ \FSPoster\App\Pages\Base\Controller::class, 'getTaxonomies' ] );
	        Route::get( 'terms', [ \FSPoster\App\Pages\Base\Controller::class, 'getTerms' ] );
	        Route::get( 'post-types', [ \FSPoster\App\Pages\Base\Controller::class, 'getPostTypes' ] );
	        Route::get( 'fonts', [ \FSPoster\App\Pages\Base\Controller::class, 'listFonts' ] );

	        //Metabox
	        Route::get( 'metabox', [ \FSPoster\App\Pages\Metabox\Controller::class, 'get' ] );
	        Route::post( 'metabox', [ \FSPoster\App\Pages\Metabox\Controller::class, 'save' ] );
	        Route::post( 'metabox/auto-share-status', [ \FSPoster\App\Pages\Metabox\Controller::class, 'setAutoShareStatus' ] );

	        //Composer
	        Route::post( 'composer', [ \FSPoster\App\Pages\Composer\Controller::class, 'save' ] );

	        // Schedules
	        Route::get( "schedules", [ \FSPoster\App\Pages\Schedules\Controller::class, 'list' ] );
	        Route::get( "schedules/calendar", [ \FSPoster\App\Pages\Schedules\Controller::class, 'calendar' ] );
	        Route::delete( "schedules", [ \FSPoster\App\Pages\Schedules\Controller::class, 'delete' ] );
	        Route::get( "schedules/export", [ \FSPoster\App\Pages\Schedules\Controller::class, 'export' ] );
	        Route::post( "schedules/post", [ \FSPoster\App\Pages\Schedules\Controller::class, 'newPost' ] );
	        Route::post( 'schedules/retry', [ \FSPoster\App\Pages\Schedules\Controller::class, 'retry' ] );
	        Route::get( 'schedules/insights', [ \FSPoster\App\Pages\Schedules\Controller::class, 'getInsights' ] );

	        // Planners
	        Route::post( 'planners', [ \FSPoster\App\Pages\Planners\Controller::class, 'save' ] );
	        Route::get( 'planners/(?P<id>\d+)', [ \FSPoster\App\Pages\Planners\Controller::class, 'get' ] );
	        Route::get( 'planners', [ \FSPoster\App\Pages\Planners\Controller::class, 'list' ] );
	        Route::delete( 'planners', [ \FSPoster\App\Pages\Planners\Controller::class, 'delete' ] );
	        Route::post( 'planners/get-selected-posts-data', [ \FSPoster\App\Pages\Planners\Controller::class, 'getSelectedPostsData' ] );
	        Route::post( 'planners/posts/get', [ \FSPoster\App\Pages\Planners\Controller::class, 'getPosts' ] );
	        Route::post( 'planners/change-status', [ \FSPoster\App\Pages\Planners\Controller::class, 'changeStatus' ] );

	        // Social channels
	        Route::get( 'channels', [ \FSPoster\App\Pages\Channels\Controller::class, 'list' ] );
	        Route::post( 'channels', [ \FSPoster\App\Pages\Channels\Controller::class, 'save' ] );
	        Route::post( 'channels/refresh', [ \FSPoster\App\Pages\Channels\Controller::class, 'refresh' ] );
	        Route::delete( 'channels', [ \FSPoster\App\Pages\Channels\Controller::class, 'delete' ] );
	        Route::get( 'channels/settings', [ \FSPoster\App\Pages\Channels\Controller::class, 'getSettings' ] );
	        Route::post( 'channels/settings', [ \FSPoster\App\Pages\Channels\Controller::class, 'saveSettings' ] );

	        Route::get( 'channels/labels', [ \FSPoster\App\Pages\Channels\LabelsController::class, 'get' ] );
	        Route::post( 'channels/labels', [ \FSPoster\App\Pages\Channels\LabelsController::class, 'create' ] );
	        Route::put( 'channels/labels', [ \FSPoster\App\Pages\Channels\LabelsController::class, 'edit' ] );
	        Route::delete( 'channels/labels', [ \FSPoster\App\Pages\Channels\LabelsController::class, 'delete' ] );

	        Route::post( 'channels/get-auth-url', [ \FSPoster\App\Pages\Channels\Controller::class, 'getAuthUrl' ] );

	        // Analytics
	        Route::get( 'analytics/stats', [ \FSPoster\App\Pages\Analytics\Controller::class, 'getStats' ] );

	        //settings
	        Route::get( 'settings/general', [ \FSPoster\App\Pages\Settings\Controller::class, 'getGeneralSettings' ] );
	        Route::post( 'settings/general', [ \FSPoster\App\Pages\Settings\Controller::class, 'saveGeneralSettings' ] );

	        Route::get( 'settings/auto-share', [ \FSPoster\App\Pages\Settings\Controller::class, 'getAutoShareSettings' ] );
	        Route::post( 'settings/auto-share', [ \FSPoster\App\Pages\Settings\Controller::class, 'saveAutoShareSettings' ] );

	        Route::get( 'settings/apps', [ \FSPoster\App\Pages\Settings\AppController::class, 'list' ] );
	        Route::post( 'settings/apps', [ \FSPoster\App\Pages\Settings\AppController::class, 'save' ] );
	        Route::delete( 'settings/apps', [ \FSPoster\App\Pages\Settings\AppController::class, 'delete' ] );

	        Route::get( 'settings/ai', [ \FSPoster\App\Pages\Settings\Controller::class, 'getAISettings' ] );
	        Route::post( 'settings/ai', [ \FSPoster\App\Pages\Settings\Controller::class, 'saveAISettings' ] );

	        Route::post( 'settings/advanced/get', [ \FSPoster\App\Pages\Settings\Controller::class, 'getAdvancedSettings' ] );

            Route::get( 'settings/logger', [ \FSPoster\App\Pages\Settings\Controller::class, 'getLoggerStatus' ] );
	        Route::post( 'settings/logger/start', [ \FSPoster\App\Pages\Settings\Controller::class, 'startLogger' ] );
	        Route::post( 'settings/logger/stop', [ \FSPoster\App\Pages\Settings\Controller::class, 'stopLogger' ] );

	        Route::post( 'settings/advanced/export', [ \FSPoster\App\Pages\Settings\Controller::class, 'exportPlugin' ] );
	        Route::post( 'settings/advanced/import', [ \FSPoster\App\Pages\Settings\Controller::class, 'importPlugin' ] );
        }
    }

    public static function get ( $route, $fn, $args = [] )
    {
        self::addRoute( 'GET', $route, $fn, $args );
    }

    public static function post ( $route, $fn, $args = [] )
    {
        self::addRoute( 'POST', $route, $fn, $args );
    }

    public static function put ( $route, $fn, $args = [] )
    {
        self::addRoute( 'PUT', $route, $fn, $args );
    }

    public static function delete ( $route, $fn, $args = [] )
    {
        self::addRoute( 'DELETE', $route, $fn, $args );
    }

    private static function getNamespace (): string
    {
        return 'fs-poster/' . self::API_VER;
    }

    private static function addRoute ( $method, $route, $fn, $args )
    {
        add_action( 'rest_api_init', function () use ( $method, $route, $fn, $args )
        {
            register_rest_route( self::getNamespace(), $route, [
                'methods'             => $method,
                'callback'            => function ( WP_REST_Request $request ) use ( $fn )
                {
                    try
                    {
                        $restRequest = new RestRequest( $request );
                        $res         = $fn( $restRequest );

                        return is_array( $res ) ? $res : [ 'error_msg' => fsp__( 'Error' ) ];
                    } catch ( \Exception $e )
                    {
                        return [ 'error_msg' => $e->getMessage() ];
                    }
                },
                'args'                => $args,
                'permission_callback' => function ()
                {
                    return current_user_can( 'read' );
                },
            ] );
        } );
    }

}
