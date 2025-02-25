<?php

namespace FSPoster\App\Providers\Helpers;

use FSPoster\App\Models\App;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelLabel;
use FSPoster\App\Models\ChannelLabelsData;
use FSPoster\App\Models\ChannelPermission;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\PostComment;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\DB;

class Helper
{
    use WPHelper;

	public static function secFormat( $seconds )
	{
		$weeks = floor($seconds /  ( 60 * 60 * 24 * 7 ) );

		$seconds = $seconds % ( 60 * 60 * 24 * 7 );

		$days = floor($seconds /  ( 60 * 60 * 24 ) );

		$seconds = $seconds % ( 60 * 60 * 24 );

		$hours = floor($seconds /  ( 60 * 60 ) );

		$seconds = $seconds % ( 60 * 60 );

		$minutes = floor($seconds / 60 );

		$seconds = $seconds % 60;

		if($weeks == 0)
		{
			$result = rtrim(
				( $weeks > 0 ? $weeks . fsp__('w').' ' : '' ) .
				( $days > 0 ? $days . fsp__('d').' ' : '' ) .
				( $hours > 0 ? $hours . fsp__('h').' ' : '' ) .
				( $minutes > 0 ? $minutes . fsp__('m').' ' : '' ) .
				( $seconds > 0 ? $seconds . fsp__('s').' ' : '' )
			);
		}
		else if($days)
		{
			$days += 7 * $weeks;
			$result = rtrim($days > 0 ? $days . fsp__('d').' ' : '');
		}
		else if($weeks)
		{
			$result = rtrim($weeks > 0 ? $weeks . fsp__('w').' ' : '');
		}

		return empty( $result ) ? '0' : $result;
	}

    public static function spintax ( $text )
    {
        $text = is_string( $text ) ? (string)$text : '';

        return preg_replace_callback( '/\{(((?>[^\{\}]+)|(?R))*)\}/x', function ( $text )
        {
            $text  = Helper::spintax( $text[ 1 ] );
            $parts = explode( '|', $text );

            return $parts[ array_rand( $parts ) ];
        }, $text );
    }

    public static function cutText ( string $text, $n = 35 ): string
    {
        return mb_strlen( $text, 'UTF-8' ) > $n ? mb_substr( $text, 0, $n, 'UTF-8' ) . '...' : $text;
    }

    public static function hexToRgb ( $hex )
    {
        if ( strpos( '#', $hex ) === 0 )
        {
            $hex = substr( $hex, 1 );
        }

        return sscanf( $hex, "%02x%02x%02x" );
    }

    public static function downloadRemoteFile ( string $destination, string $sourceURL )
    {
	    $destinationDir = dirname( $destination );

	    if ( ! is_dir( $destinationDir ) )
		    mkdir( $destinationDir );

        return file_put_contents( $destination, Curl::getURL( $sourceURL ) );
    }

    public static function mimeContentType ( string $filename ): string
    {
        if ( function_exists( 'finfo_open' ) )
        {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );

            if ( $finfo !== false )
            {
                $mimetype = @finfo_file( $finfo, $filename );
                finfo_close( $finfo );

                if ( !empty( $mimetype ) )
                {
                    return $mimetype;
                }
            }
        }

        if ( function_exists( 'mime_content_type' ) )
        {
            $mimeType = @mime_content_type( $filename );

            if ( $mimeType !== false )
            {
                return $mimeType;
            }
        }

        $mimeContentTypes = [
            'webp' => 'image/webp',
            'png'  => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpe'  => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'ico'  => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif'  => 'image/tiff',

            'webm' => 'video/webm',
            'mp4'  => 'video/mp4',
            'qt'   => 'video/quicktime',
            'mov'  => 'video/quicktime',
            'flv'  => 'video/x-flv',
        ];

        $mime = explode( '.', $filename );

        if ( empty( $mime ) )
        {
            return 'application/octet-stream';
        }

        $mime = strtolower( array_pop( $mime ) );

        foreach ( $mimeContentTypes as $ext => $contentType )
        {
            if ( strpos( $mime, $ext ) === 0 )
            {
                return $contentType;
            }
        }

        return 'application/octet-stream';
    }

    public static function webpToJpg ( $file )
    {
        if ( !function_exists( 'imagecreatefromwebp' ) || !function_exists( 'imagejpeg' ) )
            return false;

        $jpg = imagecreatefromwebp( $file );

        if ( $jpg === false )
            return false;

        ob_clean();
        ob_start();
        $res   = imagejpeg( $jpg );
        $image = ob_get_clean();

        if ( $res === false )
            return false;

        return $image;
    }

    public static function snakeCaseToCamel ( string $snakeCaseString ): string
    {
        return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $snakeCaseString ) ) ) );
    }

    public static function generateUUID (): string
    {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
    }

    public static function getFrontendAssetUrl( string $assetName ): string
    {
        $manifestPath = FSP_ROOT_DIR . '/frontend/build/manifest.json';

        $manifest = file_get_contents($manifestPath);
        $manifest = json_decode($manifest, true);

        return plugin_dir_url( FSP_ROOT_DIR . '/init.php' ) . 'frontend/build/' . $manifest[$assetName]['file'];
    }

	public static function clearUserAllData ( $userID )
	{
		// doit burda hersheyi silmeyi duzdurmu?
		App::where( 'created_by', $userID )->delete();
		Schedule::withoutGlobalScope( 'my_schedules' )->withoutGlobalScope( 'blog' )->where( 'user_id', $userID )->delete();
		Planner::where( 'user_id', $userID )->delete();

		$channelsToDelete = Channel::withoutGlobalScope( 'my_channels' )->withoutGlobalScope( 'blog' )->where( 'user_id', $userID );
		$channels         = $channelsToDelete->select( 'id', true )->fetchAll();
		$channels         = array_map( fn ( $channel ) => $channel->id, $channels );

		do_action( 'fsp_remove_channels', $channels );

		foreach ( $channels as $channelID )
		{
			$channelID = (int)$channelID;
			Planner::update( [
				'channels' => DB::field( "TRIM(BOTH ',' FROM replace(concat(',',`channels`,','), ',$channelID,',','))" ),
			] );
		}

		ChannelLabelsData::where('channel_id', $channels)->delete();

		// doit burda user_id yoxdu ilk novbede. Ikincisi withoutGlobalScope( 'blog' ) Channele verilir, halbuki Channelda blog_id yoxdu.
		Channel::withoutGlobalScope( 'my_channels' )
		       ->withoutGlobalScope( 'blog' )
		       ->where( 'user_id', $userID )
		       ->delete();

		$allChannelsQuery = Channel::withoutGlobalScope( 'my_channels' )
		                           ->withoutGlobalScope( 'soft_delete' );

		ChannelPermission::where( 'channel_id', 'not in', $allChannelsQuery->select( 'id', true ) )->delete();

		PostComment::where( 'channel_id', 'not in', $allChannelsQuery->select( 'id', true ) )->delete();

		ChannelLabel::where( 'user_id', $userID )->delete();

		ChannelLabelsData::where( 'group_id', 'not in', ChannelLabel::where( 'user_id', $userID )->select( 'id', true ) )
		                 ->orWhere( 'channel_id', 'in', $allChannelsQuery->select( 'id', true ) )
		                 ->delete();

		ChannelSession::where( 'id', 'not in', $allChannelsQuery->select( 'channel_session_id', true ) )->delete();
	}

	/**
	 * Mueyyen zaman araligindan bir ishe dushen processleri idare etmek uchundu.
	 * Meselen cron job her 60 saniyeden bir ishe dushmelidir. Bu funksiya sadece olarag hemen 60 saniyenin tamam olub olmadigini verir sene.
	 * Diqqet! Ashagidaki query buildersiz, setOption, getOption`siz yazilmasina sebeb var. Deyishdirmeye qalxmayin! Bezi cache pluginler get_option etdikde valieni deyishdirirler ve sistem ishlemir netcede. Ona gore bele yazilib.
	 * Alqoritmani optimallashdirmaga chalishmayin! WP-da virtual cron job olur deye ve chox user saytda online oldugda sizin "optimal" bildiyiniz alqoritma eyni anda bir neche prosesi run etmish olur (hetda bezen minlerle eyni anda proses run olur, meselen eyni SMS yuzlerle defe tekrar gonderildiyi case olub)
	 *
	 * @param $processName
	 * @param $runProcessEvery
	 *
	 * @return bool
	 */
	public static function processRuntimeController ( $processName, $allowProcessToRunEvery ): bool
	{
		$processOptionName = sprintf( '%s%s_run_on', Settings::PREFIX, $processName );
		$currentEpoch = Date::epoch();

		$getLastCronJobRunTime = DB::DB()->get_results( DB::DB()->prepare( "SELECT * FROM `".DB::DB()->base_prefix."options` WHERE `option_name`=%s", $processOptionName ) );
		$cronjobLastRun = ! empty( $getLastCronJobRunTime ) ? $getLastCronJobRunTime[0]->option_value : 0;

		if( empty( $cronjobLastRun ) )
			DB::DB()->query( DB::DB()->prepare("INSERT INTO `".DB::DB()->base_prefix."options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'yes')", $processOptionName, $currentEpoch) );

		if( ( $currentEpoch - $cronjobLastRun ) < $allowProcessToRunEvery )
			return false;

		/**
		 * Dublicate cron requestler ishe dushme ehtimalini 0a endirir. Bu mentiqi qeti shekilde deyishdirmek olmaz!
		 */
		DB::DB()->query( DB::DB()->prepare("UPDATE `" . DB::DB()->base_prefix . "options` SET `option_value` = %s WHERE `option_name`=%s AND `option_value` = %s", (string)$currentEpoch, $processOptionName, (string)$cronjobLastRun) );

		if ( DB::DB()->rows_affected <= 0 )
			return false;

		return true;
	}

	/**
	 * Bezi WP Secuirty pluginleri Cross-Origin-Opener-Policy`i safe edirler.
	 * Neticede biz window.opener() ile popup achirig, ve ordan sonra geriye data oture bilmirik.
	 * Bu funksiya onu yoxlayir, eger php bu headeri gonderemeye hazirlashirsa (demekki hansisa plugin deyishib),
	 * bu funksiya onu yeniden unsafe-none edir ki, datani openere oture bilek
	 *
	 * @return void
	 */
	public static function setCrossOriginOpenerPolicyHeaderIfNeed () {
		$responseHeaders = headers_list();
		$coopHeaderExists = false;

		foreach ($responseHeaders as $header) {
			if (stripos($header, 'Cross-Origin-Opener-Policy') === 0) {
				$coopHeaderExists = true;

				if (stripos($header, 'unsafe-none') === false) {
					header('Cross-Origin-Opener-Policy: unsafe-none', true);
				}

				break;
			}
		}

		if (!$coopHeaderExists) {
			header('Cross-Origin-Opener-Policy: unsafe-none');
		}
	}

}
