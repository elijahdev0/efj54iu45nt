<?php

namespace FSPoster\App\Providers\Planners;

use FSPoster\App\Models\Planner;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Pages\Channels\ChannelHelper;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;

class PlannerHelper
{
    /** @param Planner $planner */
    public static function getPlanner ( Collection $planner ): array
    {
        $hasDateRangeFilters = !empty( $planner->post_filters_date_range_to ) && !empty( $planner->post_filters_date_range_from );
        $hasPostFilters      = $hasDateRangeFilters || !empty( $planner->post_filters_term ) || $planner->post_type === 'product';
        $channelIds          = empty( $planner->channels ) ? [] : explode( ',', $planner->channels );

        $postIds = empty( $planner->selected_posts ) ? [] : explode( ',', $planner->selected_posts );

        $selectedPosts = empty( $postIds ) ? [] : self::wpFilterPosts( $planner->post_type, '', [], $postIds );

        return [
            'id'                 => !empty( $planner->id ) ? (int)$planner->id : null,
            'title'              => $planner->title ?? null,
            'start_at'           => !empty( $planner->start_at ) ? Date::epoch( $planner->start_at ) : null,
            'next_execute_at'    => !empty( $planner->next_execute_at ) ? Date::epoch( $planner->next_execute_at ) : null,
            'channels'           => ChannelHelper::getPartialChannels( $channelIds, empty( $planner->id ) ),
            'customization_data' => !empty( $planner->id ) ? json_decode( $planner->customization_data, true ) : [],
            'schedules_count'    => !empty( $planner->id ) ? (int)Schedule::where( 'planner_id', $planner->id )->where( 'status', '<>', 'sending' )->count() : null,
            'status'             => $planner->status ?? null,
            'post_type'          => $planner->post_type,
            'share_type'         => $planner->share_type ?? null,
            'selected_posts'     => $selectedPosts ?: [],
            'post_filters'       => $hasPostFilters ? [
                'date_range'                    => $hasDateRangeFilters ? [
                    'from' => Date::epoch( $planner->post_filters_date_range_from ),
                    'to'   => Date::epoch( $planner->post_filters_date_range_to ),
                ] : null,
                'term'                          => !empty( $planner->post_filters_term ) ? (int)$planner->post_filters_term : null,
                'term_name'                     => !empty( $planner->post_filters_term ) ? get_term((int)$planner->post_filters_term)->name : null,
                'skip_out_of_stock_products'    => $planner->post_type === 'product' ? ( $planner->post_filters_skip_oos_products ?? true ) : null,
            ] : null,
            'schedule_interval'  => !empty( $planner->schedule_interval ) ? [
                'value'      => Date::convertFromSecondsToUnit( (int)$planner->schedule_interval ),
                'sleep_time' => ( empty( $planner->sleep_time_start ) || empty( $planner->sleep_time_end ) ) ? null : [
                    'start' => $planner->sleep_time_start,
                    'end'   => $planner->sleep_time_end,
                ],
            ] : null,
            'weekly'             => !empty( $planner->weekly ) ? json_decode( $planner->weekly, true ) : null,
            'sort_by'            => $planner->sort_by ?? null,
            'repeating'          => isset( $planner->repeating ) ? (bool)$planner->repeating : null,
        ];
    }

	public static function intervalNextExecuteTime ( int $startAt, int $interval )
	{
		$nextScheduleAt = $startAt;

		/**
		 * Start at eger kechmish tarix daxil edilirse ve ya movcud schedule edit edilirse bura girecek.
		 */
		if( $nextScheduleAt < Date::epoch() )
		{
			$dif = Date::epoch() - $nextScheduleAt;
			$remain = $dif % $interval;
			/**
			 * Verilmish Start at-e ve Intervala uygun cari zamana qeder sonuncu schedule time hesablayirig.
			 * Meselen: StartAt = 2024-08-25 09:00, interval = 1 saat, now = 2024-09-02 12:30. Bu casede ashagidaki variable 2024-09-02 12:00 olacag.
			 */
			$nextScheduleAt = Date::epoch() - $remain;

			/**
			 * $remain yalniz 1 halda 0 ola biler, oda meselen yuxaridaki casede now saati tam olarag 12:00 olsaydi.
			 * Diger hallar uchun, hesabladigimiz son schedule time`in uzerine itnerval elave edirik ki, kechmish tarixe gore schedule etmesin.
			 * Meselen yuxaridaki casede nextScheduleAt = 2024-09-02 13:00 olacag.
			 */
			if( $remain > 0 )
				$nextScheduleAt += $interval;
		}

		return Date::dateTimeSQL( $nextScheduleAt );
	}

    public static function weeklyNextExecuteTime ( array $weekly, string $now = '' )
    {
		if( empty( $now ) )
			$now = Date::dateTimeSQL();

        $weekDays = [ 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7 ];

        $dates = [];

        foreach ( $weekly as $weekDayName => $times )
        {
            foreach ( $times as $time )
            {
                $nextWeekDay = $weekDays[ $weekDayName ] === Date::week( $now ) && Date::time( $now ) < $time ? 'today' : ( 'next ' . $weekDayName );
                $dates[]     = Date::epoch( $now, $nextWeekDay . ' ' . $time );
            }
        }

        sort( $dates );

        return Date::dateTimeSQL( reset( $dates ) );
    }

	// doit bu funksiyalarda bu qeder argument olamsi duzgun deil, seliqeli etmek lazimdi. Elave olaarag eyni ishi hem WP post querysi ile hemde raw query ile edir. ashagidaki 2 ferqli method ashagi yuxari eyni ishi gorur. onlari birleshdirmek lazimdi.
    public static function wpFilterPosts ( $postType, $search = '', $dateRange = [], $selectedPosts = [], $exclude = [], $term = null, $skipOOSProducts = false, $returnCount = 0 ): array
    {
        $page  = 1;
        $limit = 10;
        $args  = [
            'post_type'           => $postType,
            'posts_per_page'      => $limit,
            'paged'               => $page,
            'include'             => $selectedPosts,
            'exclude'             => $exclude,
            'ignore_sticky_posts' => true,
        ];

        if ( !empty( $search ) )
        {
            $args[ 's' ] = $search;
        }

        if ( !empty( $term ) )
        {
            $args[ 'tax_query' ] = [
                [
                    'field'            => 'term_taxonomy_id',
                    'terms'            => $term,
                    'include_children' => false,
                ],
            ];
        }

        if ( !empty( $dateRange ) )
        {
            $args[ 'date_query' ] = [
                [
                    'column'    => 'post_date_gmt',
                    'before'    => $dateRange[ 'to' ],
                    'after'     => $dateRange[ 'from' ],
                    'compare'   => 'BETWEEN',
                    'inclusive' => true,
                ],
            ];
        }

        if ( $postType === 'product' && $skipOOSProducts )
        {
            $args[ 'meta_key' ]     = '_stock_status';
            $args[ 'meta_value' ]   = 'outofstock';
            $args[ 'meta_compare' ] = '<>';
        }

        $response = [];

        $postsCount = 0;
        do
        {
            $posts = get_posts( $args );

            foreach ( $posts as $post )
            {
                $postsCount++;
                $response[] = [
                    'id'    => $post->ID,
                    'title' => $post->post_title,
                    'link'  => $post->guid,
                ];

                if ( $returnCount > 0 && $postsCount === $returnCount )
                {
                    break;
                }
            }

            $page++;
            $args[ 'paged' ] = $page;
        } while ( !empty( $posts ) && count( $posts ) == $limit );

		if (is_numeric($search)) {
			$postById = get_post($search);

			if (!empty($postById)) {
				$response[] = [
					'id'    => $postById->ID,
					'title' => $postById->post_title,
					'link'  => $postById->guid
				];
			}
		}

        return $response;
    }

    public static function postFilterQuery ( $postType, $dateRange = [], $selectedPosts = [], $exclude = [], $term = null, $skipOOSProducts = true ): string
    {
        $query = 'post_type=\'' . $postType . '\' ';

        if ( $postType === 'product' && $skipOOSProducts )
            $query .= 'AND IFNULL((SELECT DISTINCT `meta_value` FROM `' . DB::WPtable( 'postmeta', true ) . '` WHERE `post_id`=tb1.id AND `meta_key`=\'_stock_status\'), \'\')<>\'outofstock\' ';

        /* Categories filter */

        $terms = [];
        if ( !empty( $term ) )
        {
            $termInfo = get_term( (int)$term );

            if ( !empty( $termInfo ) )
            {
                $terms[] = $term;

                // get sub categories
                $childTerms = get_categories( [
                    'taxonomy'   => $termInfo->taxonomy,
                    'child_of'   => (int)$term,
                    'hide_empty' => false,
                ] );

                foreach ( $childTerms as $childTerm )
                {
                    $terms[] = (int)$childTerm->term_id;
                }
            }
        }

        if ( !empty( $terms ) )
        {
            $getTerms = DB::DB()->get_col( DB::DB()->prepare( 'SELECT `term_taxonomy_id` FROM `' . DB::WPtable( 'term_taxonomy', true ) . '` WHERE `term_id` IN (' . implode( ',', array_fill( 0, count( $terms ), '%s' ) ) . ')', ...$terms ), 0 );

            $query .= 'AND `id` IN ( SELECT object_id FROM `' . DB::WPtable( 'term_relationships', true ) . '` WHERE term_taxonomy_id IN (\'' . implode( '\' , \'', $getTerms ) . '\') ) ';
        }
        /* / End of Categories filter */

        /* post_date_filter */
        if ( !empty( $dateRange ) )
            $query .= 'AND post_date_gmt BETWEEN \'' . $dateRange[ 'from' ] . '\' AND \'' . $dateRange[ 'to' ] . '\' ';
        /* End of post_date_filter */

        /* Filter by id */
        if ( !empty( $selectedPosts ) )
            $query .= 'AND `ID` IN ( \'' . implode( '\',\'', $selectedPosts ) . '\' ) ';

        if ( !empty( $exclude ) )
            $query .= 'AND `ID` NOT IN ( \'' . implode( '\',\'', $exclude ) . '\' ) ';

        return $query;
    }

    public static function plannerFilters ( $postType, $dateRange = [], $selectedPosts = [], $term = null, $skipOOSProducts = true, $sortBy = 'random', $alreadySharedPosts = null ): string
    {
        $sortQuery = '';

        if ( $sortBy === 'old_to_new' )
        {
            $lastSharedPost = DB::DB()->get_row( 'SELECT ID, post_date_gmt FROM ' . DB::WPtable( 'posts', true ) . ' where FIND_IN_SET(`ID`, \'' . $alreadySharedPosts . '\') ORDER BY post_date_gmt DESC, ID DESC LIMIT 1', ARRAY_A );

            if ( !empty( $lastSharedPost[ 'ID' ] ) )
                $sortQuery .= ' AND ((post_date_gmt = \'' . $lastSharedPost[ 'post_date_gmt' ] . '\' AND ID > ' . $lastSharedPost[ 'ID' ] . ' ) OR post_date_gmt > \'' . $lastSharedPost[ 'post_date_gmt' ] . '\') ';

            $sortQuery .= 'ORDER BY post_date ASC, ID ASC';
        } else if ( $sortBy === 'new_to_old' )
        {
            $lastSharedPost = DB::DB()->get_row( 'SELECT ID, post_date_gmt FROM ' . DB::WPtable( 'posts', true ) . ' where FIND_IN_SET(`ID`, \'' . $alreadySharedPosts . '\') ORDER BY post_date_gmt, ID LIMIT 1', ARRAY_A );

            if ( !empty( $lastSharedPost[ 'ID' ] ) )
                $sortQuery .= ' AND ((post_date_gmt = \'' . $lastSharedPost[ 'post_date_gmt' ] . '\' AND ID < ' . $lastSharedPost[ 'ID' ] . ' ) OR post_date_gmt < \'' . $lastSharedPost[ 'post_date_gmt' ] . '\') ';

            $sortQuery .= ' ORDER BY post_date DESC, ID DESC';
        } else
        {
            $sortQuery = ' ORDER BY RAND()';
        }

        $exclude = empty( $alreadySharedPosts ) ? [] : explode( ',', $alreadySharedPosts );

        return self::postFilterQuery( $postType, $dateRange, $selectedPosts, $exclude, $term, $skipOOSProducts ) . ' ' . $sortQuery;
    }

    public static function isSleepTime ( $planner ): bool
    {
        if ( !empty( $planner[ 'sleep_time_start' ] ) && !empty( $planner[ 'sleep_time_end' ] ) )
        {
            $currentTimestamp = Date::epoch();
            $sleepTimeStart   = Date::epoch( Date::dateSQL() . ' ' . $planner[ 'sleep_time_start' ] );
            $sleepTimeEnd     = Date::epoch( Date::dateSQL() . ' ' . $planner[ 'sleep_time_end' ] );

            return Date::isBetweenDates( $currentTimestamp, $sleepTimeStart, $sleepTimeEnd );
        }

        return false;
    }
}