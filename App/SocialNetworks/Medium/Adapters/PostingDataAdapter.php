<?php

namespace FSPoster\App\SocialNetworks\Medium\Adapters;

use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Medium\Api\PostingData;

class PostingDataAdapter
{

	private ScheduleObject $scheduleObj;

	public function __construct( ScheduleObject $scheduleObj )
	{
		$this->scheduleObj = $scheduleObj;
	}

	/**
	 * @return PostingData
	 */
	public function getPostingData (): PostingData
	{
		$postingData = new PostingData();

		$postingData->channelId = $this->scheduleObj->getChannel()->remote_id;
		$postingData->channelType = $this->scheduleObj->getChannel()->channel_type;
		$postingData->title = $this->getPostingDataTitle();
		$postingData->message = $this->getPostingDataMessage();
		$postingData->tags = $this->getPostingDataTags();

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getPostingDataTitle()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$title = $this->scheduleObj->replaceShortCodes( $scheduleData->post_title ?? '' );

		$title = strip_tags( $title );
		$title = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $title );

		return $title;
	}

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );
		$message = str_replace( "\n", "<br>", $message );
		$message = preg_replace( '/(<noscript>.*?<\/noscript>)/s', '', $message );

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataTags()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$tags = [];

		if( ($scheduleData->send_tags ?? false) )
			$tags = array_column( $this->scheduleObj->getWpPostTerms( 'post_tag' ), 'name' );
		else if( ! empty( $scheduleData->custom_tags ) && is_array( $scheduleData->custom_tags ) )
			$tags = $scheduleData->custom_tags;

		return $tags;
	}
}