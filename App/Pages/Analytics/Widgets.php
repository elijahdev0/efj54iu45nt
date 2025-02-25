<?php

namespace FSPoster\App\Pages\Analytics;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Widgets
{
    private function __construct () {}

    public static function register (): void
    {
        $widgets = new self();
        add_filter( 'fsp_analytics_get_widget', [ $widgets, 'generalStats' ], 10, 3 );
        add_filter( 'fsp_analytics_get_widget', [ $widgets, 'sharedPosts' ], 10, 3 );
        add_filter( 'fsp_analytics_get_widget', [ $widgets, 'clicks' ], 10, 3 );
        add_filter( 'fsp_analytics_get_widget', [ $widgets, 'clicksPerSocialNetwork' ], 10, 3 );
        add_filter( 'fsp_analytics_get_widget', [ $widgets, 'clicksPerChannel' ], 10, 3 );
    }

    public function generalStats ( array $data, $name, $options ): array
    {
        if ( $name !== 'general_stats' )
        {
            return $data;
        }

        return [
            'total_channels'        => Channel::count(),
            'disconnected_channels' => Channel::where( 'status', 0 )->count(),
            'active_planners'       => Planner::where( 'status', 'active' )->count(),
            'paused_planners'       => Planner::where( 'status', 'paused' )->count(),
        ];
    }

    public function sharedPosts ( array $data, $name, $options ): array
    {
        if ( $name !== 'shared_posts' )
        {
            return $data;
        }

        $offset = intval( $options[ 'timezone_offset' ] ?? 0 );

        $daily   = Schedule::where( 'status', 'in', [ 'success', 'error' ] )->select( [ 'CAST(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE) AS DATE) AS date', 'COUNT(0) AS c' ], true )->groupBy( [ 'CAST(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE) AS DATE)' ] )->fetchAll() ?? [];
        $monthly = Schedule::where( 'status', 'in', [ 'success', 'error' ] )->where( 'send_time', '>', Date::dateTimeSQL( '1 year ago' ) )->select( [ 'CONCAT(YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE)), \'-\', MONTH(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE)) , \'-01\') AS date', 'COUNT(0) AS c' ], true )->groupBy( [ 'YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE))', 'MONTH(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE))' ] )->fetchAll() ?? [];
        $yearly  = Schedule::where( 'status', 'in', [ 'success', 'error' ] )->select( [ 'CONCAT(YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE)), \'-01-01\') AS date', 'COUNT(0) AS c' ], true )->groupBy( [ 'YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE))' ] )->fetchAll() ?? [];


        return [
            'daily'   => array_map( fn ( $d ) => [
                'label' => Date::format( 'Y-m-d', $d[ 'date' ] ),
                'value' => (int)$d[ 'c' ],
            ], $daily ),
            'monthly' => array_map( fn ( $m ) => [
                'label' => Date::format( 'Y M', $m[ 'date' ] ),
                'value' => (int)$m[ 'c' ],
            ], $monthly ),
            'yearly'  => array_map( fn ( $y ) => [
                'label' => Date::format( 'Y', $y[ 'date' ] ),
                'value' => (int)$y[ 'c' ],
            ], $yearly ),
        ];
    }

    public function clicks ( array $data, $name, $options ): array
    {
        if ( $name !== 'clicks' )
        {
            return $data;
        }

        $offset = intval( $options[ 'timezone_offset' ] ?? 0 );

        $daily   = Schedule::where( 'status', 'in', [ 'success', 'error' ] )->select( [ 'CAST(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE) AS DATE) AS date', 'SUM(visit_count) AS c' ], true )->groupBy( [ 'CAST(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE) AS DATE)' ] )->fetchAll() ?? [];
        $monthly = Schedule::where( 'status', 'in', [ 'success', 'error' ] )->where( 'send_time', '>', Date::dateTimeSQL( '1 year ago' ) )->select( [ 'CONCAT(YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE)), \'-\', MONTH(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE)) , \'-01\') AS date', 'SUM(visit_count) AS c' ], true )->groupBy( [ 'YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE))', 'MONTH(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE))' ] )->fetchAll() ?? [];
        $yearly  = Schedule::where( 'status', 'in', [ 'success', 'error' ] )->select( [ 'CONCAT(YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE)), \'-01-01\') AS date', 'SUM(visit_count) AS c' ], true )->groupBy( [ 'YEAR(DATE_ADD(send_time, INTERVAL ' . $offset . ' MINUTE))' ] )->fetchAll() ?? [];


        return [
            'daily'   => array_map( fn ( $d ) => [
                'label' => Date::format( 'Y-m-d', $d[ 'date' ] ),
                'value' => (int)$d[ 'c' ],
            ], $daily ),
            'monthly' => array_map( fn ( $m ) => [
                'label' => Date::format( 'Y M', $m[ 'date' ] ),
                'value' => (int)$m[ 'c' ],
            ], $monthly ),
            'yearly'  => array_map( fn ( $y ) => [
                'label' => Date::format( 'Y', $y[ 'date' ] ),
                'value' => (int)$y[ 'c' ],
            ], $yearly ),
        ];
    }

    public function clicksPerSocialNetwork ( array $data, $name, $options ): array
    {
        if ( $name !== 'click_stats_per_social_network' )
        {
            return $data;
        }

        $socialNetworks = array_keys( SocialNetworkAddon::getSocialNetworks() );

        $stats = [];

        foreach ( $socialNetworks as $sn )
        {
            $stats[ $sn ] = 0;
        }

        $result = Schedule::leftJoin( 'channel', [] )
            ->leftJoin( ChannelSession::getTableName(), [], ChannelSession::getField( 'id' ), Channel::getField( 'channel_session_id' ) )
            ->where( Schedule::getField( 'status' ), 'success' )
            ->groupBy( ChannelSession::getField( 'social_network' ) )
            ->select( [
                ChannelSession::getField( 'social_network' ),
                'sum(' . Schedule::getField( 'visit_count' ) . ') as c',
            ] )
            ->fetchAll() ?? [];

        foreach ( $result as $row )
        {
            if ( isset( $stats[ $row[ 'social_network' ] ] ) )
            {
                $stats[ $row[ 'social_network' ] ] = $row[ 'c' ];
            }
        }

        $data = [];

        foreach ( $stats as $k => $v )
        {
			if( $v > 0 )
			{
				$data[] = [
					'label' => $k,
					'value' => (int)$v,
				];
			}
        }

        return $data;
    }

    public function clicksPerChannel ( array $data, $name, $options ): array
    {
        if ( $name !== 'click_stats_per_channel' )
        {
            return $data;
        }

        $result = Schedule::leftJoin( 'channel', [] )
            ->where( Schedule::getField( 'status' ), 'success' )
            ->groupBy( Schedule::getField( 'channel_id' ) )
            ->select( [
                Channel::getField( 'channel_session_id' ),
                Channel::getField( 'name' ),
                'sum(' . Schedule::getField( 'visit_count' ) . ') as c',
            ] )
            ->orderBy( [
                'sum(' . Schedule::getField( 'visit_count' ) . ') DESC',
            ] )
            ->limit( 10 )
            ->fetchAll() ?? [];

        $channelSessionIds = array_map( fn ( $v ) => $v[ 'channel_session_id' ], $result );

        $socialNetworks = [];

        if ( !empty( $channelSessionIds ) )
        {
            $channelSessions = ChannelSession::where( 'id', $channelSessionIds )->select( [ 'id', 'social_network' ], true )->fetchAll() ?? [];

            foreach ( $channelSessions as $cs )
            {
                $socialNetworks[ $cs->id ] = $cs->social_network;
            }
        }

        $stats = [];

        foreach ( $result as $row )
        {
            $stats[] = [
                'label' => SocialNetworkAddon::getNetworkName( $socialNetworks[ $row[ 'channel_session_id' ] ] ) . '/' . $row[ 'name' ],
                'value' => (int)$row[ 'c' ],
            ];
        }

        return $stats;
    }
}