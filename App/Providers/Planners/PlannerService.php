<?php

namespace FSPoster\App\Providers\Planners;

use FSPoster\App\Models\Planner;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Schedules\ScheduleService;

class PlannerService
{

    public static function sharePlanners ()
    {
        $nowDateTime = Date::dateTimeSQL();

        $planners = Planner::where( 'status', 'active' )->where( 'next_execute_at', '<=', $nowDateTime )->fetchAll();

        //prevent duplicates for interval schedules
        Planner::where( 'status', 'active' )->where( 'share_type', 'interval' )->where( 'next_execute_at', '<=', $nowDateTime )->update( [
            'next_execute_at' => DB::field( DB::raw( 'DATE_ADD(`next_execute_at`, INTERVAL ((TIMESTAMPDIFF(MINUTE, `next_execute_at`, %s) DIV (schedule_interval DIV 60) ) + 1) * (schedule_interval DIV 60) minute)', [ $nowDateTime ] ) ),
        ] );

        foreach ( $planners as $planner )
        {
            if ( $planner->share_type === 'weekly' )
            {
                $weeklyScheduleNextExecuteTime = PlannerHelper::weeklyNextExecuteTime( json_decode( $planner->weekly, true ) );

                Planner::where( 'id', $planner->id )->update( [ 'next_execute_at' => $weeklyScheduleNextExecuteTime ] );
            }

            if ( PlannerHelper::isSleepTime( $planner ) )
            {
                continue;
            }

            Helper::setBlogId( $planner->blog_id );

            $postDateRange = empty( $planner->post_filters_date_range_to ) ? [] : [
                'from' => $planner->post_filters_date_range_from,
                'to'   => $planner->post_filters_date_range_to,
            ];

            $plannerSelectedPosts = empty( $planner->selected_posts ) ? [] : explode( ',', $planner->selected_posts );
            $filterQuery          = PlannerHelper::plannerFilters( $planner->post_type, $postDateRange, $plannerSelectedPosts, $planner->post_filters_term, $planner->post_filters_skip_oos_products, $planner->sort_by, $planner->shared_posts );

            /* End post_sort */
            $getRandomPost = DB::DB()->get_row( "SELECT * FROM `" . DB::WPtable( 'posts', true ) . "` tb1 WHERE (post_status='publish' OR post_type='attachment') AND {$filterQuery} LIMIT 1", ARRAY_A );

            $postId = !empty( $getRandomPost[ 'ID' ] ) ? $getRandomPost[ 'ID' ] : 0;

            if ( empty( $postId ) )
            {
                if ( !empty( $planner->selected_posts ) || $planner->sort_by !== 'old_to_new' )
                {
                    if ( $planner->repeating == '1' )
                    {
                        Planner::where( 'id', $planner->id )->update( [ 'shared_posts' => '' ] );
                    } else
                    {
                        Planner::where( 'id', $planner->id )->update( [ 'status' => 'finished' ] );
                    }
                }
            } else
            {
                $plannerShared = ScheduleService::createSchedulesFromPlanner( $planner, $postId );

                if ( $plannerShared )
                {
                    $sharedPosts   = empty( $planner->shared_posts ) ? [] : explode( ',', $planner->shared_posts );
                    $sharedPosts[] = $postId;

					if ($planner->repeating == '1' && count($plannerSelectedPosts) === count($sharedPosts)) {
						$sharedPosts = [];
					}

                    Planner::where( 'id', $planner->id )->update( [ 'shared_posts' => implode( ',', $sharedPosts ) ] );
                } else
                {
                    Planner::where( 'id', $planner->id )->update( [ 'status' => 'paused' ] );
                }
            }

            Helper::resetBlogId();
        }
    }

}