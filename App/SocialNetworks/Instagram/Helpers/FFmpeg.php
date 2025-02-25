<?php

namespace FSPoster\App\SocialNetworks\Instagram\Helpers;

use Exception;
use FSPoster\Symfony\Component\Process\Process;
use RuntimeException;

class FFmpeg
{
	const BINARIES         = [
		'ffmpeg',
		'avconv',
	];

	const WINDOWS_BINARIES = [
		'ffmpeg.exe',
		'avconv.exe',
	];

	public static    $defaultBinary;
	public static    $defaultTimeout = 600;
	public static    $ffprobeBin;
	private static   $videoDetails = [];
	protected static $_instances   = [];
	protected        $_ffmpegBinary;
	protected        $_hasNoAutorotate;
	protected        $_hasLibFdkAac;

	protected function __construct ( $ffmpegBinary )
	{
		$this->_ffmpegBinary = $ffmpegBinary;

		$this->version();
	}

	public function run ( array $command )
	{
		$process = $this->runAsync( $command );

		try
		{
			$exitCode = $process->wait();
		}
		catch ( Exception $e )
		{
			throw new RuntimeException( sprintf( 'Failed to run the ffmpeg binary: %s', $e->getMessage() ) );
		}
		if ( $exitCode )
		{
			$errors   = preg_replace( '#[\r\n]+#', '"], ["', trim( $process->getErrorOutput() ) );
			$errorMsg = sprintf( 'FFmpeg Errors: ["%s"], Command: "%s".', $errors, implode(' ', $command) );

			throw new RuntimeException( $errorMsg, $exitCode );
		}

		return preg_split( '#[\r\n]+#', $process->getOutput(), null, PREG_SPLIT_NO_EMPTY );
	}

	public function runAsync ( array $command ) : Process
    {
		$process = new Process( [
            $this->_ffmpegBinary,
            '-v',
            'error',
            ...$command,
        ] );
		if ( is_int( self::$defaultTimeout ) && self::$defaultTimeout > 60 )
		{
			$process->setTimeout( self::$defaultTimeout );
		}
		$process->start();

		return $process;
	}

	public function version ()
	{
		return $this->run( ['-version'] )[ 0 ];
	}

	public function getFFmpegBinary ()
	{
		return $this->_ffmpegBinary;
	}

	public function hasNoAutorotate ()
	{
		if ( $this->_hasNoAutorotate === null )
		{
			try
			{
				$this->run( ['-noautorotate', '-f', 'lavfi', '-i', 'color=color=red', '-t', 1, '-f', 'null', '-'] );
				$this->_hasNoAutorotate = true;
			}
			catch ( RuntimeException $e )
			{
				$this->_hasNoAutorotate = false;
			}
		}

		return $this->_hasNoAutorotate;
	}

	public function hasLibFdkAac ()
	{
		if ( $this->_hasLibFdkAac === null )
		{
			$this->_hasLibFdkAac = $this->_hasAudioEncoder( 'libfdk_aac' );
		}

		return $this->_hasLibFdkAac;
	}

	protected function _hasAudioEncoder ( $encoder )
	{
		try
		{
			$this->run( ['-f', 'lavfi',  '-i',  'anullsrc=channel_layout=stereo:sample_rate=44100', '-c:a', static::escape($encoder), '-t', 1, '-f', 'null', '-'] );

			return true;
		}
		catch ( RuntimeException $e )
		{
			return false;
		}
	}

	public static function factory ( $ffmpegBinary = null )
	{
		if ( $ffmpegBinary === null )
		{
			return static::_autoDetectBinary();
		}

		if ( isset( self::$_instances[ $ffmpegBinary ] ) )
		{
			return self::$_instances[ $ffmpegBinary ];
		}

		$instance                          = new static( $ffmpegBinary );
		self::$_instances[ $ffmpegBinary ] = $instance;

		return $instance;
	}

	protected static function _autoDetectBinary ()
	{
		$binaries = defined( 'PHP_WINDOWS_VERSION_MAJOR' ) ? self::WINDOWS_BINARIES : self::BINARIES;
		if ( self::$defaultBinary !== null )
		{
			array_unshift( $binaries, self::$defaultBinary );
		}

		$errors = [];

		$instance = null;
		foreach ( $binaries as $binary )
		{
			if ( isset( self::$_instances[ $binary ] ) )
			{
				return self::$_instances[ $binary ];
			}

			try
			{
				$instance = new static( $binary );
			}
			catch ( Exception $e )
			{
				if( ! in_array( $e->getMessage(), $errors ) )
					$errors[] = $e->getMessage();

				continue;
			}
			self::$defaultBinary         = $binary;
			self::$_instances[ $binary ] = $instance;

			return $instance;
		}

		throw new RuntimeException( fsp__( 'For sharing videos on Instagram, you have to install the FFmpeg library on your server and configure executables\' path.' . ' (['.htmlspecialchars( implode('], [', $errors) ).'])', [], false ) );
	}

	public static function escape ( $arg, $meta = true )
	{
		if ( ! defined( 'PHP_WINDOWS_VERSION_BUILD' ) )
		{
			return escapeshellarg( $arg );
		}

		$quote = strpbrk( $arg, " \t" ) !== false || $arg === '';
		$arg   = preg_replace( '/(\\\\*)"/', '$1$1\\"', $arg, -1, $dquotes );

		if ( $meta )
		{
			$meta = $dquotes || preg_match( '/%[^%]+%/', $arg );

			if ( ! $meta && ! $quote )
			{
				$quote = strpbrk( $arg, '^&|<>()' ) !== false;
			}
		}

		if ( $quote )
		{
			$arg = preg_replace( '/(\\\\*)$/', '$1$1', $arg );
			$arg = '"' . $arg . '"';
		}

		if ( $meta )
		{
			$arg = preg_replace( '/(["^&|<>()%])/', '^$1', $arg );
		}

		return $arg;
	}

	public static function checkFFPROBE ()
	{
		// We only resolve this once per session and then cache the result.
		if ( self::$ffprobeBin === null ) {
			if( function_exists( 'exec' ) ) {
				@exec( 'ffprobe -version 2>&1', $output, $statusCode );
			} else {
				$statusCode = null;
			}

			if ( $statusCode === 0 ) {
				self::$ffprobeBin = 'ffprobe';
			} else {
				self::$ffprobeBin = false; // Nothing found!
			}
		}

		return self::$ffprobeBin;
	}

	public static function checkLibx264 () {
		if( function_exists( 'exec' ) ) {
			@exec( "ffmpeg -codecs 2>&1", $output, $statusCode );

			if ( $statusCode === 0 ) {
				$outputString = implode( "\n", $output );

				if ( strpos( $outputString, 'libx264' ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function videoDetails ( $filename )
	{
		if ( ! isset( self::$videoDetails[ md5( $filename ) ] ) )
		{
			$ffprobe = self::checkFFPROBE();

			if ( $ffprobe === false )
			{
				throw new RuntimeException( fsp__( 'For sharing videos on Instagram, you have to install the FFmpeg library on your server and configure executables\' path <a href=\'https://www.fs-poster.com/documentation/how-to-install-ffmpeg\' target=\'_blank\'>How to?</a>', [], false ) );
			}

			$command = sprintf( '%s -v quiet -print_format json -show_format -show_streams %s', self::escape( $ffprobe ), self::escape( $filename ) );

			$jsonInfo    = @shell_exec( $command );
			$probeResult = @json_decode( $jsonInfo, true );

			if ( ! is_array( $probeResult ) || ! isset( $probeResult[ 'streams' ] ) || ! is_array( $probeResult[ 'streams' ] ) )
			{
				throw new RuntimeException( sprintf( 'FFprobe failed to detect any stream. Is "%s" a valid media file?', $filename ) );
			}

			$videoCodec = null;
			$width      = 0;
			$height     = 0;
			$duration   = 0;
			$audioCodec = null;

			foreach ( $probeResult[ 'streams' ] as $streamIdx => $streamInfo )
			{
				if ( ! isset( $streamInfo[ 'codec_type' ] ) )
				{
					continue;
				}

				switch ( $streamInfo[ 'codec_type' ] )
				{
					case 'video':
						$videoCodec = (string) $streamInfo[ 'codec_name' ];
						$width      = (int) $streamInfo[ 'width' ];
						$height     = (int) $streamInfo[ 'height' ];

						if ( isset( $streamInfo[ 'duration' ] ) )
						{
							$duration = (int) $streamInfo[ 'duration' ];
						}
						break;
					case 'audio':
						$audioCodec = (string) $streamInfo[ 'codec_name' ];
						break;
				}
			}

			if ( is_null( $duration ) && isset( $probeResult[ 'format' ][ 'duration' ] ) )
			{
				$duration = (int) $probeResult[ 'format' ][ 'duration' ];
			}

			if ( is_null( $duration ) )
			{
				throw new RuntimeException( sprintf( 'FFprobe failed to detect video duration. Is "%s" a valid video file?', $filename ) );
			}

			self::$videoDetails[ md5( $filename ) ] = array_merge( $probeResult, [
				'video_codec' => $videoCodec,
				'width'       => $width,
				'height'      => $height,
				'duration'    => $duration,
				'audio_codec' => $audioCodec,
			] );
		}

		return self::$videoDetails[ md5( $filename ) ];
	}
}
