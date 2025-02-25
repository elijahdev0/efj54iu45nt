<?php

namespace FSPoster\App\Providers\Helpers;

class WPPostThumbnail
{
	/**
	 * @var array
	 */
	private static array $saveCacheFiles = [];


	/**
	 * Clear cache
	 */
	public static function clearCache ()
	{
		foreach ( self::$saveCacheFiles as $cacheFile )
		{
			if ( file_exists( $cacheFile ) )
			{
				unlink( $cacheFile );
			}
		}

		self::$saveCacheFiles = [];
	}

	public static function getOrCreateImagePath ( int $mediaID, $readOnlyMode = false ) : ?string
	{
		if ( empty( $mediaID ) )
			return null;

		$imagePath = get_attached_file( $mediaID );

		if ( ( empty( $imagePath ) || ! file_exists( $imagePath ) ) && ! $readOnlyMode )
		{
			$mediaURL = wp_get_attachment_url( $mediaID );

			return self::saveRemoteImage( $mediaURL );
		}
		else
		{
			return $imagePath;
		}
	}

    public static function saveRemoteImage ( $fileUrl )
    {
        if ( empty( $fileUrl ) || ! function_exists( 'tempnam' ) || ! function_exists( 'sys_get_temp_dir' ) )
            return false;

        $imagePath = tempnam( sys_get_temp_dir(), 'FS_tmpfile_' );

        if ( $imagePath === false )
            return false;

        $fc = file_put_contents( $imagePath, Curl::getURL( $fileUrl ) );

	    self::$saveCacheFiles[] = $imagePath;

        if ( $fc !== false )
	        return $imagePath;

		return false;
    }

}
