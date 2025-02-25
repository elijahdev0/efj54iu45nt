<?php

namespace FSPoster\App\Pages\Schedules;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Planners\PlannerHelper;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;

class Controller
{
    public static function list ( RestRequest $request ): array
    {
		$scheduleId         = $request->param( 'schedule_id', 0, RestRequest::TYPE_INTEGER );
		$scheduleGroupId    = $request->param( 'schedule_group_id', '', RestRequest::TYPE_STRING );
        $page               = $request->param( 'page', 1, RestRequest::TYPE_INTEGER );
        $rowsPerPage        = $request->param( 'rows_count', 10, RestRequest::TYPE_INTEGER );
        $plannerId          = $request->param( 'planner_id', 0, RestRequest::TYPE_INTEGER );
        $wpPostId           = $request->param( 'wp_post_id', 0, RestRequest::TYPE_INTEGER );
        $statuses           = $request->param( 'statuses', [], RestRequest::TYPE_ARRAY, [ 'not_sent', 'success', 'error', 'draft' ] );
        $socialNetworks     = $request->param( 'social_networks', [], RestRequest::TYPE_ARRAY, array_keys( SocialNetworkAddon::getSocialNetworks() ) );

        $page = $page > 0 ? $page : 1;
	    $rowsPerPage = $rowsPerPage > 50 || $rowsPerPage < 10 ? 10 : $rowsPerPage;

        $schedulesQuery = Schedule::where( 'status', '<>', 'sending' );

		if( $scheduleId > 0 )
			$schedulesQuery->where( 'id', $scheduleId );

		if( ! empty( $scheduleGroupId ) )
			$schedulesQuery->where( 'group_id', $scheduleGroupId );

        if ( ! empty( $socialNetworks ) )
            $schedulesQuery->where( 'channel_id', 'IN', Channel::where( 'channel_session_id', 'in', ChannelSession::where( 'social_network', $socialNetworks )->select( 'id', true ) )->select( 'id', true ) );

        if ( $plannerId > 0 )
            $schedulesQuery->where( 'planner_id', $plannerId );

        if ( $wpPostId > 0 )
            $schedulesQuery->where( 'wp_post_id', $wpPostId );

        if ( ! empty( $statuses ) )
            $schedulesQuery->where( 'status', 'IN', $statuses );

        $count = $schedulesQuery->count();

        $offset = ( $page - 1 ) * $rowsPerPage;

        $schedules = $schedulesQuery->orderBy( 'send_time DESC' )->offset( $offset )->limit( $rowsPerPage )->fetchAll();

        $resultData = [];

        foreach ( $schedules as $schedule )
        {
	        $scheduleObj = new ScheduleObject( $schedule->id, true );
	        $calendarData = new CalendarData();
	        $calendarData = apply_filters( 'fsp_get_calendar_data', $calendarData, $scheduleObj );

            $wpPostLink = site_url() . '/?p=' . $schedule->wp_post_id;
            $postLink = $schedule->status === 'success' ? apply_filters( 'fsp_get_shared_post_link', '', $scheduleObj ) : '';
            $channelLink = apply_filters( 'fsp_get_channel_link', $scheduleObj->getChannelSession()->social_network, '', $scheduleObj->getChannel() );

            $resultData[] = [
                'id'                    => (int)$schedule->id,
                'wp_post'               => [
                    'id'            => (int)$schedule->wp_post_id,
                    'link'          => $wpPostLink,
                    'title'         => htmlspecialchars( $scheduleObj->getWPPost()->post_title ?? 'Deleted' ),
	                'post_type'     => $scheduleObj->getWPPost()->post_type ?? '',
	                'post_status'   => $scheduleObj->getWPPost()->post_status ?? ''
                ],
                'group_id'              => $schedule->group_id,
                'channel'               => [
                    'id'             => (int)$scheduleObj->getChannel()->id,
                    'name'           => !empty( $scheduleObj->getChannel()->name ) ? $scheduleObj->getChannel()->name : "[no name]",
                    'social_network' => $scheduleObj->getChannelSession()->social_network,
                    'method'         => $scheduleObj->getChannelSession()->method,
                    'picture'        => $scheduleObj->getChannel()->picture,
                    'channel_link'   => $channelLink,
                    'channel_type'   => $scheduleObj->getChannel()->channel_type,
                    'status'         => $scheduleObj->getChannel()->status === '1',
	                'is_deleted'     => (int)$scheduleObj->getChannel()->is_deleted
                ],
                'status'                => $schedule->status,
                'error_msg'             => $schedule->error_msg,
                'send_time'             => Date::epoch( $schedule->send_time ),
                'remote_post_id'        => (int)$schedule->remote_post_id ?: null,
                'remote_post_link'      => $postLink,
                'customization_data'    => $schedule->customization_data_obj,
	            'posting_data'          => (array)$calendarData,
                'data'                  => $schedule->data_obj
            ];
        }

        return [
            'schedules' => $resultData,
            'total'     => $count ?: 0,
        ];
    }

    /**
     * @throws Exception
     */
    public static function calendar ( RestRequest $request ): array
    {
        $start              = $request->param( 'start', RestRequest::TYPE_INTEGER, fsp__( 'Please set start time' ) );
        $end                = $request->param( 'end', RestRequest::TYPE_INTEGER, fsp__( 'Please set end time' ) );
        $statuses           = $request->param( 'statuses', [], RestRequest::TYPE_ARRAY, [ 'not_sent', 'success', 'error', 'draft' ] );
        $socialNetworks     = $request->param( 'social_networks', [], RestRequest::TYPE_ARRAY, array_keys( SocialNetworkAddon::getSocialNetworks() ) );

        $calendarSchedules = [];

        //region planned schedules

        /**
         * We should find all schedules that planned to be shared between "start" and "end" time.
         * We should also consider that if next execute time of planner is after "end", we could skip
         * that schedule. But not otherway around. Because, even if the next execute time is before "start",
         * there is possibility that they will be executed again in the future and that will be between
         * "start" and "end" time.
         * At the moment the only way to achieve this is fetching all planners that next execute time does not exceed
         * "end" and status is active. Then calculating all planned schedules.
         *
         * @var Planner[] $plannedPlanners
         */
        $plannedPlanners = Planner::where( 'status', 'active' )->where( 'next_execute_at', '<=', Date::dateTimeSQL( $end ) )->fetchAll();

        foreach ( $plannedPlanners as $planner )
        {
            $channelIds = array_filter( array_map( fn ( $channelId ) => is_numeric( $channelId ) ? intval( $channelId ) : $channelId, explode( ',', $planner->channels ) ), fn ( $channelId ) => is_int( $channelId ) && $channelId > 0 );

            if ( empty( $channelIds ) )
            {
                continue;
            }

            $channels = Channel::where( 'id', $channelIds )->fetchAll();

            if ( empty( $channels ) )
            {
                continue;
            }

            $nextExecute = Date::epoch( $planner->next_execute_at );

            /**
             * We calculate what posts (aka schedules) are planned to be shared and did not shared yet.
             * First we exclude already shared posts and also apply filters, then fetch posts.
             */
            $postDateRange = empty( $planner->post_filters_date_range_to ) ? [] : [
                'from' => $planner->post_filters_date_range_from,
                'to'   => $planner->post_filters_date_range_to,
            ];
            $selectedPosts = empty( $planner->selected_posts ) ? [] : explode( ',', $planner->selected_posts );

            $filterQuery = PlannerHelper::plannerFilters(
                $planner->post_type,
                $postDateRange,
                $selectedPosts,
                $planner->post_filters_term,
                $planner->post_filters_skip_oos_products,
                $planner->sort_by,
                $planner->shared_posts
            );

            $postsToBeShared = DB::DB()->get_col( "SELECT ID FROM `" . DB::WPtable( 'posts', true ) . "` tb1 WHERE (post_status='publish' OR post_type='attachment') AND {$filterQuery}" );

            /**
             * In planned schedules we don't need exact order of channels
             * as it is too ambiguous (https://www.dictionary.com/browse/ambiguous)
             * so we just take first channel and channel session.
             *
             * @var Channel        $firstChannel
             * @var ChannelSession $firstChannelSession
             */
//            $firstChannel        = $channels[ 0 ];
//            $firstChannelSession = $firstChannel->channel_session->fetch();

            $i = 0;

            while ( $nextExecute <= $end )
            {
                /**
                 * This condition is must here. Because, if next execute time is before "start" time, there is no need to calculate posts.
                 * We should find next execute time of that planner that is >= "start" time.
                 * So we can continue to calculate posts.
                 */
                if ( $nextExecute >= $start )
                {
                    $currentPost = $postsToBeShared[$i];
                    $post        = get_post( $currentPost );


                    $newGroupId = Helper::generateUUID();
	                $calendarData = new CalendarData();
	                $calendarData->content = sprintf('This post scheduled by planner. Planner name: %s', $planner->title);

                    $calendarSchedules[] = [
                        'schedule_group_id' => $newGroupId,
                        'wp_post_id'        => $post->ID,
                        'posting_data'      => (array)$calendarData,
                        'channels'          => $channels,
                        'status'            => 'not_sent',
                        'send_time'         => $nextExecute,
	                    'planner_id'        => (int)$planner->id
                    ];

                    /**
                     * Break loop $i exceeds count of posts to be shared.
                     */
                    if ( ++$i >= count( $postsToBeShared ) )
                    {
                        break;
                    }
                }

                /**
                 * We should calculate next execute time of planner.
                 * If planner is weekly, we should calculate next execute time based on weekly settings.
                 * If planner is interval, we should add interval to next execute time.
                 * If next execute time exceeds "end" time, loop will break.
                 */
                if ( $planner->share_type === 'weekly' )
                {
                    $nextExecute = Date::epoch( PlannerHelper::weeklyNextExecuteTime( json_decode( $planner->weekly, true ), Date::dateTimeSQL( $nextExecute ) ) );
                } else if ( $planner->share_type === 'interval' )
                {
                    $nextExecute += $planner->schedule_interval;

                    if ( $planner->sleep_time_start && $planner->sleep_time_end )
                    {
                        while ( Date::isBetweenDates( $nextExecute, $planner->sleep_time_start, $planner->sleep_time_end ) )
                        {
                            $nextExecute += $planner->schedule_interval;
                        }
                    }
                }
            }
        }
        //endregion

        // region schedules
        /**
         * @var Schedule[] $schedules
         */
        $schedules = Schedule::select( [ '*', 'GROUP_CONCAT(channel_id SEPARATOR \',\') as channel_ids' ] )
            ->where( 'send_time', '>=', Date::dateTimeSQL( $start ) )
            ->where( 'send_time', '<=', Date::dateTimeSQL( $end ) )
            ->where( 'status', '<>', 'sending' )
            ->groupBy( "group_id, status" )
            ->fetchAll();

        if ( !empty( $schedules ) )
        {
            foreach ( $schedules as $schedule )
            {
                $channelIds = json_decode( "[" . $schedule->channel_ids . "]" );
                $channels   = Channel::withoutGlobalScope('soft_delete')
                                     ->where( "id", $channelIds )
                                     ->fetchAll();

                $channels = array_map( function ( $channel )
                {
                    $channelSession = $channel->channel_session->fetch();

                    return [
                        'id'             => (int)$channel->id,
                        'name'           => !empty( $channel->name ) ? $channel->name : "[no name]",
                        'social_network' => $channelSession->social_network,
                        'picture'        => $channel->picture,
                        'status'         => $channel->status === '1',
                    ];
                }, $channels );

                $scheduleObj = new ScheduleObject( $schedule->id, true );
				$calendarData = new CalendarData();
				$calendarData = apply_filters( 'fsp_get_calendar_data', $calendarData, $scheduleObj );

                $calendarSchedules[] = [
                    'schedule_group_id' => $schedule->group_id,
                    'wp_post_id'        => $schedule->wp_post_id,
                    'posting_data'      => (array)$calendarData,
                    'channels'          => $channels,
                    'status'            => $schedule->status,
                    'send_time'         => Date::epoch( $schedule->send_time ),
	                'planner_id'        => 0
                ];
            }
        }
        //endregion

        if ( ! empty( $statuses ) )
        {
            // note: this can be optimized
            $calendarSchedules = array_filter($calendarSchedules, function( $schedule ) use ( $statuses )
            {
                return in_array($schedule['status'], $statuses);
            });
        }

        if ( ! empty( $socialNetworks ) )
        {
            // note: this can be optimized
            $calendarSchedules = array_filter($calendarSchedules, function( $schedule ) use ( $socialNetworks )
            {
                return array_intersect(array_column($schedule['channels'], 'social_network'), $socialNetworks);
            });
        }

        return [
            'schedules' => array_values( $calendarSchedules ) // info: array_values ona gore edilir ki, yuxarida array_filter istifade edilir ve o Listin original indexini salamag uchun list arraydan chixardir tipi. neticde json olanda objecte chevrilir.
        ];
    }

    /**
     * @throws Exception
     */
    public static function delete ( RestRequest $request ): array
    {
        $all     = $request->param( 'include_all', false, RestRequest::TYPE_BOOL );
        $exclude = $request->param( 'exclude_ids', [], RestRequest::TYPE_ARRAY );
        $filters = $request->param( 'filters', [], RestRequest::TYPE_ARRAY );
        $include = $request->param( 'include_ids', [], RestRequest::TYPE_ARRAY );

        if ( !$all && empty( $include ) )
        {
            return [];
        }

        $schedule = Schedule::where( 'status', '<>', 'sending' );

        if ( ! $all && ! empty( $include ) )
            $schedule->where( 'id', 'IN', $include );

        if ( $all && ! empty( $exclude ) )
            $schedule->where( 'id', 'NOT IN', $exclude );

        if ( $all && ! empty( $filters ) )
        {
            $socialNetworks = $filters[ 'social_networks' ] ?? [];
            $socialNetworks = array_intersect( array_keys( SocialNetworkAddon::getSocialNetworks() ), $socialNetworks );

            if ( ! empty( $socialNetworks ) )
                $schedule->where( 'channel_id', 'IN', Channel::where( 'channel_session_id', 'in', ChannelSession::where( 'social_network', $socialNetworks )->select( 'id', true ) )->select( 'id', true ) );

            $plannerId = intval( $filters[ 'planner_id' ] );

            if ( $plannerId > 0 )
                $schedule->where( 'planner_id', $plannerId );

            $statuses = $filters[ 'statuses' ] ?? [];
            $statuses = array_intersect( [ 'not_sent', 'success', 'error', 'draft' ], $statuses );

            if ( !empty( $statuses ) )
                $schedule->where( 'status', 'IN', $statuses );
        }


	    $scehdulesNeedToDelete = $schedule->fetchAll();
	    $scheduleIDs = [];
		$wpPostIDs = [];
		foreach ( $scehdulesNeedToDelete AS $scheduleInf )
		{
			$scheduleIDs[] = $scheduleInf->id;

			$wpPostId = $scheduleInf->wp_post_id;

			if( get_post_type( $wpPostId ) !== 'fsp_post' )
				update_post_meta( $wpPostId, 'fsp_schedule_created_manually', 1 );
			else
				$wpPostIDs[] = $wpPostId;
		}

		Schedule::where( 'id', $scheduleIDs )->delete();

		foreach ( $wpPostIDs AS $wpPostId )
		{
			$scheduleExists = Schedule::where( 'wp_post_id', $wpPostId )->count();

			if( ! $scheduleExists )
				wp_delete_post( $wpPostId, true );
		}

        return [];
    }

    public static function export ( RestRequest $request ): array
    {
        $plannerId      = $request->param( 'planner_id', 0, RestRequest::TYPE_INTEGER );
        $statuses       = $request->param( 'statuses', [], RestRequest::TYPE_ARRAY, [ 'not_sent', 'success', 'error', 'draft' ] );
        $socialNetworks = $request->param( 'social_networks', [], RestRequest::TYPE_ARRAY, array_keys( SocialNetworkAddon::getSocialNetworks() ) );

        $schedulesQuery = Schedule::where( 'send_time', '<', Date::dateTimeSQL() )->where( 'status', '<>', 'sending' );

        if ( !empty( $socialNetworks ) )
        {
            $schedulesQuery->where( 'channel_id', 'IN', Channel::where( 'channel_session_id', 'in', ChannelSession::where( 'social_network', $socialNetworks )->select( 'id', true ) )->select( 'id', true ) );
        }

        if ( $plannerId > 0 )
        {
            $schedulesQuery->where( 'planner_id', $plannerId );
        }

        if ( !empty( $statuses ) )
        {
            $schedulesQuery->where( 'status', 'IN', $statuses );
        }

        $schedules = $schedulesQuery->fetchAll();

        $f         = fopen( 'php://memory', 'w' );
        $delimiter = ',';
        $filename  = 'FS-Poster_logs_' . date( 'Y-m-d' ) . '.csv';
        $fields    = [
            //fsp__( 'ID' ),
            fsp__( 'Account Name' ),
            fsp__( 'Account Link' ),
            fsp__( 'Date' ),
            fsp__( 'Post Link' ),
            fsp__( 'Publication Link' ),
            fsp__( 'Social Network' ),
            fsp__( 'Status' ),
            fsp__( 'Error Message' ),
        ];

        fputcsv( $f, $fields, $delimiter );

        foreach ( $schedules as $schedule )
        {
	        $scheduleObj    = new ScheduleObject( $schedule->id, true );
            $postType       = $scheduleObj->getWPPost()->post_type ?? '';
            $channel        = $scheduleObj->getChannel();
            $channelSession = $scheduleObj->getChannelSession();

            $postLink = $schedule->status === 'success' ? apply_filters( 'fsp_get_shared_post_link', '', $scheduleObj ) : '';

            $channelLink = apply_filters( 'fsp_get_channel_link', $channelSession->social_network, '', $channel );

            $arr = [
                //$schedule[ 'id' ],
                !empty( $channel->name ) ? $channel->name : "[no name]",
                $channelLink,
                $schedule->send_time,
                $postType === 'fsp_post' ? '' : site_url() . '/?p=' . $schedule->wp_post_id,
                $postLink,
                SocialNetworkAddon::getNetwork( $channelSession->social_network )->getName(),
                $schedule->status,
                $schedule->error_msg,
            ];

            fputcsv( $f, $arr, $delimiter );
        }

        fseek( $f, 0 );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '";' );
        ob_start();
        fpassthru( $f );

        $file = ob_get_clean();
        $data = [
            'file'     => 'data:application/vnd.ms-excel;base64,' . base64_encode( $file ),
            'filename' => $filename,
        ];

        fclose( $f );

        return $data;
    }

    /**
     * @param RestRequest $request
     *
     * @return array
     * @throws Exception
     */
    public static function retry ( RestRequest $request ): array
    {
        $ids = $request->require( 'ids', RestRequest::TYPE_ARRAY, fsp__( 'Please select logs to retry' ) );

        $ids = array_filter( $ids, 'is_numeric' );

        if ( empty( $ids ) )
        {
            throw new Exception( fsp__( 'Please select logs to retry' ) );
        }

        $schedulesCount = Schedule::where( 'id', 'in', $ids )->where( 'status', 'error' )->count();

        if ( count( $ids ) !== $schedulesCount )
        {
            throw new Exception( fsp__( 'You don\'t have access to all the selected logs' ) );
        }

        Schedule::where( 'id', 'in', $ids )->update( [
            'status'    => 'not_sent',
            'error_msg' => '',
            'send_time' => Date::dateTimeSQL(),
        ] );

        return [];
    }

    /**
     * @param RestRequest $request
     *
     * @return array|array[]
     * @throws Exception
     */
    public static function getInsights ( RestRequest $request ): array
    {
        $scheduleId = $request->require( 'id', RestRequest::TYPE_INTEGER, fsp__( 'id parameter must be set' ) );

        $schedule       = Schedule::where( 'id', $scheduleId )->where( 'status', 'success' )->fetch();
        $channel        = $schedule->channel->fetch();
        $channelSession = $channel->channel_session->fetch();

        if ( empty( $schedule ) )
        {
            return [ 'insights' => [] ];
        }

		try
		{
			$insights = apply_filters( 'fsp_get_insights', [], $channelSession->social_network, $schedule, $channel, $channelSession );
		}
		catch ( ChannelSessionException $e )
		{
			do_action( 'fsp_disable_channel', $channelSession->social_network, $channel, $channelSession );

			$insights = [];
		}
		catch ( \Exception $e )
		{
			$insights = [];
		}

        $insights[] = [
            'label' => 'Hits',
            'value' => $schedule->visit_count,
        ];

        return [ 'insights' => $insights ];
    }
}