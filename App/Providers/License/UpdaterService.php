<?php

namespace FSPoster\App\Providers\License;

use FSPoster\App\Providers\Core\Request;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\PluginHelper;
use stdClass;
use WP_Error;
use Exception;


class UpdaterService
{
	private $isForcedUpdate = false;
	private $expiration     = 43200; // seconds
	private $pluginSlug;
	private $pluginBase;
	private $transient; // temporary cache for multiple calls

	public function __construct ()
	{
		$this->pluginSlug   = FSP_PLUGIN_SLUG;
		$this->pluginBase   = FSP_PLUGIN_SLUG . '/init.php';

		$this->checkIfForcedForUpdate();

		add_filter( 'plugins_api', [ $this, 'pluginInfo' ], 20, 3 );
		add_filter( 'site_transient_update_plugins', [ $this, 'pushUpdate' ] );
		add_action( 'upgrader_process_complete', [ $this, 'afterUpdate' ], 10, 2 );
		add_filter( 'plugin_row_meta', [ $this, 'addCheckForUpdateButton' ], 10, 2 );
		add_filter( 'upgrader_pre_download', [ $this, 'blockExpiredUpdates' ], 10, 3 );
		add_action( 'in_plugin_update_message-' . $this->pluginSlug . '/init.php', [ $this, 'pluginUpdateMessage' ], 10, 2 );
	}

	public function pluginInfo ( $res, $action, $args )
	{
		if ( $action !== 'plugin_information' || $args->slug !== $this->pluginSlug )
			return $res;

		$remote = $this->getTransient();

		if ( $remote )
		{
			$res = new stdClass();

			$res->name         = $remote->name;
			$res->slug         = $this->pluginSlug;
			$res->tested       = $remote->tested;
			$res->version      = $remote->version;
			$res->last_updated = $remote->last_updated;

			$res->author         = '<a href="https://www.fs-code.com">FS Code</a>';
			$res->author_profile = 'https://www.fs-code.com';

			$res->sections = [
				'description' => $remote->sections->description,
				'changelog'   => $remote->sections->changelog
			];

			return $res;
		}

		return $res;
	}

	public function pushUpdate ( $transient )
	{
		if ( empty( $transient->checked ) )
			return $transient;

		$remote = $this->getTransient();

		if ( $remote && is_object( $remote ) && version_compare( PluginHelper::getVersion(), $remote->version, '<' ) )
		{
			$res = new stdClass();

			$res->slug          = $this->pluginSlug;
			$res->plugin        = $this->pluginBase;
			$res->new_version   = $remote->version;
			$res->tested        = $remote->tested;
			$res->package       = $remote->download_url ?? '';
			$res->update_notice = $remote->update_notice ?? '';
			$res->compatibility = new stdClass();

			$transient->response[ $res->plugin ] = $res;
		}

		return $transient;
	}

	public function afterUpdate ( $upgrader_object, $options )
	{
		if ( $options[ 'action' ] === 'update' && $options[ 'type' ] === 'plugin' )
			Settings::delete( 'update_transient_cache', true );
	}

	public function addCheckForUpdateButton ( $links, $file )
	{
		if ( strpos( $file, $this->pluginBase ) !== false )
		{
			$urlParameters = [
				'fsp_check_for_update'  => '1',
				'_wpnonce'              => wp_create_nonce( 'fsp_check_for_update' )
			];
			$url = 'plugins.php?' . http_build_query( $urlParameters );

			$links['check_for_update'] = '<a href="' . $url . '">' . fsp__('Check for update') . '</a>';
		}

		return $links;
	}

	public function blockExpiredUpdates ( $reply, $package, $extra_data )
	{
		if ( $reply !== false )
			return $reply;

		if ( ! isset( $extra_data, $extra_data->skin, $extra_data->skin->plugin_info ) )
			return false;

		if ( ($extra_data->skin->plugin_info[ 'TextDomain' ] ?? null ) !== $this->pluginSlug )
			return false;

		$remote = $this->getTransient();

		if ( ! $remote || empty( $remote->update_notice ) )
			return false;

		$update_notice = '<div class="fsp-plugin-blocked-notice">' . $remote->update_notice . '</div>';

		return new WP_Error( $this->pluginSlug . '_subscription_expired', $update_notice );
	}

	public function pluginUpdateMessage ( $plugin_data, $extra_data )
	{
		if ( empty( $extra_data->package ) && ! empty( $extra_data->update_notice ) )
		{
			echo '<div class="fsp-plugin-update-notice">' . $extra_data->update_notice . '</div>';
		}
	}

	/*
	 * First check if temporary cache is available, if it is, use it
	 * Second check long-live cache, and if it is in the expiration timeframe, use it
	 * If neither cache is available, then request to remote server and cache it
	 */
	private function getTransient ()
	{
		if ( isset( $this->transient ) )
			return $this->transient;

		try
		{
			$transientCache = Settings::get( 'update_transient_cache', false, true );
			$transientCache = json_decode( $transientCache, false );

			if ( empty( $transientCache ) )
				throw new Exception();

			$transient = $transientCache->transient;
			$time      = $transientCache->time;
		}
		catch ( Exception $e )
		{
			$transient = false;
		}

		if ( ! $transient || Date::epoch() - $time > $this->expiration )
		{
			$accessToken = Settings::get( 'license_access_token', '', true );

			$apiClient = new LicenseApiClient();
			$apiClient->setAccessToken( $accessToken );

			$result = $apiClient->sendClientRequest('check_update', 'POST', [
				'site_url'          => network_site_url(),
				'license_code'      => Settings::get( 'license_code', '', true ),
				'version'           => PluginHelper::getVersion(),
				'is_manual_check'   => $this->isForcedUpdate
			]);

			if ( isset( $result['data'] ) )
			{
				// long-live cache
				Settings::set( 'update_transient_cache', json_encode( [
					'time'      => Date::epoch(),
					'transient' => $result['data']
				] ), true );
			}
			else
			{
				Settings::set( 'update_transient_cache', json_encode( [
					'time'      => Date::epoch(),
					'transient' => 1
				] ), true );
			}
		}

		$this->transient = $transient;

		return $transient;
	}

	/*
	 * Set expiration limit to 1 minute if update check is forced
	 * There should be at lease 1 minute difference between two requests
	 */
	private function checkIfForcedForUpdate ()
	{
		$checkUpdate    = Request::get( 'fsp_check_for_update', '', 'string' );
		$_wpnonce       = Request::get( '_wpnonce', '', 'string' );

		if ( $checkUpdate === '1' && wp_verify_nonce( $_wpnonce, 'fsp_check_for_update' ) )
		{
			$this->isForcedUpdate = true;
			$this->expiration     = 60; // if forced set time limit 60 seconds
		}
	}

}
