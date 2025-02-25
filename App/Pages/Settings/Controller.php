<?php

namespace FSPoster\App\Pages\Settings;

use Exception;
use FSPoster\App\Models\AILogs;
use FSPoster\App\Models\AITemplate;
use FSPoster\App\Models\App;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelLabel;
use FSPoster\App\Models\ChannelLabelsData;
use FSPoster\App\Models\ChannelPermission;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\DB\Model;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\Logger;
use FSPoster\App\Providers\Helpers\PluginHelper;

class Controller
{
    public static function saveGeneralSettings ( RestRequest $request ): array
    {
        if( ! PluginHelper::canAccessToSettings() )
			return [];

        $disableVirtualCron = (int)$request->param( 'virtual_cron_job_disabled', false, RestRequest::TYPE_BOOL );
        $showPluginTo       = $request->param( 'show_fs_poster_to', [], RestRequest::TYPE_ARRAY, array_keys( wp_roles()->role_names ) );
        $allowedPostTypes = $request->param( 'allowed_post_types', [], RestRequest::TYPE_ARRAY, array_keys( get_post_types() ) );
        
        Settings::set( 'allowed_post_types', $allowedPostTypes );
        Settings::set( 'show_fs_poster_to', $showPluginTo );
        Settings::set( 'virtual_cron_job_disabled', $disableVirtualCron );

        return [];
    }

    public static function getGeneralSettings ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    $response = [];

        $response['allowed_post_types']          = Settings::get( 'allowed_post_types', [ 'post', 'page', 'attachment', 'product' ] );
        $response['allowed_post_types_options']  = Helper::getPostTypes();
        $response[ 'virtual_cron_job_disabled' ] = (bool)Settings::get( 'virtual_cron_job_disabled', false );
        $showFsPosterTo                          = Settings::get( 'show_fs_poster_to', [] );

        $roles = wp_roles();

        $response[ 'show_fs_poster_to' ]         = [];
        $response[ 'show_fs_poster_to_options' ] = [];

        foreach ( $roles->role_names as $role => $roleName )
        {
            $response[ 'show_fs_poster_to_options' ][] = [
                'label' => $roleName,
                'value' => $role,
            ];

            if ( in_array( $role, $showFsPosterTo ) )
            {
                $response[ 'show_fs_poster_to' ][] = $role;
            }
        }

		$lastRunSecAgo = Helper::secFormat( Date::epoch() - (int)Settings::getWithRawQuery( 'cron_job_run_on', 0 ) );

        $response[ 'cronjob' ] = [
            'last_run'  => fsp__('%s ago', [ $lastRunSecAgo ]),
            'command'   => 'wget -O /dev/null ' . site_url() . '/wp-cron.php?doing_wp_cron > /dev/null 2>&1',
        ];

        return $response;
    }

    public static function startLogger ( RestRequest $request ): array
    {
        if( ! PluginHelper::canAccessToSettings() )
			return [];

        if ( PluginHelper::isDemoVersion() )
		    throw new Exception( fsp__('This action is not allowed in the demo version.') );

        $startedAt = Date::epoch();

        Settings::set( 'logger_started_at', $startedAt );

        return [
            'started_at' => $startedAt,
        ];
    }

    public static function getLoggerStatus ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

        return [
            'started_at' => (int)Settings::get( 'logger_started_at', 0 ),
        ];
    }

    /**
     * @throws Exception
     */
    public static function stopLogger ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    if ( PluginHelper::isDemoVersion() )
		    throw new Exception( fsp__('This action is not allowed in the demo version.') );

        $content = Logger::getContent();

        Settings::delete( 'logger_started_at');
        Logger::delete();

        return [
            'file'     => 'data:application/json;base64,' . base64_encode( $content ),
            'filename' => 'FS-Poster-logs-' . time() . '.json',
        ];
    }

    /**
     * @throws Exception
     */
    public static function saveAutoShareSettings ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    $autoShare        = (int)$request->param( 'auto_share', false, RestRequest::TYPE_BOOL );

        $multipleNewlinesToSingle = (int)$request->param( 'multiple_newlines_to_single', false, RestRequest::TYPE_BOOL );
        $replaceWpShortcodes      = $request->param( 'replace_wp_shortcodes', 'off', RestRequest::TYPE_STRING, [ 'on', 'off', 'del' ] );

        $enableShareTimer = (int)$request->param( 'enable_auto_share_delay', false, RestRequest::TYPE_BOOL );
        $shareDelay       = $request->param( 'auto_share_delay', [
            'value' => 0,
            'unit'  => 'second',
        ], RestRequest::TYPE_ARRAY );

        $enablePostInterval = (int)$request->param( 'enable_post_interval', false, RestRequest::TYPE_BOOL );
        $postInterval       = $request->param( 'post_interval', [
            'value' => 0,
            'unit'  => 'second',
        ], RestRequest::TYPE_ARRAY );

        $useCustomUrl = (int)$request->param( 'use_custom_url', false, RestRequest::TYPE_BOOL );
        $customUrl    = $request->param( 'custom_url', '', RestRequest::TYPE_STRING );
        $queryParams  = $request->param( 'query_params', [], RestRequest::TYPE_ARRAY );

        $useUrlShortener  = (int)$request->param( 'use_url_shortener', false, RestRequest::TYPE_BOOL );
        $shortenerService = $request->param( 'shortener_service', '', RestRequest::TYPE_STRING, [ 'tinyurl', 'bitly', 'yourls', 'polr', 'shlink', 'rebrandly' ] );

        $urlShortAccessTokenBitly = $request->param( 'url_short_access_token_bitly', '', RestRequest::TYPE_STRING );

        $urlShortApiUrlYourls   = $request->param( 'url_short_api_url_yourls', '', RestRequest::TYPE_STRING );
        $urlShortApiTokenYourls = $request->param( 'url_short_api_token_yourls', '', RestRequest::TYPE_STRING );

        $urlShortApiUrlPolr = $request->param( 'url_short_api_url_polr', '', RestRequest::TYPE_STRING );
        $urlShortApiKeyPolr = $request->param( 'url_short_api_key_polr', '', RestRequest::TYPE_STRING );

        $urlShortApiUrlShlink = $request->param( 'url_short_api_url_shlink', '', RestRequest::TYPE_STRING );
        $urlShortApiKeyShlink = $request->param( 'url_short_api_key_shlink', '', RestRequest::TYPE_STRING );

        $urlShortDomainRebrandly = $request->param( 'url_short_domain_rebrandly', '', RestRequest::TYPE_STRING );
        $urlShortApiKeyRebrandly = $request->param( 'url_short_api_key_rebrandly', '', RestRequest::TYPE_STRING );

        $enableOgTags      = $request->param( 'enable_og_tags', false, RestRequest::TYPE_BOOL );
        $enableTwitterTags = $request->param( 'enable_twitter_tags', false, RestRequest::TYPE_BOOL );
        $addMetaTagsTo     = $request->param( 'add_meta_tags_to', [], RestRequest::TYPE_ARRAY, array_keys( get_post_types() ) );


        if ( $useCustomUrl && empty( $customUrl ) )
        {
            throw new Exception( fsp__( 'Please, enter custom url to enable sharing custom URLs' ) );
        }

        if ( !isset( $shareDelay[ 'value' ], $shareDelay[ 'unit' ] ) || ( $enableShareTimer && !( $shareDelay[ 'value' ] > 0 ) ) )
        {
            throw new Exception( fsp__( 'Please, enter share timer value' ) );
        }

        if ( !isset( $postInterval[ 'value' ], $postInterval[ 'unit' ] ) || ( $enablePostInterval && !( $postInterval[ 'value' ] > 0 ) ) )
        {
            throw new Exception( fsp__( 'Please, enter post interval' ) );
        }

        if ( $useUrlShortener )
        {
            if (
				empty( $shortenerService ) ||
                ( $shortenerService === 'bitly' && empty( $urlShortAccessTokenBitly ) ) ||
                ( $shortenerService === 'yourls' && ( empty( $urlShortApiUrlYourls ) || empty( $urlShortApiTokenYourls ) ) ) ||
                ( $shortenerService === 'polr' && ( empty( $urlShortApiUrlPolr ) || empty( $urlShortApiKeyPolr ) ) ) ||
                ( $shortenerService === 'shlink' && ( empty( $urlShortApiUrlShlink ) || empty( $urlShortApiKeyShlink ) ) ) ||
                ( $shortenerService === 'rebrandly' && ( empty( $urlShortDomainRebrandly ) || empty( $urlShortApiKeyRebrandly ) ) )
            )
            {
                throw new Exception( fsp__( 'Please provide the URL shortener service credentials' ) );
            }
        }

        if ( ( $enableOgTags || $enableTwitterTags ) && empty( $addMetaTagsTo ) )
        {
            throw new Exception( fsp__( 'Please, enter post types to enable using meta tags' ) );
        }

        foreach ( $queryParams as $k => $v )
        {
            if ( !is_string( $k ) || !is_string( $v ) )
            {
                throw new Exception( fsp__( 'Query params must type of string' ) );
            }

            if ( empty( $k ) )
            {
                throw new Exception( fsp__( 'Query parameters cannot contain empty keys' ) );
            }
        }

        Settings::set( 'auto_share', $autoShare );
        Settings::set( 'multiple_newlines_to_single', $multipleNewlinesToSingle );
        Settings::set( 'replace_wp_shortcodes', $replaceWpShortcodes );
        Settings::set( 'auto_share_delay', ! $enableShareTimer ? 0 : Date::convertFromUnitToSeconds( intval( $shareDelay[ 'value' ] ), $shareDelay[ 'unit' ] ) );
        Settings::set( 'enable_post_interval', $enablePostInterval );
        Settings::set( 'post_interval', Date::convertFromUnitToSeconds( $postInterval[ 'value' ], $postInterval[ 'unit' ] ) );
        Settings::set( 'use_custom_url', $useCustomUrl );
        Settings::set( 'custom_url', $customUrl );
        Settings::set( 'query_params', $queryParams );
        Settings::set( 'use_url_shortener', $useUrlShortener );
        Settings::set( 'shortener_service', $shortenerService );
        Settings::set( 'url_short_access_token_bitly', $urlShortAccessTokenBitly );
        Settings::set( 'url_short_api_url_yourls', $urlShortApiUrlYourls );
        Settings::set( 'url_short_api_token_yourls', $urlShortApiTokenYourls );
        Settings::set( 'url_short_api_url_polr', $urlShortApiUrlPolr );
        Settings::set( 'url_short_api_key_polr', $urlShortApiKeyPolr );
        Settings::set( 'url_short_api_url_shlink', $urlShortApiUrlShlink );
        Settings::set( 'url_short_api_key_shlink', $urlShortApiKeyShlink );
        Settings::set( 'url_short_domain_rebrandly', $urlShortDomainRebrandly );
        Settings::set( 'url_short_api_key_rebrandly', $urlShortApiKeyRebrandly );
        Settings::set( 'enable_og_tags', $enableOgTags );
        Settings::set( 'enable_twitter_tags', $enableTwitterTags );
        Settings::set( 'add_meta_tags_to', $addMetaTagsTo );

        return [];
    }

    public static function getAutoShareSettings ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    return [
            'auto_share'                   => (bool)Settings::get( 'auto_share', true ),
            'allowed_post_types_options'   => Helper::getPostTypes(),
            'multiple_newlines_to_single'  => (bool)Settings::get( 'multiple_newlines_to_single', false ),
            'replace_wp_shortcodes'        => Settings::get( 'replace_wp_shortcodes', 'off' ),
            'enable_auto_share_delay'      => (int)Settings::get( 'auto_share_delay', 0 ) > 0,
            'auto_share_delay'             => Date::convertFromSecondsToUnit( (int)Settings::get( 'auto_share_delay', 0 ) ),
            'enable_post_interval'         => (bool)Settings::get( 'enable_post_interval', false ),
            'post_interval'                => Date::convertFromSecondsToUnit( (int)Settings::get( 'post_interval', 0 ) ),
            'use_custom_url'               => (bool)Settings::get( 'use_custom_url', false ),
            'custom_url'                   => Settings::get( 'custom_url', '{post_url type="original"}' ),
            'query_params'                 => Settings::get( 'query_params', ['fsp_sid' => '{schedule_id}'] ),
            'use_url_shortener'            => (bool)Settings::get( 'use_url_shortener', false ),
            'shortener_service'            => Settings::get( 'shortener_service', '' ),
            'shortener_services'           => [
                [
                    'label' => 'Tinyurl',
                    'value' => 'tinyurl',
                ],
                [
                    'label' => 'Bitly',
                    'value' => 'bitly',
                ],
                [
                    'label' => 'Yourls',
                    'value' => 'yourls',
                ],
                [
                    'label' => 'Polr',
                    'value' => 'polr',
                ],
                [
                    'label' => 'Shlink',
                    'value' => 'shlink',
                ],
                [
                    'label' => 'Rebrandly',
                    'value' => 'rebrandly',
                ],
            ],
            'url_short_access_token_bitly' => Settings::get( 'url_short_access_token_bitly', '' ),
            'url_short_api_url_yourls'     => Settings::get( 'url_short_api_url_yourls', '' ),
            'url_short_api_token_yourls'   => Settings::get( 'url_short_api_token_yourls', '' ),
            'url_short_api_url_polr'       => Settings::get( 'url_short_api_url_polr', '' ),
            'url_short_api_key_polr'       => Settings::get( 'url_short_api_key_polr', '' ),
            'url_short_api_url_shlink'     => Settings::get( 'url_short_api_url_shlink', '' ),
            'url_short_api_key_shlink'     => Settings::get( 'url_short_api_key_shlink', '' ),
            'url_short_domain_rebrandly'   => Settings::get( 'url_short_domain_rebrandly', '' ),
            'url_short_api_key_rebrandly'  => Settings::get( 'url_short_api_key_rebrandly', '' ),
            'enable_og_tags'               => (bool)Settings::get( 'enable_og_tags', false ),
            'enable_twitter_tags'          => (bool)Settings::get( 'enable_twitter_tags', false ),
            'add_meta_tags_to'             => Settings::get( 'add_meta_tags_to', [] ),
        ];
    }

    public static function getAdvancedSettings ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

		$licenseCode = Settings::get( 'license_code', '', true );
	    if( ! empty( $licenseCode ) )
		    $licenseCode = substr( $licenseCode, 0, 4 ) . '***' . substr( $licenseCode, -4 );

	    return [
            'activated_at'      => (int)Settings::get( 'license_activated_at', 0, true ),
            'purchase_code'     => $licenseCode,
		    'plugin_version'    => PluginHelper::getVersion()
        ];
    }

    /**
     * @throws Exception
     */
    public static function exportPlugin ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    if ( PluginHelper::isDemoVersion() )
		    throw new Exception( fsp__('This action is not allowed in the demo version.') );

        $includeChannels             = $request->param( 'include_channels', true, RestRequest::TYPE_BOOL );
        $excludeDisconnectedChannels = $request->param( 'exclude_disconnected_channels', true, RestRequest::TYPE_BOOL );
        $includeLabels               = $request->param( 'include_labels', true, RestRequest::TYPE_BOOL );
        $includeApps                 = $request->param( 'include_apps', true, RestRequest::TYPE_BOOL );
        $includeSchedules            = $request->param( 'include_schedules', true, RestRequest::TYPE_BOOL );
        $includePlanners             = $request->param( 'include_planners', true, RestRequest::TYPE_BOOL );
        $includeSettings             = $request->param( 'include_settings', true, RestRequest::TYPE_BOOL );
        $includeAITemplates          = $request->param( 'include_ai_templates', true, RestRequest::TYPE_BOOL );
        $includeAILogs               = $request->param( 'include_ai_logs', true, RestRequest::TYPE_BOOL );

        if ( !$includeChannels && $includePlanners )
        {
            throw new Exception( fsp__( 'You cannot export planners without exporting channels' ) );
        }

        if ( !$includeChannels && $includeSchedules )
        {
            throw new Exception( fsp__( 'You cannot export schedules without exporting channels' ) );
        }

        Settings::set( 'include_channels', (int)$includeChannels );
        Settings::set( 'exclude_disconnected_channels', (int)$excludeDisconnectedChannels );
        Settings::set( 'include_labels', (int)$includeLabels );
        Settings::set( 'include_apps', (int)$includeApps );
        Settings::set( 'include_schedules', (int)$includeSchedules );
        Settings::set( 'include_planners', (int)$includePlanners );
        Settings::set( 'include_settings', (int)$includeSettings );

        $exportedChannels        = null;
        $exportedChannelSessions = null;

        if ( $includeChannels )
        {
            $channels = Channel::withoutGlobalScope( 'blog' )->withoutGlobalScope( 'my_channels' );

            if ( $excludeDisconnectedChannels )
                $channels->where( 'status', 1 );

            $exportedChannels = $channels->fetchAll();

            if ( $exportedChannels )
            {
                $exportedChannelSessions = ChannelSession::withoutGlobalScope( 'blog' )
                    ->where( 'id', 'in', array_column( $exportedChannels, 'channel_session_id' ) )
                    ->fetchAll();
            }
        }

        $exportedChannelPermissions = null;

        if ( $exportedChannels )
        {
            $exportedChannelPermissions = ChannelPermission::where( 'channel_id', 'in', array_column( $exportedChannels, 'id' ) );
        }

        $exportedLabels = null;

        if ( $includeLabels )
        {
            $exportedLabels = ChannelLabel::withoutGlobalScope( 'blog' )
                ->withoutGlobalScope( 'my_labels' )->fetchAll();
        }

        $exportedChannelLabelsData = null;

        if ( $exportedChannels && $exportedLabels )
        {
            $exportedChannelLabelsData = ChannelLabelsData::fetchAll();
        }

        $exportedApps = null;

        $hasChannelsWithApp = $exportedChannelSessions
            ? ChannelSession::withoutGlobalScope( 'blog' )
                ->where( 'id', array_column( $exportedChannelSessions, 'id' ) )
                ->where( 'method', 'app' )
                ->fetchAll()
            : false;

        if ( $includeApps || $hasChannelsWithApp )
            $exportedApps = App::withoutGlobalScope( 'blog' )->fetchAll();

        $exportedPlanners = null;

        if ( $includePlanners && $exportedChannels )
            $exportedPlanners = Planner::withoutGlobalScope( 'blog' )->withoutGlobalScope( 'my_planners' )->fetchAll();

        $exportedSchedules = null;

        if ( $includeSchedules && $exportedChannels )
            $exportedSchedules = Schedule::withoutGlobalScope( 'blog' )->withoutGlobalScope( 'my_schedules' )->fetchAll();

        $exportedSettings = [];

        if ( $includeSettings )
        {
            $blogs = Helper::getBlogs();
            foreach ( $blogs as $blog )
            {
                Helper::setBlogId( $blog );

                $options = DB::DB()->get_results( 'SELECT `option_name`, `option_value`, `autoload` FROM ' . DB::WPtable( 'options' ) . ' WHERE `option_name` LIKE "fsp_%" AND `option_name` NOT IN ( "fsp_license_code", "fsp_license_type", "fsp_license_access_token", "fsp_license_activated_at", "fsp_installed_version" )', ARRAY_A );

                if ( $options )
                    $exportedSettings[ $blog ] = $options;

                Helper::resetBlogId();
            }
        }

        $exportedAITemplates = [];

        if ( $includeAITemplates )
            $exportedAITemplates = AITemplate::fetchAll() ?: [];

        $exportedAILogs = [];

        if ( $includeAILogs )
            $exportedAILogs = AILogs::fetchAll() ?: [];

        $exported = [
            'channels'            => $exportedChannels ?: [],
            'channel_sessions'    => $exportedChannelSessions ?: [],
            'channel_permissions' => $exportedChannelPermissions ?: [],
            'channel_labels'      => $exportedLabels ?: [],
            'channel_labels_data' => $exportedChannelLabelsData ?: [],
            'apps'                => $exportedApps ?: [],
            'planners'            => $exportedPlanners ?: [],
            'schedules'           => $exportedSchedules ?: [],
            'settings'            => $exportedSettings,
            'ai_templates'        => $exportedAITemplates,
            'ai_logs'             => $exportedAILogs,
        ];

        return [
            'file'     => 'data:application/json;base64,' . base64_encode( json_encode( [
                    'data'     => $exported,
                    'metadata' => [
                        'include_channels'              => $includeChannels,
                        'channels_count'                => count( $exported[ 'channels' ] ),
                        'exclude_disconnected_channels' => $excludeDisconnectedChannels,
                        'disconnected_channels_count'   => Channel::withoutGlobalScope( 'my_channels' )->withoutGlobalScope( 'blog' )->where( 'status', 0 )->count(),
                        'include_labels'                => $includeLabels,
                        'labels_count'                  => count( $exported[ 'channel_labels' ] ),
                        'include_apps'                  => $includeApps,
                        'apps_count'                    => count( $exported[ 'apps' ] ),
                        'include_schedules'             => $includeSchedules,
                        'schedules_count'               => count( $exported[ 'schedules' ] ),
                        'include_planners'              => $includePlanners,
                        'planners_count'                => count( $exported[ 'planners' ] ),
                        'include_settings'              => $includeSettings,
                        'include_ai_templates'          => $includeAITemplates,
                        'include_ai_logs'               => $includeAILogs,
                    ],
                    'version'  => PluginHelper::getVersion(),
                ] ) ),
            'filename' => 'FS-Poster-exported-' . time() . '.json',
        ];
    }

    /**
     * @throws Exception
     */
    public static function importPlugin ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    if ( PluginHelper::isDemoVersion() )
		    throw new Exception( fsp__('This action is not allowed in the demo version.') );

	    $includeChannels             = $request->param( 'include_channels', true, RestRequest::TYPE_BOOL );
        $excludeDisconnectedChannels = $request->param( 'exclude_disconnected_channels', true, RestRequest::TYPE_BOOL );
        $includeLabels               = $request->param( 'include_labels', true, RestRequest::TYPE_BOOL );
        $includeApps                 = $request->param( 'include_apps', true, RestRequest::TYPE_BOOL );
        $includeSchedules            = $request->param( 'include_schedules', true, RestRequest::TYPE_BOOL );
        $includePlanners             = $request->param( 'include_planners', true, RestRequest::TYPE_BOOL );
        $includeSettings             = $request->param( 'include_settings', true, RestRequest::TYPE_BOOL );
        $includeAITemplates          = $request->param( 'include_ai_templates', true, RestRequest::TYPE_BOOL );
        $includeAILogs               = $request->param( 'include_ai_logs', true, RestRequest::TYPE_BOOL );
        $data                        = $request->param( 'file', true, RestRequest::TYPE_ARRAY );
        $version                     = $request->param( 'version', true, RestRequest::TYPE_STRING );

        if ( empty( $data ) )
        {
            throw new Exception( fsp__( 'No valid import file is selected!' ) );
        }

        $isVersionCompatible = version_compare($version, '7.0.0', '>=') && version_compare($version, '8.0.0', '<');
        if ( ! $isVersionCompatible )
        {
            throw new Exception( fsp__( 'Import file version doesn\'t match plugin version' ) );
        }

        $models = [
            'channels'            => Channel::class,
            'channel_sessions'    => ChannelSession::class,
            'channel_permissions' => ChannelPermission::class,
            'channel_labels'      => ChannelLabel::class,
            'channel_labels_data' => ChannelLabelsData::class,
            'apps'                => App::class,
            'planners'            => Planner::class,
            'schedules'           => Schedule::class,
            'ai_templates'        => AITemplate::class,
            'ai_logs'             => AILogs::class,
        ];

        $allow = [
            'channels'            => $includeChannels,
            'channel_sessions'    => $includeChannels,
            'channel_permissions' => $includeChannels,
            'channel_labels'      => $includeChannels && $includeLabels,
            'channel_labels_data' => $includeChannels && $includeLabels,
            'apps'                => $includeApps,
            'planners'            => $includePlanners && $includeChannels,
            'schedules'           => $includeChannels && $includePlanners && $includeSchedules,
            'ai_logs'             => $includeAILogs,
            'ai_templates'        => $includeAITemplates,
        ];

        if ( !$includeChannels )
        {
            if ( $includePlanners )
            {
                throw new Exception( fsp__( 'Planners cannot be exported without importing channels' ) );
            }

            if ( $includeLabels )
            {
                throw new Exception( fsp__( 'Channel labels cannot be exported without importing channels' ) );
            }

            if ( $includeSchedules )
            {
                throw new Exception( fsp__( 'Schedules cannot be exported without importing channels' ) );
            }
        }

        if ( $excludeDisconnectedChannels )
        {
            Channel::where( 'status', 0 )->delete();
        }

        if ( !$includePlanners )
        {
            if ( $includeSchedules )
            {
                throw new Exception( fsp__( 'Schedules cannot be exported without importing schedules' ) );
            }
        }

        DB::DB()->query( 'SET FOREIGN_KEY_CHECKS = 0;' );

        /**
         * @var string $key
         * @var Model  $model
         */
        foreach ( $models as $key => $model )
        {
            DB::DB()->query( 'TRUNCATE TABLE ' . DB::table( $model::getTableName() ) );

            if ( empty( $data[ $key ] ) || empty( $allow[ $key ] ) )
            {
                continue;
            }

            foreach ( $data[ $key ] as $row )
            {
                $model::insert( $row );
            }
        }

        if ( !empty( $data[ 'settings' ] ) && $includeSettings )
        {
            $blogs = Helper::getBlogs();

            foreach ( $data[ 'settings' ] as $blog => $options )
            {
                if ( !in_array( $blog, $blogs ) )
                {
                    continue;
                }

                Helper::setBlogId( $blog );
                DB::DB()->query( 'DELETE FROM ' . DB::WPtable( 'options' ) . ' WHERE `option_name` LIKE "fsp_%" AND `option_name` NOT IN ( "fsp_license_code", "fsp_license_type", "license_access_token", "fsp_license_activated_at", "fsp_installed_version" )' );

                foreach ( $options as $option )
                {
                    DB::DB()->insert( DB::WPtable( 'options' ), $option );
                }

                Helper::resetBlogId();
            }
        }

        DB::DB()->query( "SET FOREIGN_KEY_CHECKS = 1;" );

        return [];
    }

    /**
     * @throws Exception
     */
    public static function saveAISettings ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    $key        = $request->require( 'openai_key', RestRequest::TYPE_STRING, fsp__( 'Please enter OpenAI API key' ) );
	    $keyChanged = $request->param( 'openai_key_changed', false, RestRequest::TYPE_BOOL );

		if( $keyChanged )
            Settings::set( 'openai_key', $key );

        return [];
    }

    public static function getAISettings ( RestRequest $request ): array
    {
	    if( ! PluginHelper::canAccessToSettings() )
		    return [];

	    $openAIKey = Settings::get( 'openai_key', '' );
		if( ! empty( $openAIKey ) )
			$openAIKey = substr( $openAIKey, 0, 10 ) . '***' . substr( $openAIKey, -10 );

		return [
            "openai_key" => $openAIKey
        ];
    }
}
