<?php

namespace FSPoster\App\SocialNetworks\Xing\Adapters;

use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\SocialNetworks\Xing\Api\PostingData;

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

        $postingData->channelType = $this->scheduleObj->getChannel()->channel_type;
        $postingData->remoteId = $this->scheduleObj->getChannel()->remote_id;
        $postingData->visibility = $this->scheduleObj->getSchedule()->customization_data_obj->visibility ?? 'public';
        $postingData->message = $this->getPostingDataMessage();
        $postingData->link = $this->getPostingDataLink();
        $postingData->uploadMedia = $this->getPostingDataUploadMedia();

        return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
    }

    public function getPostingDataMessage()
    {
        $scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

        $message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

	    $message = strip_tags( $message );
	    $message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

        return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
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

                /* Video + image mix edib upload ede bilmez, API desteklemir */
                if( empty( $path ) || ! ( empty( $mediaListToUpload ) || $mediaListToUpload[0]['type'] === $mimeType ) )
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

}