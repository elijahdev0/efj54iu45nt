<?php

namespace FSPoster\App\SocialNetworks\Facebook\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\SocialNetworks\Facebook\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod\AuthData;
use FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod\Api;

class Controller
{
    /**
     * @throws Exception
     */
    public static function addChannelViaCookies ( RestRequest $request ): array
    {
        $cookieCuser    = $request->require( 'cookie_c_user', RestRequest::TYPE_STRING, fsp__( 'Please, enter the cookie "%s"', [ 'c_user' ] ) );
        $cookieXs       = $request->require( 'cookie_xs', RestRequest::TYPE_STRING, fsp__( 'Please, enter the cookie "%s"', [ 'xs' ] ) );
        $proxy          = $request->param( 'proxy', '', RestRequest::TYPE_STRING );

	    $authData = new AuthData();
	    $authData->fbUserId = $cookieCuser;
		$authData->fbSess = $cookieXs;
		$authData->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $api = new Api();
        $api->setProxy( $proxy )
	        ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

        try
        {
	        $channels = ChannelAdapter::fetchChannels( $api, 'cookie' );
        } catch ( Exception $e )
        {
            throw new Exception( fsp__( 'The entered cookies are wrong' ) );
        }

		if( $api->needsSessionUpdate && ! empty( $channels ) )
			ChannelAdapter::updateChannelCookies( $channels[0]['channel_session_id'], $api->authData );

	    return [ 'channels' => $channels ];
    }

    /**
     * @throws Exception
     */
    public static function getPages ( RestRequest $request ): array
    {
        $id = $request->require( 'group_id', RestRequest::TYPE_INTEGER, fsp__( 'Group ID is required' ) );

        $groupExists = Channel::where( 'id', $id )->where( 'channel_type', 'group' )->fetch();

        if ( empty( $groupExists ) )
        {
            throw new Exception( fsp__( 'You don\'t have access to this group' ) );
        }

        $pages = Channel::where( 'channel_session_id', $groupExists->channel_session_id )->where( 'channel_type', 'ownpage' )->fetchAll();

        $posters = [];

        $channelSession = $groupExists->channel_session->fetch();

        foreach ( $pages as $page )
        {
            $posters[] = [
                'id'             => (int)$page->id,
                'social_network' => Bootstrap::getInstance()->getSlug(),
                'name'           => $page->name,
                'picture'        => $page->picture,
                'channel_link'   => apply_filters( 'fsp_get_channel_link', '', $channelSession->social_network, $groupExists ),
                'status'         => $page->status === 1,
            ];
        }

        return [ 'pages' => $posters ];
    }

    /**
     * @throws Exception
     */
    public static function saveSettings ( RestRequest $request ): array
    {
        $importComments                     = (int)$request->param( 'import_comments', false, RestRequest::TYPE_BOOL );
        $importCommentsPublishedIn          = $request->param( 'import_comments_published_in', 'last_week', RestRequest::TYPE_STRING, [ 'last_week', 'last_2_weeks', 'last_3_weeks', 'last_month' ] );

        $postText                           = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
	    $postAttachLink                     = $request->param( 'post_attach_link', true, RestRequest::TYPE_BOOL );
	    $postUploadMedia                    = $request->param( 'post_upload_media', false, RestRequest::TYPE_BOOL );
	    $postMediaTypeToUpload              = $request->param( 'post_media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );
	    $shareToFirstComment                = (int)$request->param( 'share_to_first_comment', false, RestRequest::TYPE_BOOL );
	    $firstCommentText                   = $request->param( 'first_comment_text', '', RestRequest::TYPE_STRING );

	    $storyText                          = $request->param( 'story_text', '', RestRequest::TYPE_STRING );
        $storyAttachLink                    = (int)$request->param( 'story_attach_link', false, RestRequest::TYPE_BOOL );
        $storyCustomizationBgColor          = $request->param( 'story_customization_bg_color', '636e72', RestRequest::TYPE_STRING );
        $storyCustomizationTitleBgColor     = $request->param( 'story_customization_title_bg_color', '000000', RestRequest::TYPE_STRING );
        $storyCustomizationTitleBgOpacity   = $request->param( 'story_customization_title_bg_opacity', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleColor       = $request->param( 'story_customization_title_color', 'FFFFFF', RestRequest::TYPE_STRING );
        $storyCustomizationTitleTopOffset   = $request->param( 'story_customization_title_top_offset', 120, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleLeftOffset  = $request->param( 'story_customization_title_left_offset', 0, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleWidth       = $request->param( 'story_customization_title_width', 660, RestRequest::TYPE_INTEGER );
        $storyCustomizationTitleFontSize    = $request->param( 'story_customization_title_font_size', 30, RestRequest::TYPE_INTEGER );
        $storyCustomizationIsRtl            = (int)$request->param( 'story_customization_is_rtl', false, RestRequest::TYPE_BOOL );
        $storyCustomizationTitleFontFamily  = $request->param( 'story_customization_title_font_family', '', RestRequest::TYPE_STRING );

	    if ( $shareToFirstComment && empty( $firstCommentText ) )
		    throw new Exception( fsp__( 'First comment can\'t be empty if enabled' ) );

	    if( ! $shareToFirstComment )
		    $firstCommentText = '';

        if ( !empty( $storyCustomizationTitleFontFamily ) )
        {
            $fonts = Settings::get( 'google_fonts', [] );

            $wpUploadDir = wp_upload_dir();

            if ( empty( $wpUploadDir[ 'basedir' ] ) )
            {
                throw new Exception( 'Wordpress upload directory is not writeable to save processed media files' );
            }

            $existingFontFile = Settings::get( 'fb_story_customization_title_font_file', '' );

            if ( !empty( $existingFontFile ) && file_exists( $existingFontFile ) )
            {
                unlink( $existingFontFile );
            }

            if ( !empty( $fonts[ $storyCustomizationTitleFontFamily ] ) )
            {
                $newFontFile = implode( DIRECTORY_SEPARATOR, [ $wpUploadDir[ 'basedir' ], FSP_PLUGIN_SLUG, 'fonts', $storyCustomizationTitleFontFamily . '-fb-story-customization-title-font-file.ttf' ] );
                Helper::downloadRemoteFile( $newFontFile, $fonts[ $storyCustomizationTitleFontFamily ][ 'original_font' ] );
                Settings::set( 'fb_story_customization_title_font_file', $newFontFile );
            } else
            {
                throw new Exception( fsp__( 'Selected font is now available' ) );
            }
        } else
        {
            Settings::delete( 'fb_story_customization_title_font_file' );
        }

        Settings::set( 'fb_import_comments', $importComments );
        Settings::set( 'fb_import_comments_published_in', $importCommentsPublishedIn );

        Settings::set( 'fb_post_text', $postText );
	    Settings::set( 'fb_post_attach_link', (int)$postAttachLink );
	    Settings::set( 'fb_post_upload_media', (int)$postUploadMedia );
	    Settings::set( 'fb_post_media_type_to_upload', $postMediaTypeToUpload );
	    Settings::set( 'fb_share_to_first_comment', $shareToFirstComment );
	    Settings::set( 'fb_first_comment_text', $firstCommentText );

        Settings::set( 'fb_story_text', $storyText );
        Settings::set( 'fb_story_attach_link', $storyAttachLink );
        Settings::set( 'fb_story_customization_bg_color', $storyCustomizationBgColor );
        Settings::set( 'fb_story_customization_title_bg_color', $storyCustomizationTitleBgColor );
        Settings::set( 'fb_story_customization_title_bg_opacity', $storyCustomizationTitleBgOpacity );
        Settings::set( 'fb_story_customization_title_color', $storyCustomizationTitleColor );
        Settings::set( 'fb_story_customization_title_top_offset', $storyCustomizationTitleTopOffset );
        Settings::set( 'fb_story_customization_title_left_offset', $storyCustomizationTitleLeftOffset );
        Settings::set( 'fb_story_customization_title_width', $storyCustomizationTitleWidth );
        Settings::set( 'fb_story_customization_title_font_size', $storyCustomizationTitleFontSize );
        Settings::set( 'fb_story_customization_is_rtl', $storyCustomizationIsRtl );
        Settings::set( 'fb_story_customization_title_font_family', $storyCustomizationTitleFontFamily );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
        $fonts  = Settings::get( 'google_fonts', [] );
        $fontId = Settings::get( 'fb_story_customization_title_font_family', '' );
        $font   = $fonts[ $fontId ] ?? null;

	    return apply_filters('fsp_get_settings', [
		    'import_comments'                       => (bool)Settings::get( 'fb_import_comments', false ),
		    'import_comments_published_in'          => Settings::get( 'fb_import_comments_published_in', 'last_week' ),
		    'import_comments_published_in_options'  => [
			    [ 'label' => fsp__( 'Last week' ), 'value' => 'last_week' ],
			    [ 'label' => fsp__( 'Last 2 weeks' ), 'value' => 'last_2_weeks' ],
			    [ 'label' => fsp__( 'Last 3 weeks' ), 'value' => 'last_3_weeks' ],
			    [ 'label' => fsp__( 'Last month' ), 'value' => 'last_month' ],
		    ],
		    'post_text'                             => Settings::get( 'fb_post_text', '{post_title}' ),
		    'post_attach_link'                      => (bool)Settings::get( 'fb_post_attach_link', true ),
		    'post_upload_media'                     => (bool)Settings::get( 'fb_post_upload_media', false ),
		    'post_media_type_to_upload'             => Settings::get( 'fb_post_media_type_to_upload', 'featured_image' ),
		    'share_to_first_comment'                => (bool)Settings::get( 'fb_share_to_first_comment', false ),
		    'first_comment_text'                    => Settings::get( 'fb_first_comment_text', '' ),

		    'story_text'                            => Settings::get( 'fb_story_text', '{post_title}' ),
		    'story_attach_link'                     => (bool)Settings::get( 'fb_story_attach_link', false ),

		    'story_customization_bg_color'          => Settings::get( 'fb_story_customization_bg_color', '636e72' ),
		    'story_customization_title_bg_color'    => Settings::get( 'fb_story_customization_title_bg_color', '000000' ),
		    'story_customization_title_bg_opacity'  => (int)Settings::get( 'fb_story_customization_title_bg_opacity', 30 ),
		    'story_customization_title_color'       => Settings::get( 'fb_story_customization_title_color', 'FFFFFF' ),
		    'story_customization_title_top_offset'  => (int)Settings::get( 'fb_story_customization_title_top_offset', 125 ),
		    'story_customization_title_left_offset' => (int)Settings::get( 'fb_story_customization_title_left_offset', 30 ),
		    'story_customization_title_width'       => (int)Settings::get( 'fb_story_customization_title_width', 660 ),
		    'story_customization_title_font_size'   => (int)Settings::get( 'fb_story_customization_title_font_size', 30 ),
		    'story_customization_is_rtl'            => (bool)Settings::get( 'fb_story_customization_is_rtl', false ),
		    'story_customization_title_font_family' => $font
	    ], Bootstrap::getInstance()->getSlug());
    }
}