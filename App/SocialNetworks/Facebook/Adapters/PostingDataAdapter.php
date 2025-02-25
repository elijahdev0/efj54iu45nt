<?php

namespace FSPoster\App\SocialNetworks\Facebook\Adapters;

use FSPoster\App\Libraries\PHPImage\PHPImage;
use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\SocialNetworks\Facebook\Api\PostingData;
use FSPoster\App\SocialNetworks\Facebook\Helpers\PersianStringDecorator;

class PostingDataAdapter
{

	private ScheduleObject $scheduleObj;
	private $removeFilesOnDestruct = [];

	public function __construct( ScheduleObject $scheduleObj )
	{
		$this->scheduleObj = $scheduleObj;
	}

	public function __destruct()
	{
		foreach ( $this->removeFilesOnDestruct AS $filePath )
		{
			unlink( $filePath );
		}
	}

	/**
	 * @return PostingData
	 */
	public function getPostingData (): PostingData
	{
		$postingData = new PostingData();

		$posterId = $this->scheduleObj->getChannel()->custom_settings_obj['group_poster'];// doit ?

		$postingData->edge = $this->getEdge();
		$postingData->ownerId = $this->scheduleObj->getChannel()->remote_id;
		$postingData->posterId = !empty( $posterId ) ? Channel::get( $posterId )->remote_id : null;
		$postingData->channelType = str_replace( '_story', '', $this->scheduleObj->getChannel()->channel_type );
		$postingData->message = $this->getPostingDataMessage();
		$postingData->link = $this->getPostingDataLink();
		$postingData->uploadMedia = $this->getPostingDataUploadMedia();
		$postingData->firstComment = $this->getPostingDataFirstComment();

		if( $postingData->edge === 'story' && ! $this->scheduleObj->readOnlyMode )
		{
			if ( $this->scheduleObj->getChannelSession()->method === 'app' )
				throw new ScheduleShareException( fsp__( 'Facebook API does not support sharing posts on the story so that accounts have to be added to the plugin via the cookie method to share posts on the story.' ) );

			if ( $postingData->channelType === 'group' )
				throw new ScheduleShareException( fsp__( 'Sharing stories on groups is not supported.' ) );

			if ( empty( $postingData->uploadMedia ) )
				throw new ScheduleShareException( fsp__( 'Error! An image is required to share a story on Facebook. Please add media to the post.' ) );

			if( $postingData->uploadMedia[0]['type'] === 'video' )
				throw new ScheduleShareException( fsp__( 'Sharing video on stories is not supported.' ) );

			try
			{
				$storyMedia = $this->createImageForStory( $postingData->uploadMedia[0]['path'], $postingData->message );
			}
			catch ( \Exception $e )
			{
				throw new ScheduleShareException( $e->getMessage() );
			}

			if ( empty( $storyMedia ) )
				throw new ScheduleShareException( fsp__('The image resolution is too large') );

			$postingData->uploadMedia = [ $storyMedia ];
		}

		return apply_filters( 'fsp_schedule_posting_data', $postingData, $this->scheduleObj );
	}

	public function getEdge()
	{
		$channelType = $this->scheduleObj->getChannel()->channel_type;

		return in_array( $channelType, ['account_story', 'ownpage_story'] ) ? 'story' : 'feed';
	}

	public function getPostingDataMessage()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$message = $this->scheduleObj->replaceShortCodes( $scheduleData->post_content ?? '' );

		$message = strip_tags( $message );
		$message = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $message );

		return apply_filters( 'fsp_schedule_post_content', $message, $this->scheduleObj );
	}

	public function getPostingDataFirstComment()
	{
		$scheduleData = $this->scheduleObj->getSchedule()->customization_data_obj;

		$firstComment = $this->scheduleObj->replaceShortCodes( $scheduleData->first_comment ?? '' );

		$firstComment = strip_tags( $firstComment );
		$firstComment = str_replace( [ '&nbsp;', "\r\n" ], [ ' ', "\n" ], $firstComment );

		return $firstComment;
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

		return apply_filters( 'fsp_schedule_media_list_to_upload', $mediaListToUpload, $this->scheduleObj );
	}

	private function createImageForStory ( $photoPath, $title ) : ?array
	{
		$storyBackground    = Settings::get( 'fb_story_customization_bg_color', '636e72' );
		$titleBackground    = Settings::get( 'fb_story_customization_title_bg_color', '000000' );
		$titleBackgroundOpc = Settings::get( 'fb_story_customization_title_bg_opacity', '30' );
		$titleColor         = Settings::get( 'fb_story_customization_title_color', 'FFFFFF' );
		$titleTop           = (int) Settings::get( 'fb_story_customization_title_top_offset', '125' );
		$titleLeft          = (int) Settings::get( 'fb_story_customization_title_left_offset', '30' );
		$titleWidth         = (int) Settings::get( 'fb_story_customization_title_width', '660' );
		$titleFontSize      = (int) Settings::get( 'fb_story_customization_title_font_size', '30' );
		$titleRtl           = (bool) Settings::get( 'fb_story_customization_is_rtl', false );

		if ( $titleRtl )
		{
			$p_a   = new PersianStringDecorator();
			$title = $p_a->decorate( $title, false );
		}

		$titleBackgroundOpc = $titleBackgroundOpc > 100 || $titleBackgroundOpc < 0 ? 0.3 : $titleBackgroundOpc / 100;

		$storyBackground   = Helper::hexToRgb( $storyBackground );
		$storyBackground[] = 0;// opacity

		$storyW = 1080 / 1.5;
		$storyH = 1920 / 1.5;

		$imageInf    = new PHPImage( $photoPath );
		$imageWidth  = $imageInf->getWidth();
		$imageHeight = $imageInf->getHeight();

		if ( $imageWidth * $imageHeight > 3400 * 3400 ) // large file
		{
			return null;
		}

		$imageInf->cleanup();
		unset( $imageInf );

		$w1 = $storyW;
		$h1 = ( $w1 / $imageWidth ) * $imageHeight;

		if ( $h1 > $storyH )
		{
			$w1 = ( $storyH / $h1 ) * $w1;
			$h1 = $storyH;
		}

		$image = new PHPImage();
		$image->initialiseCanvas( $storyW, $storyH, 'img', $storyBackground );

		$image->draw( $photoPath, '50%', '50%', $w1, $h1 );

		$titleLength  = mb_strlen( $title, 'UTF-8' );
		$titlePercent = $titleLength - 40;
		if ( $titlePercent < 0 )
		{
			$titlePercent = 0;
		}
		else if ( $titlePercent > 100 )
		{
			$titlePercent = 100;
		}
		// write title
		if ( ! empty( $title ) )
		{
			$textPadding = 10;
			$textWidth   = $titleWidth;
			$textHeight  = 100 + $titlePercent;
			$iX          = $titleLeft;
			$iY          = $titleTop;

			$fontDir = Settings::get( 'fb_story_customization_title_font_file', '' );

			$image->setFont( $fontDir );

			$image->rectangle( $iX, $iY, $textWidth + $textPadding, $textHeight - $textPadding, Helper::hexToRgb( $titleBackground ), $titleBackgroundOpc );

			$image->textBox( $title, [
				'fontSize'        => $titleFontSize,
				'fontColor'       => Helper::hexToRgb( $titleColor ),
				'x'               => $iX,
				'y'               => $iY,
				'strokeWidth'     => 1,
				'strokeColor'     => [ 99, 110, 114 ],
				'width'           => $textWidth,
				'height'          => $textHeight,
				'alignHorizontal' => 'center',
				'alignVertical'   => 'center',
			], $titleRtl );
		}

		$newFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid( 'fs_' );

		$image->setOutput( 'jpg' )->save( $newFileName );

		$this->removeFilesOnDestruct[] = $newFileName;

		return [
			'width'  => $storyW,
			'height' => $storyH,
			'path'   => $newFileName,
		];
	}


}