<?php

namespace FSPoster\App\SocialNetworks\Tumblr\Adapters;

use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Tumblr\Api\PostingData;

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

		$postingData->blogId = $this->scheduleObj->getChannel()->remote_id;
		$postingData->title = $this->getPostingDataTitle();
		$postingData->message = $this->getPostingDataMessage();
		$postingData->tags = $this->getPostingDataTags();
		$postingData->link = $this->getPostingDataLink();
		$postingData->uploadMedia = $this->getPostingDataUploadMedia();

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

		$message = strip_tags( $message );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		return apply_filters( 'fsp_schedule_post_content', htmlspecialchars_decode($message), $this->scheduleObj );
	}

	public function getPostingDataLink()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$link = '';

		if( $scheduleData->attach_link )
		{
			if( ! empty( $scheduleData->custom_link ) )
				$link = $scheduleData->custom_link;
			else
				$link = $this->scheduleObj->getPostLink();
		}

		return apply_filters( 'fsp_schedule_post_link', $link, $this->scheduleObj );
	}

	public function getPostingDataUploadMedia()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;
		$mediaListToUpload = [];

		if( $scheduleData->upload_media )
		{
			if( $scheduleData->upload_media_type === 'featured_image' )
				$mediaIDs = [ $this->scheduleObj->getPostThumbnailID() ];
			else if( $scheduleData->upload_media_type === 'all_images' )
				$mediaIDs = $this->scheduleObj->getPostAllAttachedImagesID();
			else
				$mediaIDs = $scheduleData->media_list_to_upload ?? [];

			foreach ( $mediaIDs AS $mediaID )
			{
				if( ! ( $mediaID > 0 ) )
					continue;

				$path = WPPostThumbnail::getOrCreateImagePath( $mediaID, $this->scheduleObj->readOnlyMode );
				$url = wp_get_attachment_url( $mediaID );
				$mimeType = get_post_mime_type( $mediaID );
				$mimeType = strpos( $mimeType, 'video' ) !== false ? 'video' : 'image';

				if( empty( $url ) || $mimeType !== 'image' )
					continue;

				$mediaListToUpload[] = [
					'id'    =>  $mediaID,
					'type'  =>  $mimeType,
					'url'   =>  $url,
					'path'  =>  $path
				];
			}
		}

		return apply_filters( 'fsp_schedule_media_list_to_upload', $mediaListToUpload, $this->scheduleObj );
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