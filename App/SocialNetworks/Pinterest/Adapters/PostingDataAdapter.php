<?php

namespace FSPoster\App\SocialNetworks\Pinterest\Adapters;

use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\SocialNetworks\Pinterest\Api\PostingData;

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

		$postingData->boardId = $this->scheduleObj->getChannel()->remote_id;
		$postingData->title = $this->getPostingDataTitle();
		$postingData->message = $this->getPostingDataMessage();
		$postingData->altText = $this->getPostingDataAltText();
		$postingData->link = $this->getPostingDataLink();
		$postingData->uploadMedia = $this->getPostingDataUploadMedia();

		if( empty( $postingData->uploadMedia ) && ! $this->scheduleObj->readOnlyMode )
			throw new ScheduleShareException( fsp__( 'An image is required to pin on board' ) );

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getPostingDataTitle()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$title = $this->scheduleObj->replaceShortCodes( $scheduleData->post_title ?? '' );

		$title = strip_tags( $title );
		$title = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $title );

		if ( mb_strlen( $title ) > 100 )
			$title = Helper::cutText( $title, 97 );

		return $title;
	}

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = strip_tags( $message );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		if ( mb_strlen( $message ) > 500 )
			$message = Helper::cutText( $message, 497 );

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataAltText()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$altText = $this->scheduleObj->replaceShortCodes( $scheduleData->alt_text ?? '' );

		$altText = strip_tags( $altText );
		$altText = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $altText );

		if ( mb_strlen( $altText ) > 500 )
			$altText = Helper::cutText( $altText, 497 );

		return $altText;
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

		if( $scheduleData->upload_media_type === 'featured_image' )
		{
			$mediaIDs = [ $this->scheduleObj->getPostThumbnailID() ];
		}
		else if ( $scheduleData->upload_media_type === 'all_images' )
		{
			$mediaIDs = $this->scheduleObj->getPostAllAttachedImagesID();
			$imageCount = (int)Settings::get( 'pinterest_max_images_count', 1 );
			$mediaIDs = array_slice( $mediaIDs, 0, $imageCount );
		}
		else
		{
			$mediaIDs = $scheduleData->media_list_to_upload ?? [];
		}

		foreach ( $mediaIDs AS $mediaID )
		{
			if( ! ( $mediaID > 0 ) )
				continue;

			$path = WPPostThumbnail::getOrCreateImagePath( $mediaID, $this->scheduleObj->readOnlyMode );
			$url = wp_get_attachment_url( $mediaID );
			$mimeType = get_post_mime_type( $mediaID );
			$mimeType = strpos( $mimeType, 'video' ) !== false ? 'video' : 'image';

			if( empty( $path ) || $mimeType !== 'image' )
				continue;

			$mediaListToUpload[] = [
				'id'    =>  $mediaID,
				'type'  =>  $mimeType,
				'path'  =>  $path,
				'url'   =>  $url
			];
		}

		return apply_filters( 'fsp_schedule_media_list_to_upload', $mediaListToUpload, $this->scheduleObj );
	}

}