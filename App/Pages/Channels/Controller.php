<?php

namespace FSPoster\App\Pages\Channels;

use Exception;
use FSPoster\App\Models\App;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelLabel;
use FSPoster\App\Models\ChannelLabelsData;
use FSPoster\App\Models\ChannelPermission;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\PluginHelper;
use FSPoster\App\Providers\Helpers\Session;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Controller
{

    public static function list ( RestRequest $request ): array
    {
        $channelSessions = ChannelSession::where( 'id', 'in', Channel::select( 'DISTINCT channel_session_id', true ) )->fetchAll();

        $channelSessionsIdToInfoMap = [];

        foreach ( $channelSessions as $channelSession )
        {
            $channelSessionsIdToInfoMap[ $channelSession->id ] = $channelSession;
        }

        unset( $channelSessions );

        $channels = Channel::fetchAll();

        $list = [];
        foreach ( $channels as $channel )
        {
            $labels = ChannelLabel::where( 'id', 'in', ChannelLabelsData::where( 'channel_id', $channel->id )->select( 'DISTINCT label_id', true ) )
                                  ->select( [ 'id', 'name', 'color' ] )
                                  ->fetchAll();

            $labels = array_map( fn ( $label ) => [
                'value' => (int)$label->id,
                'label' => $label->name,
                'color' => $label->color,
            ], $labels );

            $channelSession      = $channelSessionsIdToInfoMap[ $channel->channel_session_id ];
            $savedCustomSettings = json_decode( $channel->custom_settings ?? '[]', true );

            $list[] = apply_filters( 'fsp_get_channel', [
                'id'                        => (int)$channel->id,
                'name'                      => !empty( $channel->name ) ? $channel->name : "[no name]",
                'social_network'            => $channelSession->social_network,
                'picture'                   => $channel->picture,
                'channel_type'              => $channel->channel_type,
                'channel_link'              => apply_filters( 'fsp_get_channel_link', '', $channelSession->social_network, $channel ),
                'auto_share_on'             => $channel->auto_share === '1',
                'has_auto_share_conditions' => $channel->auto_share === '1' && !empty( $savedCustomSettings[ 'post_filter_terms' ] ) && !empty( $savedCustomSettings[ 'post_filter_type' ] ) && $savedCustomSettings[ 'post_filter_type' ] !== 'all',
                'status'                    => $channel->status === '1',
                'method'                    => $channelSession->method,
                'labels'                    => $labels,
                'custom_post_data'          => apply_filters( 'fsp_channel_custom_post_data', [], $channel, $channelSession->social_network ),
	            'has_full_permission'       => $channel->hasPermission('full_access'),
	            'created_by'                => $channelSession->created_by,
	            'proxy'                     => $channelSession->proxy,
                /**
                 * Session data fieldi cookie methodla elave olunmush channellarda front-a cookie melumatlarini gondermek ucun istifade olunur
                 * Default nulldir, cunki ancaq bezi social networkler (mes., cookie only Xing) ve cookie ile elave olunmush channellar (mes., Facebook cookie method) ucun bu field gonderilmelidir
                 */
                'session_data'              => apply_filters('fsp_get_channel_session_data', null, $channelSession->social_network, $channelSession, $channel),
            ], $channelSession->social_network, $channel, $channelSession );
        }

        return [ 'channels' => $list ];
    }

    /**
     * @throws Exception
     */
    public static function getAuthUrl ( RestRequest $request ): array
    {
        $appId         = $request->param( 'app_id', 0, RestRequest::TYPE_INTEGER );
        $socialNetwork = $request->require( 'social_network', RestRequest::TYPE_STRING, fsp__( 'social_network is required' ), array_keys( SocialNetworkAddon::getSocialNetworks() ) );

        $proxy = $request->param( 'proxy' );

        Session::set( 'app_id', $appId );
        Session::set( 'proxy', $proxy );

        if ( empty( $appId ) )
        {
	        $fsPurchaseKey = Settings::get( 'license_code', '', true );

	        $authUrl = FSP_API_URL .
	                   '?purchase_code=' . urlencode( $fsPurchaseKey ) .
	                   '&domain=' . urlencode( network_site_url() ) .
	                   '&sn=' . urlencode( $socialNetwork ) .
	                   '&proxy=' . urlencode( $proxy ) .
	                   '&r_url=' . urlencode( site_url() . '/?fsp_app_redirect=1&sn=' . $socialNetwork ) .
	                   '&plugin_version=' . urlencode( PluginHelper::getVersion() );

            return [ 'auth_url' => $authUrl ];
        }

        $app = App::where( 'id', $appId )->where( 'social_network', $socialNetwork )->where( 'slug', 'is', null )->fetch();

        if ( empty( $app ) )
        {
            throw new Exception( 'App is not available' );
        }

        $authUrl = apply_filters( 'fsp_auth_get_url', '', $socialNetwork, $app, $proxy );
        return [ 'auth_url' => $authUrl ];
    }

    /**
     * @throws Exception
     */
    public static function save ( RestRequest $request ): array
    {
        $channelSessions = [];
        $channelsToAdd   = $request->require( 'channels', RestRequest::TYPE_ARRAY, fsp__( 'No channels are selected' ) );

        foreach ( $channelsToAdd as $channel )
        {
            $id                                     = $channel['id'] ?? null;
            $channelSessionId                       = $channel['channel_session_id'] ?? null;
            $channelSessions[ $channelSessionId ]   = $channelSessions[ $channelSessionId ] ?? ChannelSession::get( $channelSessionId );
            $channelType                            = $channel['channel_type'] ?? null;
            $name                                   = $channel['name'] ?? '';
            $picture                                = $channel['picture'] ?? '';
            $remoteId                               = $channel['remote_id'] ?? null;
            $socialNetwork                          = $channel['social_network'] ?? null;
            $data                                   = $channel['data'] ?? [];

            if ( empty( $channelSessionId ) || empty( $channelType ) || empty( $remoteId ) || empty( $socialNetwork ) )
                continue;

            if ( !empty( $id ) && $id > 0 )
            {
                $updateData = apply_filters( 'fsp_update_channel', [
                    'name'          => $name,
                    'picture'       => $picture,
                    'status'        => 1,
                    'is_deleted'    => 0,
                    'updated_at'    => Date::dateTimeSQL(),
	                'data'          => $data
                ], $socialNetwork, $channel, $channelSessions[ $channel[ 'channel_session_id' ] ] );

                if ( isset( $updateData[ 'data' ] ) && is_array( $updateData[ 'data' ] ) )
                {
                    $updateData[ 'data' ] = json_encode( $updateData[ 'data' ] );
                }

                Channel::where( 'id', $id )->update( $updateData );
            } else
            {
                $insertData = apply_filters( 'fsp_insert_channel', [
                    'channel_session_id'    => $channelSessionId,
                    'channel_type'          => $channelType,
                    'name'                  => $name,
                    'picture'               => $picture,
                    'remote_id'             => $remoteId,
                    'status'                => 1,
                    'is_deleted'            => 0,
                    'updated_at'            => Date::dateTimeSQL(),
                    'created_at'            => Date::dateTimeSQL(),
	                'data'                  => $data
                ], $socialNetwork, $channel, $channelSessions[ $channel[ 'channel_session_id' ] ] );

                if ( isset( $insertData[ 'data' ] ) && is_array( $insertData[ 'data' ] ) )
                {
                    $insertData[ 'data' ] = json_encode( $insertData[ 'data' ] );
                }

                Channel::insert( $insertData );
            }
        }

        return [];
    }

    /**
     * @throws Exception
     */
    public static function refresh ( RestRequest $request ): array
    {
        $id = $request->require( 'id', RestRequest::TYPE_INTEGER, fsp__( 'Please select a channel to refresh' ) );

        $channel = Channel::get( $id );

        if ( empty( $channel ) || ! $channel->hasPermission('full_access') )
            throw new Exception( fsp__( 'You don\'t have access to this channel' ) );

        $channelSession = $channel->channel_session->fetch();

        try
        {
            $refreshed = apply_filters( 'fsp_refresh_channel', [], $channelSession->social_network, $channel, $channelSession );
        } catch ( ChannelSessionException $e )
        {
            do_action( 'fsp_disable_channel', $channelSession->social_network, $channel, $channelSession );
            throw new Exception( fsp__( 'Couldn\'t update channel' ) );
        }

        if ( empty( $refreshed ) )
        {
            do_action( 'fsp_disable_channel', $channelSession->social_network, $channel, $channelSession );
            throw new Exception( fsp__( 'Couldn\'t update channel' ) );
        }

		if( array_key_exists( 'id', $refreshed ) )
			unset( $refreshed['id'] );

        $data = $channel->data_obj->toArray();

        $data = array_merge( $data, $refreshed['data'] ?? [] );

        $refreshed['data'] = json_encode( $data );

        unset( $refreshed['social_network'] );

        Channel::where( 'id', $id )->update( $refreshed );

        return [];
    }

    /**
     * @throws Exception
     */
    public static function delete ( RestRequest $request ): array
    {
        $channelsToDelete = $request->require( 'ids', RestRequest::TYPE_ARRAY, fsp__( 'No channels are selected' ) );

        $channelsToDelete = array_filter( $channelsToDelete, 'is_numeric' );

        if ( empty( $channelsToDelete ) )
            throw new Exception( fsp__( 'No channels are selected' ) );

        $channelsToDelete = Channel::where( 'id', $channelsToDelete )->fetchAll();

		/**
	     * Check permissions. Only users who have full_access permission to the channel can delete it
	     */
	    $channelsToDelete = array_filter( $channelsToDelete, function ( Collection $channelInf ) {
		    return $channelInf->hasPermission('full_access');
	    });

        if ( empty( $channelsToDelete ) )
            throw new Exception( fsp__( 'Selected channels are not available' ) );

		foreach ( $channelsToDelete AS $channelInf ) {
			ChannelService::deleteChannel( $channelInf->id );
		}

        return [];
    }

    /**
     * @throws Exception
     */
    public static function getSettings ( RestRequest $request ): array
    {
        $channelId = $request->require( 'id', RestRequest::TYPE_INTEGER, fsp__( 'Channel ID cannot be empty' ) );

        $channel = Channel::where( 'id', $channelId )->fetch();

        if ( empty( $channel ) || ! $channel->hasPermission('full_access') )
            throw new Exception( fsp__( 'You don\'t have access to the channel' ) );

        $customSettings = $channel->custom_settings_obj;

        $postFilterTerms = $customSettings[ 'post_filter_terms' ] ?? [];
        $terms           = [];

        foreach ( $postFilterTerms as $postFilterTerm )
        {
            $term = get_term( $postFilterTerm );

            if ( empty( $term ) )
            {
                continue;
            }

            $terms[] = [
                'value' => $term->term_id,
                'label' => $term->name,
            ];
        }

        $channelSession = $channel->channel_session->fetch();
	    $settings = [];

        $settings['custom_settings'] = [
            'auto_share'        => isset( $channel->auto_share ) ? $channel->auto_share === '1' : (bool)Settings::get( 'auto_share', true ),
            'post_filter_type'  => $customSettings[ 'post_filter_type' ] ?? 'all',
            'post_filter_terms' => $terms,
            'custom_post_data'  => apply_filters( 'fsp_channel_custom_post_data', ( $customSettings->custom_post_data ?? [] ), $channel, $channelSession->social_network ),
        ];

		$settings['proxy'] = $channelSession->proxy;

        $permissions = ChannelPermission::where( 'channel_id', $channelId )->fetchAll();

        $wpRoles = wp_roles()->role_names;
        $roles   = [];

        foreach ( $wpRoles as $role => $name )
        {
            $roles[ $role ] = [
                'value'       => $role,
                'label'       => $name,
                'permissions' => [],
            ];
        }

        foreach ( $permissions as $permission )
        {
            if ( isset( $roles[ $permission->user_role ] ) )
            {
                $roles[ $permission->user_role ][ 'permissions' ][] = $permission->permission;
            }
        }

        $settings[ 'roles' ] = array_values( $roles );

        $labels = ChannelLabel::where( 'id', 'in', ChannelLabelsData::where( 'channel_id', $channelId )->select( 'DISTINCT label_id', true ) )->fetchAll();

        $labels = array_map( fn ( $label ) => [
            'value' => (int)$label->id,
            'label' => $label->name,
            'color' => $label->color,
        ], $labels );

        $settings[ 'labels' ] = $labels;

        return $settings;
    }

    /**
     * @throws Exception
     */
    public static function saveSettings ( RestRequest $request ): array
    {
        $channelId = $request->require( 'id', RestRequest::TYPE_INTEGER, fsp__( 'Channel ID cannot be empty' ) );

        $channel = Channel::where( 'id', $channelId )->fetch();

        if ( empty( $channel ) || ! $channel->hasPermission('full_access') )
            throw new Exception( fsp__( 'You don\'t have access to the channel' ) );

        /*
         * saving custom auto-share options
         */
        $settings       = $request->param( 'custom_settings', [], RestRequest::TYPE_ARRAY );
        $autoShare      = $settings[ 'auto_share' ] ?? false;
        $postFilterType = isset( $settings[ 'post_filter_type' ] ) && in_array( $settings[ 'post_filter_type' ], [ 'all', 'in', 'out' ] ) ? $settings[ 'post_filter_type' ] : 'all';

        $postFilterTerms = isset( $settings[ 'post_filter_terms' ] ) && is_array( $settings[ 'post_filter_terms' ] ) ? $settings[ 'post_filter_terms' ] : [];
        $postFilterTerms = array_filter( $postFilterTerms, fn ( $v, $k ) => is_numeric( $v ), ARRAY_FILTER_USE_BOTH );
        $postFilterTerms = array_map( 'intval', $postFilterTerms );
        $postFilterTerms = array_filter( $postFilterTerms, fn ( $v, $k ) => $v > 0, ARRAY_FILTER_USE_BOTH );

        if ( $postFilterType !== 'all' && empty( $postFilterTerms ) )
        {
            throw new Exception( fsp__( 'Please select post terms' ) );
        }

        $customSettings = [];

        $customizationData = ( isset( $settings[ 'custom_post_data' ] ) && is_array( $settings[ 'custom_post_data' ] ) ) ? $settings[ 'custom_post_data' ] : [];

        $customSettings[ 'post_filter_type' ]  = $postFilterType;
        $customSettings[ 'post_filter_terms' ] = $postFilterTerms;
        $customSettings[ 'custom_post_data' ]  = $customizationData;

        Channel::where( 'id', $channelId )->update( [
            'auto_share'      => $autoShare ? 1 : 0,
            'custom_settings' => json_encode( $customSettings ),
        ] );

	    /*
		 * saving proxy
		 */
	    $proxy = $request->param( 'proxy', '', RestRequest::TYPE_STRING );
		$channelSession = $channel->channel_session->fetch();

		ChannelSession::where('id', $channelSession->id)->update([
			'proxy' => $proxy
		]);

        /*
         * saving permissions
         */
        $permissions = $request->param( 'permissions', [], RestRequest::TYPE_ARRAY );

        global $wp_roles;

        foreach ( $permissions as $role => $caps )
        {
            if ( !isset( $wp_roles->roles[ $role ] ) )
            {
                throw new Exception( fsp__( 'Undefined role' ) );
            }

            foreach ( $caps as $cap )
            {
                if ( !in_array( $cap, [ 'full_access', 'can_share' ] ) )
                {
                    throw new Exception( fsp__( 'Undefined permission' ) );
                }
            }
        }

        ChannelPermission::where( 'channel_id', $channelId )->delete();

        foreach ( $permissions as $role => $caps )
        {
            foreach ( $caps as $cap )
            {
                ChannelPermission::insert( [
                    'channel_id' => $channelId,
                    'user_role'  => $role,
                    'permission' => $cap,
                ] );
            }
        }

        /*
         * saving labels
         */
        $labels = $request->param( 'labels', [], RestRequest::TYPE_ARRAY );

        foreach ( $labels as $label )
        {
            if ( !is_numeric( $label ) )
            {
                throw new Exception( fsp__( 'Invalid parameters' ) );
            }
        }

        $labels = array_filter( array_map( 'intval', $labels ), fn ( $v, $k ) => $v > 0, ARRAY_FILTER_USE_BOTH );

        if ( ! empty( $labels ) )
        {
            $userLabels = ChannelLabel::where( 'id', $labels )->select( 'id', true )->fetchAll();

            if ( count( $userLabels ) !== count( $labels ) )
            {
                throw new Exception( fsp__( 'Wrong labels' ) );
            }
        }

        ChannelLabelsData::where( 'channel_id', $channelId )->delete();

        foreach ( $labels as $label )
        {
            ChannelLabelsData::insert( [
                'channel_id' => $channelId,
                'label_id'   => $label
            ] );
        }

        return [];
    }

}
