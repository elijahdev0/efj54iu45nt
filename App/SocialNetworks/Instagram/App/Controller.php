<?php

namespace FSPoster\App\SocialNetworks\Instagram\App;

use Exception;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\SocialNetworks\Instagram\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Instagram\Api\CookieMethod\Api as CookieMethodApi;
use FSPoster\App\SocialNetworks\Instagram\Api\CookieMethod\AuthData as CookieMethodAuthData;
use FSPoster\App\SocialNetworks\Instagram\Api\LoginPassMethod\Api as LoginPassMethodApi;
use FSPoster\App\SocialNetworks\Instagram\Api\LoginPassMethod\AuthData as LoginPassMethodAuthData;

class Controller
{
    /**
     * @throws Exception
     */
    public static function addChannelViaPassword ( RestRequest $request ): array
    {
        $username = $request->require( 'username', RestRequest::TYPE_STRING, fsp__( 'Please enter the username' ) );
        $password = $request->require( 'password', RestRequest::TYPE_STRING, fsp__( 'Please enter the password' ) );
        $proxy    = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

		$authData = new LoginPassMethodAuthData();
		$authData->username = $username;
		$authData->pass = $password;

		$api = new LoginPassMethodApi();
	    $result = $api->setProxy( $proxy )
		    ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData )
		    ->login();

        if ( $result[ 'data' ][ 'needs_challenge' ] )
        {
            return [ 'options' => $result[ 'data' ][ 'options' ] ];
        }

	    $channels = ChannelAdapter::fetchChannels( $api, 'login_pass' );

        return [
            'channels' => $channels
        ];
    }

    /**
     * @throws Exception
     */
    public static function confirmTwoFactor ( RestRequest $request ): array
    {
        $options = $request->require( 'options', RestRequest::TYPE_ARRAY, fsp__( 'Options are empty' ) );
        $code    = $request->require( 'code', RestRequest::TYPE_STRING, fsp__( 'Please enter the code' ) );
        $proxy   = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

		if( empty( $options['auth_data'] ) )
			throw new Exception( fsp__( 'Something went wrong!' ) );

	    $authData = new LoginPassMethodAuthData();
	    $authData->setFromArray( $options['auth_data'] );

	    $api = new LoginPassMethodApi();
	    $api->setProxy( $proxy )
		    ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData )
	        ->doTwoFactorAuth( $options[ 'two_factor_identifier' ], $code, $options[ 'verification_method' ] );

	    $channels = ChannelAdapter::fetchChannels( $api, 'login_pass' );

        return [
            'channels' => $channels
        ];
    }

    /**
     * @throws Exception
     */
    public static function addChannelCookieMethod ( RestRequest $request ): array
    {
        $cookie_csrf_token = $request->require( 'cookie_csrf_token', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'csrf_token' ] ) );
        $cookie_ds_user_id = $request->require( 'cookie_ds_user_id', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'ds_user_id' ] ) );
        $cookie_sessionid  = $request->require( 'cookie_sessionid', RestRequest::TYPE_STRING, fsp__( 'Please enter the cookie "%s"', [ 'sessionid' ] ) );
        $proxy             = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

        $cookiesArr = [
            [
                "Name"     => "csrftoken",
                "Value"    => $cookie_csrf_token,
                "Domain"   => ".instagram.com",
                "Path"     => "/",
                "Max-Age"  => null,
                "Expires"  => null,
                "Secure"   => true,
                "Discard"  => false,
                "HttpOnly" => true,
            ],
            [
                "Name"     => "ds_user_id",
                "Value"    => $cookie_ds_user_id,
                "Domain"   => ".instagram.com",
                "Path"     => "/",
                "Max-Age"  => null,
                "Expires"  => null,
                "Secure"   => true,
                "Discard"  => false,
                "HttpOnly" => true,
            ],
            [
                "Name"     => "sessionid",
                "Value"    => $cookie_sessionid,
                "Domain"   => ".instagram.com",
                "Path"     => "/",
                "Max-Age"  => null,
                "Expires"  => null,
                "Secure"   => true,
                "Discard"  => false,
                "HttpOnly" => true,
            ],
        ];

	    $authData = new CookieMethodAuthData();
	    $authData->cookies = $cookiesArr;

		$api = new CookieMethodApi();
		$api->setProxy( $proxy )
			->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

	    $channels = ChannelAdapter::fetchChannels( $api, 'cookie' );

        return [ 'channels' => $channels ];
    }

    /**
     * @throws Exception
     */
    public static function saveSettings ( RestRequest $request ): array
    {
        $postText            = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $cutPostText         = (int)$request->param( 'cut_post_text', false, RestRequest::TYPE_BOOL );
        $mediaTypeToUpload         = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );
        $pinThePost          = (int)$request->param( 'pin_the_post', false, RestRequest::TYPE_BOOL );
        $shareToFirstComment = (int)$request->param( 'share_to_first_comment', false, RestRequest::TYPE_BOOL );
        $firstCommentText    = $request->param( 'first_comment_text', '', RestRequest::TYPE_STRING );

        $storyText        = $request->param( 'story_text', '', RestRequest::TYPE_STRING );
        $storySendLink    = (int)$request->param( 'story_send_link', false, RestRequest::TYPE_BOOL );

        $storyCustomizationBgColor         = $request->param( 'story_customization_bg_color', '636e72', RestRequest::TYPE_STRING );
        $storyCustomizationTitleBgColor    = $request->param( 'story_customization_title_bg_color', '000000', RestRequest::TYPE_STRING );
        $storyCustomizationTitleBgOpacity  = $request->param( 'story_customization_title_bg_opacity', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleColor      = $request->param( 'story_customization_title_color', 'FFFFFF', RestRequest::TYPE_STRING );
        $storyCustomizationTitleTopOffset  = $request->param( 'story_customization_title_top_offset', 125, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleLeftOffset = $request->param( 'story_customization_title_left_offset', 0, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleWidth      = $request->param( 'story_customization_title_width', 660, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleFontSize   = $request->param( 'story_customization_title_font_size', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleFontFamily = $request->param( 'story_customization_title_font_family', '', RestRequest::TYPE_STRING );
        $storyCustomizationIsRtl           = (int)$request->param( 'story_customization_is_rtl', false, RestRequest::TYPE_BOOL );

        $storyCustomizationLinkBgColor    = $request->param( 'story_customization_link_bg_color', '000000', RestRequest::TYPE_STRING );
        $storyCustomizationLinkBgOpacity  = $request->param( 'story_customization_link_bg_opacity', 100, RestRequest::TYPE_INTEGER );
        $storyCustomizationLinkColor      = $request->param( 'story_customization_link_color', '3468CF', RestRequest::TYPE_STRING );
        $storyCustomizationLinkTopOffset  = $request->param( 'story_customization_link_top_offset', 1000, RestRequest::TYPE_INTEGER );
        $storyCustomizationLinkLeftOffset = $request->param( 'story_customization_link_left_offset', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationLinkWidth      = $request->param( 'story_customization_link_width', 660, RestRequest::TYPE_INTEGER );
        $storyCustomizationLinkFontSize   = $request->param( 'story_customization_link_font_size', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationLinkFontFamily = $request->param( 'story_customization_link_font_family', '', RestRequest::TYPE_STRING );

        $storyCustomizationAddHashtag        = (int)$request->param( 'story_customization_add_hashtag', false, RestRequest::TYPE_BOOL );
        $storyCustomizationHashtagText       = $request->param( 'story_customization_hashtag_text', '', RestRequest::TYPE_STRING );
        $storyCustomizationHashtagBgColor    = $request->param( 'story_customization_hashtag_bg_color', '000000', RestRequest::TYPE_STRING );
        $storyCustomizationHashtagBgOpacity  = $request->param( 'story_customization_hashtag_bg_opacity', 100, RestRequest::TYPE_INTEGER );
        $storyCustomizationHashtagColor      = $request->param( 'story_customization_hashtag_color', '3468CF', RestRequest::TYPE_STRING );
        $storyCustomizationHashtagTopOffset  = $request->param( 'story_customization_hashtag_top_offset', 700, RestRequest::TYPE_INTEGER );
        $storyCustomizationHashtagLeftOffset = $request->param( 'story_customization_hashtag_left_offset', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationHashtagWidth      = $request->param( 'story_customization_hashtag_width', 660, RestRequest::TYPE_INTEGER );
        $storyCustomizationHashtagFontSize   = $request->param( 'story_customization_hashtag_font_size', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationHashtagFontFamily = $request->param( 'story_customization_hashtag_font_family', '', RestRequest::TYPE_STRING );

        if ( $shareToFirstComment && empty( $firstCommentText ) )
            throw new Exception( fsp__( 'First comment can\'t be empty if enabled' ) );

		if( ! $shareToFirstComment )
			$firstCommentText = '';

        if ( $storyCustomizationAddHashtag && empty( $storyCustomizationHashtagText ) )
            throw new Exception( fsp__( 'Hashtag comment can\'t be empty if enabled' ) );

		if( ! $storyCustomizationAddHashtag )
			$storyCustomizationHashtagText = '';

        $fonts       = Settings::get( 'google_fonts', [] );
        $wpUploadDir = wp_upload_dir();

        $saveFontFiles = function ( $target, $fontFamily ) use ( $fonts, $wpUploadDir )
        {
            if ( ! empty( $fontFamily ) )
            {
                if ( empty( $wpUploadDir[ 'basedir' ] ) )
                    throw new Exception( 'Wordpress upload directory is not writeable to save processed media files' );

                $existingFontFile = Settings::get( 'instagram_story_customization_' . $target . '_font_file', '' );

                if ( ! empty( $existingFontFile ) && file_exists( $existingFontFile ) )
                    unlink( $existingFontFile );

                if ( ! empty( $fonts[ $fontFamily ] ) )
                {
                    $newFontFile = implode( DIRECTORY_SEPARATOR, [ $wpUploadDir['basedir'], FSP_PLUGIN_SLUG, 'fonts', $fontFamily . '-instagram-story-customization-' . $target . '-font-file.ttf' ] );
                    Helper::downloadRemoteFile( $newFontFile, $fonts[$fontFamily]['original_font'] );
                    Settings::set( 'instagram_story_customization_' . $target . '_font_file', $newFontFile );
                }
				else
                {
                    throw new Exception( fsp__( 'Selected font is now available' ) );
                }
            }
			else
            {
	            Settings::delete( 'instagram_story_customization_' . $target . '_font_file' );
            }
        };

        $saveFontFiles( 'title', $storyCustomizationTitleFontFamily );
        $saveFontFiles( 'hashtag', $storyCustomizationHashtagFontFamily );
        $saveFontFiles( 'link', $storyCustomizationLinkFontFamily );

        Settings::set( 'instagram_post_text', $postText );
        Settings::set( 'instagram_cut_post_text', $cutPostText );
        Settings::set( 'instagram_media_type_to_upload', $mediaTypeToUpload );
        Settings::set( 'instagram_pin_the_post', $pinThePost );
        Settings::set( 'instagram_share_to_first_comment', $shareToFirstComment );
        Settings::set( 'instagram_first_comment_text', $firstCommentText );

        Settings::set( 'instagram_story_text', $storyText );
        Settings::set( 'instagram_story_send_link', $storySendLink );

        Settings::set( 'instagram_story_customization_bg_color', $storyCustomizationBgColor );
        Settings::set( 'instagram_story_customization_title_bg_color', $storyCustomizationTitleBgColor );
        Settings::set( 'instagram_story_customization_title_bg_opacity', $storyCustomizationTitleBgOpacity );
        Settings::set( 'instagram_story_customization_title_color', $storyCustomizationTitleColor );
        Settings::set( 'instagram_story_customization_title_top_offset', $storyCustomizationTitleTopOffset );
        Settings::set( 'instagram_story_customization_title_left_offset', $storyCustomizationTitleLeftOffset );
        Settings::set( 'instagram_story_customization_title_width', $storyCustomizationTitleWidth );
        Settings::set( 'instagram_story_customization_title_font_size', $storyCustomizationTitleFontSize );
        Settings::set( 'instagram_story_customization_title_font_family', $storyCustomizationTitleFontFamily );
        Settings::set( 'instagram_story_customization_is_rtl', $storyCustomizationIsRtl );

        Settings::set( 'instagram_story_customization_link_bg_color', $storyCustomizationLinkBgColor );
        Settings::set( 'instagram_story_customization_link_bg_opacity', $storyCustomizationLinkBgOpacity );
        Settings::set( 'instagram_story_customization_link_color', $storyCustomizationLinkColor );
        Settings::set( 'instagram_story_customization_link_top_offset', $storyCustomizationLinkTopOffset );
        Settings::set( 'instagram_story_customization_link_left_offset', $storyCustomizationLinkLeftOffset );
        Settings::set( 'instagram_story_customization_link_width', $storyCustomizationLinkWidth );
        Settings::set( 'instagram_story_customization_link_font_size', $storyCustomizationLinkFontSize );
        Settings::set( 'instagram_story_customization_link_font_family', $storyCustomizationLinkFontFamily );
        Settings::set( 'instagram_story_customization_add_hashtag', $storyCustomizationAddHashtag );
        Settings::set( 'instagram_story_customization_hashtag_text', $storyCustomizationHashtagText );
        Settings::set( 'instagram_story_customization_hashtag_bg_color', $storyCustomizationHashtagBgColor );
        Settings::set( 'instagram_story_customization_hashtag_bg_opacity', $storyCustomizationHashtagBgOpacity );
        Settings::set( 'instagram_story_customization_hashtag_color', $storyCustomizationHashtagColor );
        Settings::set( 'instagram_story_customization_hashtag_top_offset', $storyCustomizationHashtagTopOffset );
        Settings::set( 'instagram_story_customization_hashtag_left_offset', $storyCustomizationHashtagLeftOffset );
        Settings::set( 'instagram_story_customization_hashtag_width', $storyCustomizationHashtagWidth );
        Settings::set( 'instagram_story_customization_hashtag_font_size', $storyCustomizationHashtagFontSize );
        Settings::set( 'instagram_story_customization_hashtag_font_family', $storyCustomizationHashtagFontFamily );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
        $fonts = Settings::get( 'google_fonts', [] );

        $titleFontId = Settings::get( 'instagram_story_customization_title_font_family', '' );
        $titleFont   = $fonts[ $titleFontId ] ?? null;

        $linkFontId = Settings::get( 'instagram_story_customization_link_font_family', '' );
        $linkFont   = $fonts[ $linkFontId ] ?? null;

        $hashtagFontId = Settings::get( 'instagram_story_customization_hashtag_font_family', '' );
        $hashtagFont   = $fonts[ $hashtagFontId ] ?? null;

	    return apply_filters('fsp_get_settings', [
		    'post_text'                                 => Settings::get( 'instagram_post_text', '{post_title}' ),
		    'cut_post_text'                             => (bool)Settings::get( 'instagram_cut_post_text', true ),
		    'media_type_to_upload'                      => Settings::get( 'instagram_media_type_to_upload', 'featured_image' ),
		    'pin_the_post'                              => (bool)Settings::get( 'instagram_pin_the_post', false ),
		    'share_to_first_comment'                    => (bool)Settings::get( 'instagram_share_to_first_comment', false ),
		    'first_comment_text'                        => Settings::get( 'instagram_first_comment_text', '' ),

		    'story_text'                                => Settings::get( 'instagram_story_text', '{post_title}' ),
		    'story_send_link'                           => (bool)Settings::get( 'instagram_story_send_link', false ),

		    'story_customization_bg_color'              => Settings::get( 'instagram_story_customization_bg_color', '636e72' ),
		    'story_customization_title_bg_color'        => Settings::get( 'instagram_story_customization_title_bg_color', '000000' ),
		    'story_customization_title_bg_opacity'      => (int)Settings::get( 'instagram_story_customization_title_bg_opacity', 30 ),
		    'story_customization_title_color'           => Settings::get( 'instagram_story_customization_title_color', 'FFFFFF' ),
		    'story_customization_title_top_offset'      => Settings::get( 'instagram_story_customization_title_top_offset', 125 ),
		    'story_customization_title_left_offset'     => (int)Settings::get( 'instagram_story_customization_title_left_offset', 30 ),
		    'story_customization_title_width'           => (int)Settings::get( 'instagram_story_customization_title_width', 660 ),
		    'story_customization_title_font_size'       => (int)Settings::get( 'instagram_story_customization_title_font_size', 30 ),
		    'story_customization_is_rtl'                => (bool)Settings::get( 'instagram_story_customization_is_rtl', false ),
		    'story_customization_title_font_family'     => $titleFont,

		    'story_customization_link_bg_color'         => Settings::get( 'instagram_story_customization_link_bg_color', '000000' ),
		    'story_customization_link_bg_opacity'       => (int)Settings::get( 'instagram_story_customization_link_bg_opacity', 100 ),
		    'story_customization_link_color'            => Settings::get( 'instagram_story_customization_link_color', '3468CF' ),
		    'story_customization_link_top_offset'       => (int)Settings::get( 'instagram_story_customization_link_top_offset', 1000 ),
		    'story_customization_link_left_offset'      => (int)Settings::get( 'instagram_story_customization_link_left_offset', 30 ),
		    'story_customization_link_width'            => (int)Settings::get( 'instagram_story_customization_link_width', 660 ),
		    'story_customization_link_font_size'        => (int)Settings::get( 'instagram_story_customization_link_font_size', 30 ),
		    'story_customization_link_font_family'      => $linkFont,

		    // doit InstagramHelperde hashtagi PostingData`dan yox optionsdan chagirir.
		    'story_customization_add_hashtag'           => (bool)Settings::get( 'instagram_story_customization_add_hashtag', false ),
		    'story_customization_hashtag_text'          => Settings::get( 'instagram_story_customization_hashtag_text', '' ),
		    'story_customization_hashtag_bg_color'      => Settings::get( 'instagram_story_customization_hashtag_bg_color', '000000' ),
		    'story_customization_hashtag_bg_opacity'    => (int)Settings::get( 'instagram_story_customization_hashtag_bg_opacity', 100 ),
		    'story_customization_hashtag_color'         => Settings::get( 'instagram_story_customization_hashtag_color', '3468CF' ),
		    'story_customization_hashtag_top_offset'    => (int)Settings::get( 'instagram_story_customization_hashtag_top_offset', 700 ),
		    'story_customization_hashtag_left_offset'   => (int)Settings::get( 'instagram_story_customization_hashtag_left_offset', 30 ),
		    'story_customization_hashtag_width'         => (int)Settings::get( 'instagram_story_customization_hashtag_width', 660 ),
		    'story_customization_hashtag_font_size'     => (int)Settings::get( 'instagram_story_customization_hashtag_font_size', 30 ),
		    'story_customization_hashtag_font_family'   => $hashtagFont
	    ], Bootstrap::getInstance()->getSlug());
    }
}