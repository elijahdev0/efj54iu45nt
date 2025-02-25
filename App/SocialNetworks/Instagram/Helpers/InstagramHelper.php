<?php

namespace FSPoster\App\SocialNetworks\Instagram\Helpers;

use Exception;
use FSPoster\App\Libraries\PHPImage\PHPImage;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use InvalidArgumentException;

class InstagramHelper
{
	const MIN_ASPECT_RATIO = 0.8;

	const MAX_ASPECT_RATIO = 1.91;

	public static array $recycle_bin = [];

    /**
     * @throws Exception
     */
    public static function imageForStory ($photo_path, $title, $link, $method ) : ?array
    {
		$storyBackground    = Settings::get( 'instagram_story_customization_bg_color', '636e72' );
		$titleBackground    = Settings::get( 'instagram_story_customization_title_bg_color', '000000' );
		$titleBackgroundOpc = Settings::get( 'instagram_story_customization_title_bg_opacity', '30' );
		$titleColor         = Settings::get( 'instagram_story_customization_title_color', 'FFFFFF' );
		$titleTop           = (int) Settings::get( 'instagram_story_customization_title_top_offset', '125' );
		$titleLeft          = (int) Settings::get( 'instagram_story_customization_title_left_offset', '30' );
		$titleWidth         = (int) Settings::get( 'instagram_story_customization_title_width', '660' );
		$titleFontSize      = (int) Settings::get( 'instagram_story_customization_title_font_size', '30' );
		$titleRtl           = (bool)Settings::get( 'instagram_story_customization_is_rtl', false);

		$linkBackground    = Settings::get( 'instagram_story_customization_link_bg_color', '000000' );
		$linkBackgroundOpc = Settings::get( 'instagram_story_customization_link_bg_opacity', '100' );
		$linkColor         = Settings::get( 'instagram_story_customization_link_color', '3468CF' );
		$linkTop           = (int) Settings::get( 'instagram_story_customization_link_top_offset', '1000' );
		$linkLeft          = (int) Settings::get( 'instagram_story_customization_link_left_offset', '30' );
		$linkWidth         = (int) Settings::get( 'instagram_story_customization_link_width', '660' );
		$linkFontSize      = (int) Settings::get( 'instagram_story_customization_link_font_size', '30' );

		$hashtag              = Settings::get( 'instagram_story_customization_hashtag_text', '' );
		$hashtagBackground    = Settings::get( 'instagram_story_customization_hashtag_bg_color', '000000' );
		$hashtagBackgroundOpc = Settings::get( 'instagram_story_customization_hashtag_bg_opacity', '100' );
		$hashtagColor         = Settings::get( 'instagram_story_customization_hashtag_color', '3468CF' );
		$hashtagTop           = (int) Settings::get( 'instagram_story_customization_hashtag_top_offset', 700 );
		$hashtagLeft          = (int) Settings::get( 'instagram_story_customization_hashtag_left_offset', '30' );
		$hashtagWidth         = (int) Settings::get( 'instagram_story_customization_hashtag_width', '660' );
		$hashtagFontSize      = (int) Settings::get( 'instagram_story_customization_hashtag_font_size', '30' );

		if ( $titleRtl )
		{
			$p_a   = new PersianStringDecorator();
			$title = $p_a->decorate( $title, false, true );
		}

		$titleBackgroundOpc   = $titleBackgroundOpc > 100 || $titleBackgroundOpc < 0 ? 0.3 : $titleBackgroundOpc / 100;
		$linkBackgroundOpc    = $linkBackgroundOpc > 100 || $linkBackgroundOpc < 0 ? 0.3 : $linkBackgroundOpc / 100;
		$hashtagBackgroundOpc = $hashtagBackgroundOpc > 100 || $hashtagBackgroundOpc < 0 ? 0.3 : $hashtagBackgroundOpc / 100;

		$storyBackground   = Helper::hexToRgb( $storyBackground );
		$storyBackground[] = 0;// opacity

		$storyW = 1080 / 1.5;
		$storyH = 1920 / 1.5;

		$imageInf    = new PHPImage( $photo_path );
		$imageWidth  = $imageInf->getWidth();
		$imageHeight = $imageInf->getHeight();

		if ( $imageWidth * $imageHeight > 3400 * 3400 ) // large file
			throw new Exception( fsp__('The file is too large! The allowed max ratio is 3400x3400!') );

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

		$image->draw( $photo_path, '50%', '50%', $w1, $h1 );

		// write title
		if ( ! empty( $title ) )
		{
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

			$textPadding = 10;
			$textWidth   = $titleWidth;
			$textHeight  = 100 + $titlePercent;
			$iX          = $titleLeft;
			$iY          = $titleTop;

			$titleFontDir = Settings::get( 'instagram_story_customization_title_font_file', '' );

			$image->setFont( $titleFontDir );

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
				'alignVertical'   => 'center'
			], $titleRtl );
		}

		// write link
		if ( ! empty( $link ) )
		{
			$https_pattern = '/^(https:\/\/www\.|https:\/\/|http:\/\/www\.|http:\/\/)/';

			preg_match( $https_pattern, $link, $matches );

			if ( ! empty( $matches[ 0 ] ) )
			{
				$link = substr( $link, strlen( $matches[ 0 ] ) );
			}

			if ( strpos( $link, '/' ) )
			{
				$link = explode( '/', $link )[ 0 ];
			}

			$link = strtoupper( $link );

			$textLength  = mb_strlen( $link, 'UTF-8' );
			$textPercent = $textLength - 40;
			if ( $textPercent < 0 )
			{
				$textPercent = 0;
			}
			else if ( $textPercent > 100 )
			{
				$textPercent = 100;
			}

			$textPadding = 10;
			$textWidth   = $linkWidth;
			$textHeight  = 100 + $textPercent;
			$iX          = $linkLeft;
			$iY          = $linkTop;

            $linkFontDir = Settings::get( 'instagram_story_customization_link_font_file', '' );

            $image->setFont( $linkFontDir );

			$image->rectangle( $iX, $iY, $textWidth + $textPadding, $textHeight - $textPadding, Helper::hexToRgb( $linkBackground ), $linkBackgroundOpc );

			$image->textBox( $link, [
				'fontSize'        => $linkFontSize,
				'fontColor'       => Helper::hexToRgb( $linkColor ),
				'x'               => $iX,
				'y'               => $iY,
				'strokeWidth'     => 1,
				'strokeColor'     => [ 99, 110, 114 ],
				'width'           => $textWidth,
				'height'          => $textHeight,
				'alignHorizontal' => 'center',
				'alignVertical'   => 'center'
			] );
		}

		// write hashtag
        $addHashtag = (bool)Settings::get('instagram_story_customization_add_hashtag', false);
		if ($addHashtag && ! empty( $hashtag ) && $method === 'login_pass' )
		{
			$hashtag = strtoupper( $hashtag );
			$hashtag = '#' . $hashtag;

			$textLength  = mb_strlen( $hashtag, 'UTF-8' );
			$textPercent = $textLength - 40;
			if ( $textPercent < 0 )
			{
				$textPercent = 0;
			}
			else if ( $textPercent > 100 )
			{
				$textPercent = 100;
			}

			$textPadding = 10;
			$textWidth   = $hashtagWidth;
			$textHeight  = 100 + $textPercent;
			$iX          = $hashtagLeft;
			$iY          = $hashtagTop;

            $hashtagFontDir = Settings::get( 'instagram_story_customization_hashtag_font_file', '' );

			$image->setFont( $hashtagFontDir );

			$image->rectangle( $iX, $iY, $textWidth + $textPadding, $textHeight - $textPadding, Helper::hexToRgb( $hashtagBackground ), $hashtagBackgroundOpc );

			$image->textBox( $hashtag, [
				'fontSize'        => $hashtagFontSize,
				'fontColor'       => Helper::hexToRgb( $hashtagColor ),
				'x'               => $iX,
				'y'               => $iY,
				'strokeWidth'     => 1,
				'strokeColor'     => [ 99, 110, 114 ],
				'width'           => $textWidth,
				'height'          => $textHeight,
				'alignHorizontal' => 'center',
				'alignVertical'   => 'center'
			] );
		}

        if(empty(wp_upload_dir()[ 'basedir' ]))
        {
            throw new Exception(fsp__('Upload directory is not writeable to create images for story'));
        }

        $newFileName = self::randomPathAndUrl();

        $image->setOutput( 'jpg' )->save( $newFileName['path'] );

		return [
			'width'  => $storyW,
			'height' => $storyH,
			'path'   => $newFileName['path'],
            'url'    => $newFileName['url']
		];
	}

    /**
     * @throws Exception
     */
    public static function imageForFeed ( $photo ) : array
    {
		$result = @getimagesize( $photo );

		if ( $result === false )
		{
			throw new InvalidArgumentException( sprintf( 'The photo file "%s" is not a valid image.', $photo ) );
		}

		$width  = $result[ 0 ];
		$height = $result[ 1 ];

		$ratio1    = $width / $height;
		$newWidth  = $width;
		$newHeight = $height;

		if ( $ratio1 > self::MAX_ASPECT_RATIO )
		{
			$newWidth = (int) ( $height * self::MAX_ASPECT_RATIO );
		}
		else if ( $ratio1 < self::MIN_ASPECT_RATIO )
		{
			$newHeight = (int) ( $width / self::MIN_ASPECT_RATIO );
		}

		$image = new PHPImage();
		$image->initialiseCanvas( $newWidth, $newHeight, 'img', [ 255, 255, 255, 0 ] );

		$image->draw( $photo );

		$newFileName = self::randomPathAndUrl();

		$image->setOutput( 'jpg' )->save( $newFileName['path'] );

		return [
			'width'  => $newWidth,
			'height' => $newHeight,
			'path'   => $newFileName['path'],
			'url'    => $newFileName['url']
		];
	}

    /**
     * @throws Exception
     */
    public static function renderVideo ( $video_path, $target ) : array
    {
		if ( !function_exists('exec') || @exec( 'echo EXEC' ) !== 'EXEC' )
		{
			throw new Exception( fsp__( 'exec() function have to be enabled to share videos on Instagram. <a href=\'https://www.fs-poster.com/documentation/how-to-install-ffmpeg\' target=\'_blank\'>How to?</a>', [], false ) );
		}

		$details = FFmpeg::videoDetails( $video_path );

		$width       = $details[ 'width' ];
		$height      = $details[ 'height' ];
		$duration    = (int) $details[ 'duration' ];
		$video_codec = (int) $details[ 'video_codec' ];
		$audio_codec = (int) $details[ 'audio_codec' ];

		$maxDuration = ( $target == 'story' ? 15 : 60 ) - 0.1;
		$minDuration = ( $target == 'story' ? 1 : 3 );

		if ( $duration < $minDuration )
		{
			throw new Exception( 'Video is too short' );
		}

		$ratio1 = $width / $height;

		if ( $ratio1 > self::MAX_ASPECT_RATIO )
		{
			$newWidth  = (int) ( $height * self::MAX_ASPECT_RATIO );
			$newHeight = $height;
			$cropVideo = true;
		}
		else if ( $ratio1 < self::MIN_ASPECT_RATIO )
		{
			$newWidth  = $width;
			$newHeight = (int) ( $width / self::MIN_ASPECT_RATIO );
			$cropVideo = true;
		}
		else
		{
			$newWidth  = $width;
			$newHeight = $height;
			$cropVideo = false;
		}

		$x = abs( $width - $newWidth ) / 2;
		$y = abs( $height - $newHeight ) / 2;

        $videoPathAndUrl = self::randomPathAndUrl(false);

		$thumbnail = self::randomPathAndUrl()['path'];

		$ffmpeg = FFmpeg::factory();

		$outputFilters = [
			'-metadata:s:v', 'rotate=0',
			'-f', 'mp4',
			'-c:v', 'libx264', /*'-preset', 'fast', */'-crf', '24'
		];

		if ( $audio_codec !== 'aac' )
		{
			if ( $ffmpeg->hasLibFdkAac() )
			{
				$outputFilters[] = '-c:a';
				$outputFilters[] = 'libfdk_aac';
				$outputFilters[] = '-vbr';
				$outputFilters[] = 4;
			}
			else
			{
				$outputFilters[] = '-strict';
				$outputFilters[] = '-2';
				$outputFilters[] = '-c:a';
				$outputFilters[] = 'aac';
				$outputFilters[] = '-b:a';
				$outputFilters[] = '96k';
			}
		}
		else
		{
			$outputFilters[] = '-c:a';
			$outputFilters[] = 'copy';
		}

		if ( $duration > $maxDuration )
		{
			$outputFilters[] = '-t';
			$outputFilters[] = sprintf( '%.2F', $maxDuration );
		}

		$ffmpegOutput          = $ffmpeg->run( ['-y', '-i', $video_path, '-vf', sprintf( 'crop=w=%d:h=%d:x=%d:y=%d', $newWidth, $newHeight, $x, $y ), ...$outputFilters, $videoPathAndUrl['path']] );
		$ffmpegOutputThumbnail = $ffmpeg->run( ['-y', '-i', $video_path, '-f', 'mjpeg', '-vframes', 1, '-ss', '00:00:00.000', $thumbnail] );

		return [
			'width'       => $width,
			'height'      => $height,
			'duration'    => $duration,
			'audio_codec' => $audio_codec,
			'vudie_codec' => $video_codec,
			'path'        => $videoPathAndUrl['path'],
			'url'         => $videoPathAndUrl['url'],
			'thumbnail'   => self::imageForFeed( $thumbnail )
		];
	}

	public static function moveToTrash ( $filePath )
	{
		self::$recycle_bin[] = $filePath;
	}

    /**
     * @throws Exception
     * @return array{path: string, url: string}
     */
    private static function randomPathAndUrl(bool $isImage = true): array
    {
        $ext = $isImage ? '.jpg' : '.mp4';

        $wpUploadDir = wp_upload_dir();

        if(empty($wpUploadDir['basedir']) || empty($wpUploadDir['baseurl']))
        {
            throw new Exception(fsp__('Wordpress upload directory is not writeable to save processed media files'));
        }

        $dirPath = $wpUploadDir['basedir'] . DIRECTORY_SEPARATOR . FSP_PLUGIN_SLUG . DIRECTORY_SEPARATOR . 'instagram';

        if(function_exists('mkdir') && !file_exists($dirPath))
        {
            $madeDir = mkdir($dirPath, 0777, true);

             if(!$madeDir)
             {
                 throw new Exception(fsp__('Failed to create upload directory'));
             }
        }

        $relativePath = [FSP_PLUGIN_SLUG, 'instagram', uniqid() . $ext];

        self::$recycle_bin[] = implode(DIRECTORY_SEPARATOR, [$wpUploadDir['basedir'], ...$relativePath]);

        return [
            'path' => implode(DIRECTORY_SEPARATOR, [$wpUploadDir['basedir'], ...$relativePath]),
            'url'  => implode('/', [$wpUploadDir['baseurl'], ...$relativePath]),
        ];
    }

}