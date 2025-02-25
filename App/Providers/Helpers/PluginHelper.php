<?php

namespace FSPoster\App\Providers\Helpers;

use FSPoster\App\Models\App;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelLabel;
use FSPoster\App\Models\ChannelLabelsData;
use FSPoster\App\Models\ChannelPermission;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Data;
use FSPoster\App\Models\Planner;
use FSPoster\App\Models\PostComment;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\DB;

class PluginHelper
{

	public static function isPluginActivated (): bool
	{
		return ! empty( Settings::get( 'license_code', '', true ) );
	}

    public static function isPluginDisabled (): bool
    {
        return (int)Settings::get( 'plugin_disabled', '0', true ) > 0;
    }

	public static function canAccessToSettings () : bool
	{
		return current_user_can( 'administrator' ) || PluginHelper::isDemoVersion();
	}

	public static function canUserAccessToPlugin (): bool
	{
		$showFsPosterTo = Settings::get( 'show_fs_poster_to', [] );

		$userInf   = wp_get_current_user();
		$userRoles = $userInf->roles;

		if ( empty( $showFsPosterTo ) || in_array( 'administrator', $userRoles ) )
			return false;

		foreach ( $userRoles as $roleId )
		{
			if ( in_array( $roleId, $showFsPosterTo ) )
				return false;
		}

		return true;
	}

    public static function getVersion (): string
    {
        $plugin_data = get_file_data( FSP_ROOT_DIR . '/init.php', [ 'Version' => 'Version' ], false );

        return $plugin_data['Version'] ?? '7.0.0';
    }

    public static function removePlugin () : void
    {
        $fsPurchaseKey        = Settings::get( 'license_code', '', true );
        $checkPurchaseCodeURL = FSP_API_URL . "api.php?act=delete&purchase_code=" . urlencode( $fsPurchaseKey ) . "&domain=" . network_site_url();

        Curl::getURL( $checkPurchaseCodeURL );

        $fsTables = [
            DB::table( App::class ),
            DB::table( ChannelLabel::class ),
            DB::table( ChannelLabelsData::class ),
            DB::table( ChannelSession::class ),
            DB::table( Data::class ),
            DB::table( Schedule::class ),
            DB::table( PostComment::class ),
            DB::table( Planner::class ),
            DB::table( Channel::class ),
            DB::table( ChannelPermission::class ),
        ];

        foreach ( $fsTables as $tableName )
        {
            DB::DB()->query( "DROP TABLE IF EXISTS `" . $tableName . "`" );
        }

        DB::DB()->query( 'DELETE FROM `' . DB::DB()->base_prefix . 'options` WHERE `option_name` LIKE "fs_%"' );

        if ( is_multisite() )
        {
            DB::DB()->query( 'DELETE FROM `' . DB::DB()->base_prefix . 'sitemeta` WHERE `meta_key` LIKE "fs_%"' );
        }

        DB::DB()->query( "DELETE FROM " . DB::WPtable( 'posts', true ) . " WHERE post_type='fsp_post'" );
    }

	public static function isDemoVersion () : bool
	{
		return defined( 'FSP_IS_DEMO' ) && FSP_IS_DEMO;
	}

	public static function isDevelopmentMode () : bool
	{
		return defined( "FS_POSTER_DEV" ) && FS_POSTER_DEV;
	}

}
