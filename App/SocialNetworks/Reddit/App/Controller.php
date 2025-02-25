<?php

namespace FSPoster\App\SocialNetworks\Reddit\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\SocialNetworks\Reddit\Api\Api;
use FSPoster\App\SocialNetworks\Reddit\Api\AuthData;

class Controller
{
    /**
     * @throws Exception
     */
    public static function fetchChannels ( RestRequest $request ): array
    {
        $channelSessionId = $request->require( 'channel_session_id', RestRequest::TYPE_INTEGER, fsp__( 'No channels found' ) );

        $channelSession = ChannelSession::where( 'id', $channelSessionId )->where( 'social_network', 'reddit' )->fetch();

        if ( ! $channelSession )
        {
            throw new Exception( fsp__( 'No channels found' ) );
        }

        $channels = Channel::where( 'channel_session_id', $channelSession->id )->where( 'channel_type', '<>', 'account' )->fetchAll();

        $channelsList = [];

        foreach ( $channels as &$channel )
        {
            $channelsList[] = apply_filters( 'fsp_get_channel', [
                'id'                 => (int)$channel->id,
                'name'               => !empty( $channel->name ) ? $channel->name : "[no name]",
                'remote_id'          => explode( ':', $channel->remote_id )[ 0 ],
                'channel_session_id' => $channel->channel_session_id,
                'social_network'     => $channelSession->social_network,
                'picture'            => $channel->picture,
                'channel_type'       => $channel->channel_type,
                'channel_link'       => apply_filters( 'fsp_get_channel_link', '', $channelSession->social_network, $channel ),
                'method'             => $channelSession->method,
            ], $channelSession->social_network, $channel, $channelSession );
        }

        return [
            'channels' => $channelsList
        ];
    }

    /**
     * @throws ChannelSessionException
     * @throws Exception
     */
    public static function getSubredditFlairs ( RestRequest $request ): array
    {
        $sessionId = $request->require( 'channel_session_id', RestRequest::TYPE_INTEGER, fsp__( 'Channel session ID is missing' ) );
        $subreddit = $request->require( 'subreddit', RestRequest::TYPE_STRING, fsp__( 'Subreddit name is missing' ) );

        $subreddit = basename( $subreddit );

        $channelSession = ChannelSession::get( $sessionId );

        if ( empty( $channelSession ) )
        {
            throw new Exception( fsp__( 'You have not a permission for adding subreddit in this account' ) );
        }

		$authDataArr = $channelSession->data_obj->auth_data ?? [];
		$authData = new AuthData();
		$authData->setFromArray( $authDataArr );

	    $api = new Api();
	    $api->setProxy( $channelSession->proxy )
		    ->setAuthException( ChannelSessionException::class )
		    ->setAuthData( $authData );

        $flairs = [];

        foreach ( $api->getFlairs( $subreddit ) as $flair )
        {
            $flairs[] = [
                'text'       => htmlspecialchars( $flair['text'] ),
                'flair_id'   => htmlspecialchars( $flair['id'] ),
                'bg_color'   => htmlspecialchars( $flair['background_color'] ),
                'text_color' => htmlspecialchars( $flair['text_color'] ),
            ];
        }

        return [ 'flairs' => $flairs ];
    }

    /**
     * @throws ChannelSessionException
     * @throws Exception
     */
    public static function searchSubreddits ( RestRequest $request ): array
    {
        $accountId = $request->param( 'channel_session_id', '0', RestRequest::TYPE_INTEGER );
        $search    = $request->require( 'search', RestRequest::TYPE_STRING, fsp__( 'Please enter some text to search' ) );

        $channelSession = ChannelSession::get( $accountId );

        if ( empty( $channelSession ) )
        {
            throw new Exception( fsp__( 'You have not a permission for adding subreddit in this account' ) );
        }

	    $authDataArr = $channelSession->data_obj->auth_data ?? [];
	    $authData = new AuthData();
	    $authData->setFromArray( $authDataArr );

	    $api = new Api();
	    $api->setProxy( $channelSession->proxy )
	        ->setAuthException( ChannelSessionException::class )
	        ->setAuthData( $authData );

        $new_arr = [];

        foreach ( $api->searchSubreddits( $search ) as $subreddit )
        {
            $new_arr[] = [
                'id'                 => null,
                'social_network'     => Bootstrap::getInstance()->getSlug(),
                'name'               => htmlspecialchars( $subreddit[ 'name' ] ),
                'channel_type'       => 'subreddit',
                'remote_id'          => htmlspecialchars( $subreddit[ 'name' ] ),
                'channel_session_id' => $accountId,
                'picture'            => $subreddit[ 'icon_img' ] ?? '',
            ];
        }

        return [ 'channels' => $new_arr ];
    }

    /**
     * @throws Exception
     */
    public static function saveSettings ( RestRequest $request ): array
    {
        $postTitle              = $request->param( 'post_title', '', RestRequest::TYPE_STRING );
        $cutPostTitle           = (int)$request->param( 'cut_post_title', false, RestRequest::TYPE_BOOL );
        $postText               = $request->param( 'post_text', '', RestRequest::TYPE_STRING );
        $shareToFirstComment    = (int)$request->param( 'share_to_first_comment', false, RestRequest::TYPE_BOOL );
        $firstCommentText       = $request->param( 'first_comment_text', '', RestRequest::TYPE_STRING );
	    $attachLink             = $request->param( 'attach_link', true, RestRequest::TYPE_BOOL );
	    $uploadMedia            = $request->param( 'upload_media', false, RestRequest::TYPE_BOOL );
	    $mediaTypeToUpload      = $request->param( 'media_type_to_upload', 'featured_image', RestRequest::TYPE_STRING );

        if ( $shareToFirstComment && empty( $firstCommentText ) )
            throw new Exception( fsp__( 'First comment can\'t be empty if enabled' ) );

        Settings::set( 'reddit_post_title', $postTitle );
        Settings::set( 'reddit_cut_post_title', $cutPostTitle );
        Settings::set( 'reddit_post_content', $postText );
        Settings::set( 'reddit_share_to_first_comment', $shareToFirstComment );
        Settings::set( 'reddit_first_comment_text', $firstCommentText );
	    Settings::set( 'reddit_attach_link', (int)$attachLink );
	    Settings::set( 'reddit_upload_media', (int)$uploadMedia );
	    Settings::set( 'reddit_media_type_to_upload', $mediaTypeToUpload );

	    do_action( 'fsp_save_settings', $request, Bootstrap::getInstance()->getSlug() );

        return [];
    }

    public static function getSettings ( RestRequest $request ): array
    {
	    return apply_filters('fsp_get_settings', [
		    'post_title'                => Settings::get( 'reddit_post_title', '{post_title}' ),
		    'cut_post_title'            => (bool)Settings::get( 'reddit_cut_post_title', true ),
		    'post_text'                 => Settings::get( 'reddit_post_content', '{post_title}' ),
		    'share_to_first_comment'    => (bool)Settings::get( 'reddit_share_to_first_comment', true ),
		    'first_comment_text'        => Settings::get( 'reddit_first_comment_text', '' ),
		    'attach_link'               => (bool)Settings::get( 'reddit_attach_link', true ),
		    'upload_media'              => (bool)Settings::get( 'reddit_upload_media', false ),
		    'media_type_to_upload'      => Settings::get( 'reddit_media_type_to_upload', 'featured_image' )
	    ], Bootstrap::getInstance()->getSlug());
    }
}