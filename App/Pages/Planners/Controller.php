<?php

namespace FSPoster\App\Pages\Planners;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Planners\PlannerHelper;

class Controller
{
    /**
     * @throws Exception
     */
    public static function save ( RestRequest $request ): array
    {
        $id                = $request->param( 'id', null, Request::TYPE_INTEGER );
        $title             = $request->param( 'title', '', Request::TYPE_STRING );
        $channelIds        = $request->require( 'channels', Request::TYPE_ARRAY, fsp__( 'Please select channels' ) );
        $customizationData = $request->param( 'customization_data', [], RestRequest::TYPE_ARRAY );
        $shareType         = $request->require( 'share_type', RestRequest::TYPE_STRING, fsp__( 'Please specify share type' ), [ 'interval', 'weekly' ] );
        $postType          = $request->require( 'post_type', RestRequest::TYPE_STRING, fsp__( 'Please specify post type' ), array_keys( get_post_types() ) );

        $selectedPosts = $request->param( 'selected_posts', [], RestRequest::TYPE_ARRAY );
        $postFilters   = $request->param( 'post_filters', [], RestRequest::TYPE_ARRAY );

        $startAt  = $request->param( 'start_at', Date::epoch(), RestRequest::TYPE_INTEGER );
        $interval = $request->param( 'schedule_interval', [], RestRequest::TYPE_ARRAY );
        $weekly   = $request->param( 'weekly', [], RestRequest::TYPE_ARRAY );

        $sortBy    = $request->param( 'sort_by', 'random', RestRequest::TYPE_STRING, [ 'random', 'old_to_new', 'new_to_old' ] );
        $repeating = $request->param( 'repeating', false, RestRequest::TYPE_BOOL );

        $existingPlanner = empty( $id ) ? null : Planner::get( $id );

        if ( !empty( $existingPlanner ) )// doit
        {
            if ( $existingPlanner->created_by != get_current_user_id() || $existingPlanner->blog_id != Helper::getBlogId() )
                throw new Exception( 'You cannot edit this planner' );
        }

        if ( !empty( $id ) && empty( $existingPlanner ) )
            throw new Exception( fsp__( 'Invalid planner' ) );

        if ( isset( $existingPlanner->share_type ) && $existingPlanner->share_type !== $shareType )
            throw new Exception( fsp__( 'You cannot change planner share type' ) );

        if ( empty( $title ) )
            throw new Exception( fsp__( 'Please enter a title' ) );

        if ( strlen( $title ) > 255 )
            throw new Exception( fsp__( 'The title needs to be shorter than 255 characters' ) );

        $channelIds = array_filter( $channelIds, fn ( $channelId ) => is_int( $channelId ) && $channelId > 0 );

        if ( empty( $channelIds ) )
            throw new Exception( fsp__( 'Please select channels' ) );

        $channels = Channel::where( 'id', $channelIds )->fetchAll();

        if ( count( $channelIds ) != count( $channels ) )
            throw new Exception( fsp__( 'You don\'t have access to all the selected channels. Please refresh the page.' ) );

        $selectedPosts = array_filter( $selectedPosts, fn ( $postId ) => is_int( $postId ) && $postId > 0 );

        if ( !empty( $selectedPosts ) )
        {
            $getSelectedPosts = PlannerHelper::wpFilterPosts( $postType, '', [], $selectedPosts );

            if ( count( $selectedPosts ) != count( $getSelectedPosts ) || empty( $getSelectedPosts ) )
                throw new Exception( fsp__( 'You don\'t have access to all the selected posts. Please refresh the page.' ) );
        }

        $savePlanner = [
            'title'                             => $title,
            'post_type'                         => $postType,
            'status'                            => $existingPlanner->status ?? 'active',
            'channels'                          => implode( ',', $channelIds ),
            'customization_data'                => json_encode( $customizationData ),
            'share_type'                        => $shareType,
            'sort_by'                           => $sortBy,
            'start_at'                          => Date::dateTimeSQL( $startAt ),
            'next_execute_at'                   => null,
            'repeating'                         => $repeating,

            'selected_posts'                    => implode( ',', $selectedPosts ),
            'shared_posts'                      => empty( $existingPlanner->shared_posts ) ? '' : $existingPlanner->shared_posts,

            'post_filters_date_range_from'      => null,
            'post_filters_date_range_to'        => null,
            'post_filters_term'                 => null,
            'post_filters_skip_oos_products'    => empty( $postFilters[ 'skip_out_of_stock_products' ] ) ? 0 : 1,

            'schedule_interval'                 => null,
            'sleep_time_start'                  => null,
            'sleep_time_end'                    => null,

            'weekly'                            => null,

            'created_at'                        => $existingPlanner->created_at ?? Date::dateTimeSQL(),
            'created_by'                        => get_current_user_id(),
            'blog_id'                           => Helper::getBlogId(),
        ];

        if ( $shareType === 'interval' )
        {
            if ( !isset( $interval['value']['value'], $interval['value']['unit'] ) )
                throw new Exception( fsp__( 'Interval is not correct' ) );

            $intervalValue = Date::convertFromUnitToSeconds( $interval['value']['value'], $interval['value']['unit'] );

            if ( !( $intervalValue > 0 ) )
                throw new Exception( fsp__( 'Interval is not correct' ) );

            $savePlanner['schedule_interval'] = $intervalValue;

            $intervalSleepTimeStart = $interval['sleep_time']['start'] ?? null;
            $intervalSleepTimeEnd   = $interval['sleep_time']['end'] ?? null;

            if ( is_string( $intervalSleepTimeStart ) && preg_match( '/\d\d:\d\d/', $intervalSleepTimeStart ) && is_string( $intervalSleepTimeEnd ) && preg_match( '/\d\d:\d\d/', $intervalSleepTimeEnd ) )
            {
                $savePlanner['sleep_time_start'] = $intervalSleepTimeStart;
                $savePlanner['sleep_time_end']   = $intervalSleepTimeEnd;
            }

            $savePlanner[ 'next_execute_at' ] = PlannerHelper::intervalNextExecuteTime( $startAt, $intervalValue );
        }
		else if ( $shareType === 'weekly' )
        {
            $weekDays = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
            $weekDays = array_intersect( $weekDays, array_keys( $weekly ) );
            $weeklyFiltered = [];

            foreach ( $weekDays as $weekDay )
            {
                if ( ! is_array( $weekly[ $weekDay ] ) )
                    continue;

                foreach ( $weekly[ $weekDay ] as $weeklyTime )
                {
                    if ( ! is_string( $weeklyTime ) || !preg_match( '/\d\d:\d\d/', $weeklyTime ) )
                        continue;

                    $weeklyFiltered[ $weekDay ][] = $weeklyTime;
                }
            }

            if ( empty( $weeklyFiltered ) )
                throw new Exception( 'Please select when to share your posts' );

            $savePlanner['weekly'] = json_encode( $weeklyFiltered );
            $savePlanner['next_execute_at'] = PlannerHelper::weeklyNextExecuteTime( $weeklyFiltered );
        }

        if ( empty( $selectedPosts ) )
        {
            if ( !empty( $postFilters['date_range']['from'] ) && !empty( $postFilters['date_range']['to'] ) )
            {
                if ( $postFilters['date_range']['from'] > $postFilters['date_range']['to'] )
                {
                    throw new Exception( fsp__( 'Invalid post date range' ) );
                }

                $savePlanner['post_filters_date_range_from'] = Date::dateTimeSQL( $postFilters['date_range']['from'] );
                $savePlanner['post_filters_date_range_to']   = Date::dateTimeSQL( $postFilters['date_range']['to'], '+1 day' );
            }

            if ( !empty( $postFilters['term'] ) && is_int( $postFilters['term'] ) )
            {
                $termTaxonomy  = get_term( $postFilters['term'] )->taxonomy ?? null;
                $termPostTypes = empty( $termTaxonomy ) ? [] : ( get_taxonomy( $termTaxonomy )->object_type ?? [] );

                if ( !in_array( $postType, $termPostTypes ) )
                {
                    throw new Exception( fsp__( 'Selected term doesn\'t match post type' ) );
                }

                $savePlanner['post_filters_term'] = $postFilters['term'];
            }
        }

        $id > 0 ? Planner::where( 'id', $id )->update( $savePlanner ) : Planner::insert( $savePlanner );

        return [];
    }

    /**
     * @throws Exception
     */
    public static function get ( RestRequest $request ): array
    {
        $id = $request->param( 'id', 0, RestRequest::TYPE_INTEGER );

        $planner = Planner::get( $id );

        if ( empty( $planner ) )
        {
            throw new Exception( fsp__( 'Planner not found' ) );
        }

        return [
            'planner' => PlannerHelper::getPlanner( $planner ),
        ];
    }

    public static function list ( RestRequest $request ): array
    {
        $postId   = $request->param( 'wp_post_id', 0, RestRequest::TYPE_INTEGER );
        $page     = $request->param( 'page', 1, RestRequest::TYPE_INTEGER );
        $search   = $request->param( 'search', '', RestRequest::TYPE_STRING );
        $statuses = $request->param( 'statuses', [], RestRequest::TYPE_ARRAY, [ 'active', 'finished', 'paused' ] );

        if ( $postId > 0 )
        {
            $planners = Planner::where( 'status', 'active' )->whereFindInSet( 'selected_posts', $postId );
            $total    = $planners->count();
        } else
        {
            $page     = $page > 0 ? $page : 1;
            $planners = Planner::orderBy('id DESC');

            if ( !empty( $statuses ) )
            {
                $planners->where( 'status', 'in', $statuses );
            }

            if ( !empty( $search ) && empty( $postId ) )
            {
                $planners->where( 'title', 'like', '%' . $search . '%' );
            }

            $total    = $planners->count();
            $planners = $planners->offset( ( $page - 1 ) * 10 )->limit( 10 );
        }

        $plannerList = $planners->fetchAll();

        $items = [];
        foreach ( $plannerList as $planner )
        {
            $items[] = PlannerHelper::getPlanner( $planner );
        }

        return [
            'planners' => $items,
            'total'    => $total,
        ];
    }

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

        $planners = new Planner();

        if ( !$all && !empty( $include ) )
        {
            $planners->where( 'id', 'IN', $include );
        }

        if ( $all && !empty( $exclude ) )
        {
            $planners->where( 'id', 'NOT IN', $exclude );
        }

        if ( !empty( $filters[ 'search' ] ) && empty( $include ) )
        {
            $planners->where( 'title', 'like', '%' . $filters[ 'search' ] . '%' );
        }

        $planners->delete();
        Schedule::where( 'planner_id', 'not in', Planner::select( 'id' ) )->delete();

        return [];
    }

    /**
     * @throws Exception
     */
    public static function changeStatus ( RestRequest $request ): array
    {
        $planners = $request->require( "planners", RestRequest::TYPE_ARRAY, fsp__( "Please select planner(s)" ) );

        $ids = array_column( $planners, "id" );

        if ( count( $ids ) !== count( array_unique( $ids ) ) )
            throw new Exception( fsp__( "Duplicate IDs found in the planners" ) );

		$returnArray = [];

        foreach ( $planners as $plannerData )
        {
            $id        = $plannerData['id'];
            $newStatus = $plannerData['status'];

            if ( !in_array( $newStatus, [ "paused", "active" ] ) )
                throw new Exception( fsp__( "Invalid status provided" ) );

            $planner = Planner::get( $id );

            if ( !$planner )
                throw new Exception( fsp__( "Planner is not available" ) );

            if ( $planner->status === 'finished' )
                throw new Exception( fsp__( "Planner has already finished" ) );

            $updateArr = [ "status" => $newStatus ];

            if ( $newStatus === 'active' && $planner->share_type === 'interval' )
                $updateArr["next_execute_at"] = PlannerHelper::intervalNextExecuteTime( Date::epoch( $planner->start_at ), (int)$planner->schedule_interval );
			else if ( $newStatus === "active" && $planner->share_type === "weekly" )
                $updateArr["next_execute_at"] = PlannerHelper::weeklyNextExecuteTime( json_decode( $planner->weekly, true ) );

            Planner::where( "id", $id )->update( $updateArr );

			if( isset( $updateArr["next_execute_at"] ) )
			{
				$returnArray[] = [
					'id'                => $id,
					'next_execute_at'   => $updateArr["next_execute_at"]
				];
			}
        }

        return [
			'changes'   =>  $returnArray
        ];
    }

    /**
     * @throws Exception
     */
    public static function getSelectedPostsData ( RestRequest $request ): array
    {
        $postIds = $request->param( 'post_ids', [], RestRequest::TYPE_ARRAY );

        $response = [];

        if ( !empty( $postIds ) )
        {
            $postTypes = DB::DB()->get_results( DB::raw( 'SELECT DISTINCT post_type from ' . DB::WPtable( 'posts', true ) . ' where ID IN (' . implode( ',', array_fill( 0, count( $postIds ), '%s' ) ) . ')', $postIds ), 'ARRAY_A' );

            if ( empty( $postTypes ) )
            {
                throw new Exception( fsp__( 'Selected posts are not available' ) );
            }

            if ( count( $postTypes ) != 1 )
            {
                throw new Exception( fsp__( 'All posts must have same post type' ) );
            }

            $postType = reset( $postTypes )[ 'post_type' ];

            $response[ 'post_type' ] = [
                'label' => get_post_type_object( $postType )->label,
                'value' => $postType,
            ];

            $response[ 'posts' ] = PlannerHelper::wpFilterPosts( $postType, '', [], $postIds );
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public static function getPosts ( RestRequest $request ): array
    {
        $postType = $request->require( 'post_type', RestRequest::TYPE_STRING, fsp__( 'Please specify post type' ), array_keys( get_post_types() ) );
        $search   = $request->param( 'search', '', RestRequest::TYPE_STRING );

        return [ 'posts' => PlannerHelper::wpFilterPosts( $postType, $search, [], [], [], null, false, 10 ) ];
    }
}
