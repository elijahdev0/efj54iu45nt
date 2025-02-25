<?php

namespace FSPoster\App\SocialNetworks\Blogger\Adapters;

use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Blogger\Api\PostingData;

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

		$postingData->blogId    = $this->scheduleObj->getChannel()->remote_id;
		$postingData->authorId  = $this->scheduleObj->getChannelSession()->remote_id;
		$postingData->isDraft   = $this->getPostingDataIsDraft();
		$postingData->title     = $this->getPostingDataTitle();
		$postingData->content   = $this->getPostingDataContent();
		$postingData->labels    = $this->getPostingDataLabels();
		$postingData->kind      = $this->getPostingDataIKind();

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getPostingDataIsDraft ()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return (bool)$scheduleData->is_draft;
	}

	public function getPostingDataIKind ()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( ! empty( $scheduleData->kind ) )
			return $scheduleData->kind;

		if( $scheduleData->send_pages_as_page && $this->scheduleObj->getWPPost()->post_type === 'page' )
			return 'page';

		return 'post';
	}

	public function getPostingDataTitle()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $this->scheduleObj->replaceShortCodes( $scheduleData->post_title ?? '' );
	}

	public function getPostingDataContent()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = str_replace( "\n", '<br>', $message );

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataLabels()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( ! empty( $scheduleData->custom_labels ) && is_array( $scheduleData->custom_labels ) )
		{
			$labels = $scheduleData->custom_labels;
		}
		else
		{
			$labels = [];

			if ( $scheduleData->send_categories )
				$labels = array_merge( $labels, array_column( $this->scheduleObj->getWpPostTerms( 'category' ), 'name' ) );

			if ( $scheduleData->send_tags )
				$labels = array_merge( $labels, array_column( $this->scheduleObj->getWpPostTerms( 'post_tag' ), 'name' ) );
		}

		$labels_cut = [];
		foreach ( $labels as $label )
		{
			if ( strlen( implode( ',', array_merge( $labels_cut, [ $label ] ) ) ) > 200 )
				break;

			$labels_cut[] = $label;
		}

		return $labels_cut;
	}

}