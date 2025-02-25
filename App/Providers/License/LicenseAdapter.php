<?php

namespace FSPoster\App\Providers\License;

use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\PluginHelper;

class LicenseAdapter
{

	private const LOCK_MIGRATION_OPTION_NAME = 'migration_is_running';

	public static function activateLicense ( ActivateLicenseRequest $request ) : array
	{
		$apiClient = new LicenseApiClient();

		$result = $apiClient->sendClientRequest( 'activate_license', 'POST', $request->toArray(), true );

		if( ! ( $result['status'] ?? false ) )
		{
			if( ($result['error_code'] ?? '') === 'license_is_not_free' && isset( $result['data']['activated_website'] ) )
			{
				return [
					'activated_website' => $result['data']['activated_website'],
				];
			}

			throw new \Exception( $result['message'] ?? fsp__('Your server can not access our license server via CURL! Our license server is "https://api.fs-code.com". Please contact your hosting provider and ask them to solve the problem.') );
		}

		if( ! isset( $result['data']['access_token'], $result['data']['license_code'] ) )
			throw new \Exception( fsp__('Something went wrong!') );

		$accessToken = $result['data']['access_token'];
		$licenseCode = $result['data']['license_code'];

		Settings::set( 'license_type', $request->licenseType, true );
		Settings::set( 'license_code', $licenseCode, true );
		Settings::set( 'license_access_token', $accessToken, true );
		Settings::set( 'license_activated_at', Date::epoch(), true );
		Settings::set( 'plugin_disabled', '0', true );
		Settings::set( 'plugin_alert', '', true );

		return [];
	}

	public static function fetchAndRunMigrationData ()
	{
		if( ! PluginHelper::isPluginActivated() )
			return;

		$currentVersion = PluginHelper::getVersion();
		$lastUpdatedVersion = Settings::get( 'installed_version', '0.0.0', true );

		if( $lastUpdatedVersion == $currentVersion || ! self::isMigrationServiceAlreadyRunning() )
			return;

		$accessToken = Settings::get( 'license_access_token', '', true );

		$apiClient = new LicenseApiClient();
		$apiClient->setAccessToken( $accessToken );

		$requestData = [
			'old_version'   => $lastUpdatedVersion,
			'new_version'   => $currentVersion,
		];

		$result = $apiClient->sendClientRequest( 'get_migration_data', 'POST', $requestData );

		if( isset( $result['data']['migrations'] ) )
		{
			set_time_limit( 0 );

			foreach ( $result['data']['migrations'] AS $migration )
			{
				if( $migration['type'] == 'sql' )
				{
					$sqlData = base64_decode( $migration['data'] );
					$basePrefix = DB::DB()->base_prefix;
					$tablePrefix = $basePrefix . DB::PLUGIN_DB_PREFIX;

					$sqlData = str_replace( [ '{tableprefix}', '{tableprefixbase}' ], [ $tablePrefix, $basePrefix ], $sqlData );
					$sqlData = explode( ';', $sqlData );

					foreach ( $sqlData as $sqlQuery )
					{
						$checkIfEmpty = preg_replace( '/\s/', '', $sqlQuery );

						if ( ! empty( $checkIfEmpty ) )
							DB::DB()->query( $sqlQuery );
					}
				}
			}

			Settings::set( 'installed_version', $currentVersion, true );

			self::unlockMigrationService();
		}
	}

	private static function isMigrationServiceAlreadyRunning ()
	{
		$optionName = Settings::PREFIX . self::LOCK_MIGRATION_OPTION_NAME;

		$oldValue = DB::DB()->show_errors( false );
		$query = DB::DB()->prepare("INSERT INTO `" . DB::DB()->base_prefix . "options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no') ", [ $optionName, (string)Date::epoch() ]);
		DB::DB()->query( $query );
		DB::DB()->show_errors( $oldValue );

		$isAlreadyRunning = DB::DB()->rows_affected === 0;

		/** yoxlayag belke hansisa sebeben 1 saatdan choxdur ilishib qalib? */
		if( $isAlreadyRunning )
		{
			$value = DB::DB()->get_row( DB::DB()->prepare( "SELECT * FROM `" . DB::DB()->base_prefix . "options` WHERE `option_name`=%s", [ $optionName ] ), ARRAY_A );

			if( isset( $value['option_value'] ) && $value['option_value'] > 0 && (Date::epoch() - $value['option_value']) > 60 * 60 )
				self::unlockMigrationService();
		}

		return $isAlreadyRunning;
	}

	private static function unlockMigrationService ()
	{
		DB::DB()->query("DELETE FROM `" . DB::DB()->base_prefix . "options` WHERE `option_name`='fsp_migration_is_running'");
	}

	public static function checkLicenseAndDisableWebsiteIfNeed ()
	{
		$lastTime = Settings::get( 'license_last_checked_time', 0 );

		if ( Date::epoch() - $lastTime < 10 * 60 * 60 )
			return;

		Settings::set( 'license_last_checked_time', Date::epoch() );

		$apiClient = new LicenseApiClient();
		$requestData = [
			'license_code'  => Settings::get( 'license_code', '', true ),
			'license_type'  => Settings::get( 'license_type', '', true ),
			'site_url'      => network_site_url()
		];

		$checkResult = $apiClient->sendClientRequest( 'check_license', 'POST', $requestData );

		if( ! ( isset( $checkResult['status'] ) && $checkResult['status'] === false && isset( $checkResult['error_code'] ) ) )
			return;

		$alertMessage = $checkResult['message'] ?? fsp__('Plugin is disabled!');

		Settings::set( 'plugin_alert', $alertMessage, true );
		Settings::set( 'plugin_disabled', 1, true );
		Settings::delete( 'license_code', true );
		Settings::delete( 'license_type', true );
		Settings::delete( 'license_access_token', true );
	}

	public static function getNews ()
	{
		$dataURL = 'https://www.fs-poster.com/api/news/';
		$expTime = 12*60*60; // In seconds

		$cachedData = json_decode( Settings::get( 'news_cache', false, true ) );
		$now        = Date::epoch();

		if ( empty( $cachedData ) || $now - $cachedData->time >= $expTime )
		{
			$data = Curl::getContents( $dataURL );

			Settings::set( 'news_cache', json_encode( [
				'time' => $now,
				'data' => $data,
			] ), true );
		}
	}

}