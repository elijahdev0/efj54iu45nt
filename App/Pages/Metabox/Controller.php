<?php

namespace FSPoster\App\Pages\Metabox;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Schedules\ScheduleService;

class Controller
{
    public static function setAutoShareStatus ( RestRequest $request ): array
    {
        $wpPostId     = $request->param( 'wp_post_id', 0, RestRequest::TYPE_INTEGER );
        $shareChecked = (int)$request->param( 'auto_share', false, RestRequest::TYPE_BOOL );

		if( $shareChecked )
			ScheduleService::enableAutoShareForWpPost( $wpPostId );
		else
			ScheduleService::disableAutoShareForWpPost( $wpPostId );

        return [];
    }

    public static function get ( RestRequest $request ): array
    {
        $wpPostId = $request->param( 'wp_post_id', 0, RestRequest::TYPE_INTEGER );

        if ( !( $wpPostId > 0 ) )
            return [];

        $autoShareOn = (bool)( metadata_exists( 'post', $wpPostId, 'fsp_enable_auto_share' ) ? get_post_meta( $wpPostId, 'fsp_enable_auto_share', true ) : Settings::get( 'auto_share', true ) );
		$schedulesAlreadySharedForThisWpPost = metadata_exists( 'post', $wpPostId, 'fsp_runned_for_this_post' );
		$scheduleGroupId = get_post_meta( $wpPostId, 'fsp_schedule_group_id', true );
	    $metaboxData = [];

	    if ( ! empty( $scheduleGroupId ) )
	    {
		    $existingSchedules = Schedule::where( 'wp_post_id', $wpPostId )
		                                 ->where( 'group_id', $scheduleGroupId )
		                                 ->fetchAll();

		    foreach ( $existingSchedules as $schedule )
		    {
			    $metaboxData[] = [
				    'channel_id'       => $schedule->channel_id,
				    'custom_post_data' => json_decode( $schedule->customization_data, true ),
			    ];
		    }
	    }
		else
		{
			$metaboxData = ScheduleService::getDefaultSchedulesPostData( $wpPostId );
		}

        return [
			''  =>  $schedulesAlreadySharedForThisWpPost,
            'auto_share'   => $autoShareOn,
            'metabox_data' => $metaboxData,
        ];
    }

    /**
     * @throws Exception
     */
    public static function save ( RestRequest $request ): array
    {
        $wpPostId  = $request->param( 'wp_post_id', 0, RestRequest::TYPE_INTEGER );
        $schedules = $request->param( 'schedules', [], RestRequest::TYPE_ARRAY );

        if ( empty( $schedules ) )
        {
            throw new Exception( fsp__( 'Schedules can\'t be empty' ) );
        }

        $channelIds = array_column( $schedules, 'channel_id' );

        if ( empty( $channelIds ) )
        {
            throw new Exception( fsp__( 'Please select channels' ) );
        }

        $channelsCount = Channel::where( 'id', $channelIds )->count();

        if ( count( $channelIds ) !== $channelsCount )
        {
            throw new Exception( fsp__( 'You don\'t have access to all the selected channels. Please refresh the page.' ) );
        }

        $post = get_post( $wpPostId );

        if ( !$post )
        {
            throw new Exception( fsp__( 'Post not found' ) );
        }

	    $scheduleGroupId = get_post_meta( $wpPostId, 'fsp_schedule_group_id', true );
		$isEdit = ! empty( $scheduleGroupId );

	    if( $isEdit )
	    {
			ScheduleService::deleteSchedulesFromWpPost( $wpPostId, $scheduleGroupId );
	    }

        $schedulesGroupId = ScheduleService::createSchedulesFromWpPost( $wpPostId, $scheduleGroupId, $schedules );

		if( ! $isEdit )
		{
			update_post_meta( $wpPostId, 'fsp_enable_auto_share', 1 );
			update_post_meta( $wpPostId, 'fsp_schedule_group_id', $schedulesGroupId );
		}

	    /*
		 * Schedulenin avtomatik olarag (WP hook ile) yaradilmadigini, manual yaradildigini yadda saxlayir.
		 * Ona gore yadda saxlayir ki, tutag ki, adam scehdule etdi WP Postu 1 hefte sonraya. Sabah girdi ve WP Postda category sechdi,
		 * hansi ki, hemen categoryli Postu bashga bir channelede paylashmag lazimdi.
		 * Bu halda biz baxirig, eger avtomatik created scehduledirse o halda silib tezden yaradacayig scheduleleri ki, yeni channelda elave edilsin.
		 * Yox eger user metaboxdan Customise buttonuna basib, editler edib save basibsa o halda bu meta-data olmayacag ve sistem bilecek ki,
		 * bu bazadki scheduleler user terefinden teyin olunub. Artig novbeti save hooklarinda o schedulelere deyilmeyecek.
		 * Yalniz tarixi ve statusu deyishe biler o scehdulelerin, amma hech bir halda silinib yeni channellerle elave edilmeyecek.
		 * Chunki user manual Customise edende istediyi channellari seche biler, ona biz artig qarishmayacayig.
		*/
	    update_post_meta( $wpPostId, 'fsp_schedule_created_manually', 1 );

        return [ 'schedule_group_id' => $schedulesGroupId ];
    }

}