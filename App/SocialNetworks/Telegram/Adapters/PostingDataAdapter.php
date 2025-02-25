<?php

namespace FSPoster\App\SocialNetworks\Telegram\Adapters;


use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Telegram\Api\PostingData;

class PostingDataAdapter
{

	private ScheduleObject $scheduleObj;

	public function __construct( ScheduleObject $scheduleObj )
	{
		$this->scheduleObj = $scheduleObj;
	}

    public function getPostingData (): PostingData
    {
        $postingData = new PostingData();

	    $postingData->message           = $this->getPostingDataMessage();
		$postingData->silent            = $this->getPostingDataSilent();
	    $postingData->uploadMedia       = $this->getPostingDataUploadMedia();
        $postingData->addReadMoreBtn    = $this->getPostingDataAddReadMoreBtn();
        $postingData->readMoreBtnUrl    = $this->getPostingDataLink();
        $postingData->readMoreBtnText   = $this->getPostingDataAddReadMoreBtnText();

        return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
    }

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = strip_tags( $message, '<b><u><i><a>' );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		if ( Settings::get( 'telegram_cut_post_content', true ) )
		{
			$cutLen = $scheduleData->upload_media ? 1024 : 4096;

			if ( mb_strlen( $message, 'UTF-8' ) > $cutLen )
				$message = Helper::cutText( $message, $cutLen - 3 );
		}

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataLink()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( ! empty( $scheduleData->read_more_button_link ) )
			$link = $scheduleData->read_more_button_link;
		else
			$link = $this->scheduleObj->getPostLink();

		return apply_filters( 'fsp_schedule_post_link', $link, $this->scheduleObj );
	}

	public function getPostingDataAddReadMoreBtn()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return (bool)$scheduleData->add_read_more_button;
	}

	public function getPostingDataAddReadMoreBtnText()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return $scheduleData->read_more_button_text ?: fsp__( 'READ MORE' );
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

				if( empty( $path ) )
					continue;

				$mediaListToUpload[] = [
					'id'    =>  $mediaID,
					'type'  =>  strpos( $mimeType, 'video' ) !== false ? 'video' : 'image',
					'path'  =>  $path,
					'url'   =>  $url
				];
			}
		}

		return apply_filters( 'fsp_schedule_media_list_to_upload', $mediaListToUpload, $this->scheduleObj );
	}

	public function getPostingDataSilent ()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		return (bool)($scheduleData->silent_notifications ?? false);
	}

}