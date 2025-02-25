<?php

namespace FSPoster\App\Providers\Core;

use FSPoster\App\Providers\DB\DB;

class Settings
{

	const PREFIX = 'fsp_';

	public static function get ( string $optionName, $default = null, bool $network_option = false )
	{
		$network_option = !is_multisite() && $network_option ? false : $network_option;
		$fnName         = $network_option ? 'get_site_option' : 'get_option';

		return $fnName( self::PREFIX . $optionName, $default );
	}

	public static function getWithRawQuery ( string $optionName, $default = null )
	{
		$optionNameFull = sprintf( '%s%s', Settings::PREFIX, $optionName );

		$getOption = DB::DB()->get_results( DB::DB()->prepare( "SELECT * FROM `".DB::DB()->base_prefix."options` WHERE `option_name`=%s", $optionNameFull ) );

		return ! empty( $getOption ) ? $getOption[0]->option_value : $default;
	}

	public static function set ( string $optionName, $optionValue, bool $network_option = false, $autoLoad = null )
	{
		$network_option = !is_multisite() && $network_option ? false : $network_option;
		$fnName         = $network_option ? 'update_site_option' : 'update_option';

		$arguments = [ self::PREFIX . $optionName, $optionValue ];

		if ( !is_null( $autoLoad ) && !$network_option )
		{
			$arguments[] = $autoLoad;
		}

		return call_user_func_array( $fnName, $arguments );
	}


	public static function delete ( string $optionName, bool $network_option = false )
	{
		$network_option = !is_multisite() && $network_option ? false : $network_option;
		$fnName         = $network_option ? 'delete_site_option' : 'delete_option';

		return $fnName( self::PREFIX . $optionName );
	}

}