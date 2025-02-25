<?php

namespace FSPoster\App\SocialNetworks\WordPress\Adapters;

use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\WordPress\Api\PostingData;

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

		$postingData->title = $this->getPostingDataTitle();
		$postingData->excerpt = $this->getPostingDataExcerpt();
		$postingData->message = $this->getPostingDataMessage();
		$postingData->postStatus = $this->getPostingDataPostStatus();
		$postingData->tags = $this->getPostingDataTags();
		$postingData->categories = $this->getPostingDataCategories();
		$postingData->uploadMedia = $this->getPostingDataUploadMedia();
		$postingData->postType = $this->getPostingDataPostType();
		$postingData->preservePostType = $this->getPostingDataPreservePostType();

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
		$message = nl2br( $message );

		return apply_filters( 'fsp_schedule_post_content', htmlspecialchars_decode($message), $this->scheduleObj );
	}

	public function getPostingDataExcerpt()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->excerpt ?? '' );

		$message = strip_tags( $message );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		return $message;
	}


	public function getPostingDataUploadMedia()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;
		$mediaListToUpload = [];

		if( $scheduleData->upload_media )
		{
			if( $scheduleData->upload_media_type === 'featured_image' )
				$mediaIDs = [ $this->scheduleObj->getPostThumbnailID() ];
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

				if( $mimeType !== 'image' )
					continue;

				$mediaListToUpload[] = [
					'id'    =>  $mediaID,
					'type'  =>  $mimeType,
					'path'  =>  $path,
					'url'   =>  $url
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
			$tags = array_map( fn ($el) => [
				'name' => $el['name'],
				'slug' => $el['slug']
			], $this->scheduleObj->getWpPostTerms( 'post_tag' ));
		else if( ! empty( $scheduleData->custom_tags ) && is_array( $scheduleData->custom_tags ) )
			$tags = $scheduleData->custom_tags;

		return $tags;
	}

	public function getPostingDataCategories()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$categories = [];

		if( ($scheduleData->send_categories ?? false) )
			$categories = array_map( fn ($el) => [
				'name' => $el['name'],
				'slug' => $el['slug']
			], $this->scheduleObj->getWpPostTerms( 'category' ));
		else if( ! empty( $scheduleData->custom_categories ) && is_array( $scheduleData->custom_categories ) )
			$categories = $scheduleData->custom_categories;

		return $categories;
	}

	public function getPostingDataPostStatus()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $scheduleData->post_status ?? 'publish';
	}

	public function getPostingDataPostType()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( ! empty( $scheduleData->post_type ) )
			return $scheduleData->post_type;

		$wpPostType = $this->scheduleObj->getWPPost()->post_type;

		return $wpPostType == 'fsp_post' ? 'post' : $wpPostType;
	}

	public function getPostingDataPreservePostType()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $scheduleData->preserve_post_type ?? true;
	}

}