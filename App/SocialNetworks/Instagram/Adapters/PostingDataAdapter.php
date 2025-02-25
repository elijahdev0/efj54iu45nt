<?php

namespace FSPoster\App\SocialNetworks\Instagram\Adapters;

use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\SocialNetworks\Instagram\Api\PostingData;
use FSPoster\App\SocialNetworks\Instagram\Helpers\InstagramHelper;

class PostingDataAdapter
{

	private ScheduleObject $scheduleObj;

	public function __construct( ScheduleObject $scheduleObj )
	{
		$this->scheduleObj = $scheduleObj;
	}

	public function __destruct()
	{
		foreach ( InstagramHelper::$recycle_bin as $image )
		{
			if( file_exists( $image ) )
				unlink( $image );
		}
	}

	/**
	 * @return PostingData
	 */
	public function getPostingData (): PostingData
	{
		$postingData = new PostingData();

		$postingData->edge = $this->getEdge();
		$postingData->ownerId = $this->scheduleObj->getChannel()->remote_id;
		$postingData->message = $this->getPostingDataMessage();
		$postingData->link = $this->getPostingDataLink();
		$postingData->linkConfig = $this->getPostingDataLinkConfig();
		$postingData->uploadMedia = $this->getPostingDataUploadMedia( $postingData->message, $postingData->link );
		$postingData->firstComment = $this->getPostingDataFirstComment();
		$postingData->pinThePost = $this->getPostingDataPinThePost();
		$postingData->storyHashtag = $this->getPostingDataStoryHashtag();
		$postingData->storyHashtagConfig = $this->getPostingDataStoryHashtagConfig();

		if ( ! $this->scheduleObj->readOnlyMode && empty( $postingData->uploadMedia ) )
			throw new ScheduleShareException( fsp__( 'An image/video is required to share a post on Instagram.' ) );

		if ( ! $this->scheduleObj->readOnlyMode && $postingData->edge === 'story' && $postingData->uploadMedia[0]['type'] === 'video' && $this->getMethod() === 'cookie' )
			throw new ScheduleShareException( fsp__( 'The cookie method doesn\'t support sharing videos on stories' ) );

		$postingData->uploadMedia = array_slice($postingData->uploadMedia, 0, 10);

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getEdge()
	{
		$channelType = $this->scheduleObj->getChannel()->channel_type;

		return $channelType === 'account_story' ? 'story' : 'feed';
	}

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = strip_tags( $message );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		if ( Settings::get( 'instagram_cut_post_text', true ) )
			$message = Helper::cutText( $message, 2200 - 3 );

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataLink()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$link = '';

		if( $scheduleData->attach_link && $this->getEdge() === 'story' && $this->getMethod() === 'login_pass' )
		{
			if( ! empty( $scheduleData->custom_link ) )
				$link = $scheduleData->custom_link;
			else
				$link = $this->scheduleObj->getPostLink();
		}

		return apply_filters( 'fsp_schedule_post_link', $link, $this->scheduleObj );
	}

	public function getPostingDataLinkConfig()
	{
		return [
			'top_offset' => (float) Settings::get('instagram_story_customization_link_top_offset', 1000)
		];
	}

	public function getPostingDataUploadMedia( $message, $link )
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
				if( empty( $url ) || ! ( empty( $mediaListToUpload ) || $mediaListToUpload[0]['type'] === $mimeType ) )
					continue;

				$mediaListToUpload[] = [
					'id'    =>  $mediaID,
					'type'  =>  $mimeType,
					'url'   =>  $url,
					'path'  =>  $path,
				];
			}
		}

		if( ! $this->scheduleObj->readOnlyMode )
		{
			if( ! empty( $mediaListToUpload ) && $mediaListToUpload[0]['type'] === 'image' )
			{
				foreach ( $mediaListToUpload AS $i => $photo )
				{
					if( $this->getEdge() === 'story' )
					{
						try
						{
							$mediaListToUpload[$i] = InstagramHelper::imageForStory( $photo[ 'path' ], $message, $link, $this->getMethod() );
						}
						catch ( \Exception $e )
						{
							throw new ScheduleShareException( $e->getMessage() );
						}
					}
					else
					{
						try
						{
							$mediaListToUpload[$i] = InstagramHelper::imageForFeed( $photo['path'] );
						}
						catch ( \Exception $e )
						{
							throw new ScheduleShareException( $e->getMessage() );
						}
					}

					$mediaListToUpload[$i]['id'] = $photo['id'];
					$mediaListToUpload[$i]['type'] = $photo['type'];
					$mediaListToUpload[$i]['original_url'] = $photo['url'];
					$mediaListToUpload[$i]['original_path'] = $photo['path'];
				}
			}
			else if( ! empty( $mediaListToUpload ) && $mediaListToUpload[0]['type'] === 'video' )
			{
				if( count( $mediaListToUpload ) > 1 )
					throw new ScheduleShareException( fsp__('You can only share 1 video') );

				$firstVideo = $mediaListToUpload[0];

				try
				{
					$mediaListToUpload[0] = InstagramHelper::renderVideo( $firstVideo['path'], $this->getEdge() );
				}
				catch ( \Exception $e )
				{
					throw new ScheduleShareException( $e->getMessage() );
				}

				$mediaListToUpload[0]['id'] = $firstVideo['id'];
				$mediaListToUpload[0]['type'] = $firstVideo['type'];
				$mediaListToUpload[0]['original_url'] = $firstVideo['url'];
				$mediaListToUpload[0]['original_path'] = $firstVideo['path'];
			}
		}

		return apply_filters( 'fsp_schedule_media_list_to_upload', $mediaListToUpload, $this->scheduleObj );
	}

	public function getPostingDataFirstComment()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$firstComment = $this->scheduleObj->replaceShortCodes( $scheduleData->first_comment ?? '' );
		$firstComment = strip_tags( $firstComment );
		$firstComment = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $firstComment );

		return $firstComment;
	}

	public function getPostingDataPinThePost ()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( $this->getMethod() === 'login_pass' && $this->getEdge() === 'feed' )
			return (bool)$scheduleData->pin_the_post ?? false;

		return false;
	}

	public function getPostingDataStoryHashtag()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		if( $this->getMethod() === 'login_pass' && $this->getEdge() === 'story' )
			return $scheduleData->story_hashtag ?? '';

		return '';
	}

	public function getPostingDataStoryHashtagConfig()
	{
		return [
			'top_offset' => (float) Settings::get( 'instagram_story_customization_hashtag_top_offset', 700 )
		];
	}

	private function getMethod()
	{
		return $this->scheduleObj->getChannelSession()->method;
	}


}