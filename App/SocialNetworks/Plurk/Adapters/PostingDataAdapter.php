<?php

namespace FSPoster\App\SocialNetworks\Plurk\Adapters;

use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Plurk\Api\PostingData;

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

		$postingData->message = $this->getPostingDataMessage();
		$postingData->qualifier = $this->getPostingDataQualifier();

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = strip_tags( $message );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		if ( Settings::get( 'plurk_cut_post_content', true ) && mb_strlen( $message ) > 360 )
			$message = Helper::cutText( $message, 357 );

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataQualifier()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $scheduleData->qualifier ?? ':';
	}

}