<?php

namespace FSPoster\App\SocialNetworks\Discord\Adapters;


use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Discord\Api\PostingData;

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

	    $postingData->channelId         = $this->scheduleObj->getChannel()->remote_id;
	    $postingData->message           = $this->getPostingDataMessage();
	    $postingData->uploadMedia       = $this->getPostingDataUploadMedia();

        return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
    }

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = strip_tags( $message, '<b><u><i><a>' );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		if ( Settings::get( 'discord_cut_post_text', true ) )
			$message = Helper::cutText( $message, 1997 );

		return apply_filters( 'fsp_schedule_post_content', trim( $message ), $this->scheduleObj );
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


}