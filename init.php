<?php
/*
 * Plugin Name: FS Poster
 * Description: The World's #1-Ranked Social Media Auto Poster & Scheduler, Streamlining Seamless Content Sharing Across Many Platforms.
 * Version: 7.1.7
 * Author: FS Code
 * Author URI: https://www.fs-code.com
 * License: commercial
 * Text Domain: fs-poster
 */

defined( 'ABSPATH' ) or exit;

require_once __DIR__ . '/vendor/autoload.php';

new FSPoster\App\Providers\Core\Bootstrap();

$networks = [
    'Facebook',
    'Instagram',
    'Threads',
	'Tiktok',
	'Twitter',
    'Linkedin',
    'Pinterest',
    'Telegram',
    'Reddit',
    'Youtube',
    'GoogleBusinessProfile',
    'Tumblr',
    'Vk',
    'Odnoklassniki',
    'Medium',
    'WordPress',
    'Webhook',
    'Blogger',
    'Plurk',
    'Xing',
    'Discord',
    'Mastodon',
];

foreach ( $networks as $network )
{
    require_once __DIR__ . '/App/SocialNetworks/' . $network . '/init.php';
}
function fsp__ ( $text, $binds = [], $esc_html = false ): string
{
	$text = $esc_html ? esc_html__( $text, FSP_PLUGIN_SLUG ) : __( $text, FSP_PLUGIN_SLUG );

	if ( !empty( $binds ) && is_array( $binds ) )
		$text = vsprintf( $text, $binds );

	return $text ?: '';
}
