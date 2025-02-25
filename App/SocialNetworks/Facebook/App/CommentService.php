<?php

namespace FSPoster\App\SocialNetworks\Facebook\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\DB;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\SocialNetworks\Facebook\Api\AppMethod\Api;
use FSPoster\App\SocialNetworks\Facebook\Api\AppMethod\AuthData;

class CommentService
{
    private static $fbid_to_wpid = [];

    public static function init ()
    {
        $fbLastRunOn = Settings::get( 'fb_cron_job_run_on', 0 );
        $fbLastRunOn = is_numeric( $fbLastRunOn ) ? $fbLastRunOn : 0;
        $fbDiff      = Date::epoch() - $fbLastRunOn;

        if ( $fbDiff > 12 * 60 * 60 && Settings::get( 'fb_import_comments', false ) )
        {
            Settings::set( 'fb_cron_job_run_on', Date::epoch(), false, false );
            Settings::set( 'fb_real_cron_job_run_on', Date::epoch(), false, false );

            self::fetchFacebookComments();
        }
    }

    private static function fetchFacebookComments ()
    {
        $allBlogs = Helper::getBlogs();

        foreach ( $allBlogs as $blogId )
        {
            Helper::setBlogId( $blogId );

            try
            {
                self::_fetchFacebookComments();
            } catch ( Exception $e )
            {
            }

            Helper::resetBlogId();
        }
    }

    /**
     * @throws ChannelSessionException
     */
    private static function _fetchFacebookComments ()
    {
        $posts = self::getPosts();

        foreach ( $posts as $post )
        {
            $channel = Channel::withoutGlobalScopes( 'my_channels' )->where( 'id', $post[ 'channel_id' ] )->fetch();

			if( ! $channel )
				continue;

	        $channelSession = $channel->channel_session->fetch();

			if( ! $channelSession || $channelSession->method != 'app' )
				continue;

	        $authDataArray = $channelSession->data_obj->auth_data ?? [];
            if($channel['channel_type'] === 'ownpage')
                $authDataArray['accessToken'] = $channel->data_obj->access_token;

	        $authData = new AuthData();
	        $authData->setFromArray( $authDataArray );

	        $api = new Api();
	        $api->setProxy( $channelSession->proxy )
	            ->setAuthException( ChannelSessionException::class )
	            ->setAuthData( $authData );

            $since = get_post_meta( $post[ 'wp_post_id' ], 'fsp_fb_last_comment_fetch_date_' . $post[ 'id' ], true );

            $comments = $api->fetchComments( $channel->remote_id, $post[ 'remote_post_id' ], $since );

            if ( !empty ( $comments ) )
            {
                $dates = [];

                $needsExistenceCheck = true;

                foreach ( $comments as $comment )
                {
                    if ( !empty( $comment[ 'created_time' ] ) )
                    {
                        $dates[] = $comment[ 'created_time' ];
                    }

                    if ( empty( $comment[ 'id' ] ) || isset( self::$fbid_to_wpid[ $comment[ 'id' ] ] ) )
                    {
                        continue;
                    }

                    if ( $needsExistenceCheck )
                    {
                        $existingComment = DB::DB()->get_row( DB::DB()->prepare( 'SELECT comment_id FROM ' . DB::WPtable( 'commentmeta', true ) . ' WHERE meta_value=%s AND meta_key=\'fsp_fb_comment_id\'', $comment[ 'id' ] ), ARRAY_A );

                        if ( empty( $existingComment ) )
                        {
                            $needsExistenceCheck = false;
                        } else
                        {
                            self::$fbid_to_wpid[ $comment[ 'id' ] ] = $existingComment[ 'comment_id' ];
                            continue;
                        }
                    }

                    $message = empty( $comment[ 'message' ] ) ? '' : $comment[ 'message' ];

                    $isAttachment = false;

                    if ( !empty( $comment[ 'attachment' ] ) )
                    {
                        $type = $comment[ 'attachment' ][ 'type' ] ?? '';

                        if ( $type !== 'share' ) //is not link
                        {
                            $isAttachment = true;

                            $attachment = "<br>" . fsp__( 'Media: ' );

                            if ( $comment[ 'attachment' ][ 'target' ][ 'url' ] )
                            {
                                $attachment .= $comment[ 'attachment' ][ 'target' ][ 'url' ];
                            } else if ( $comment[ 'attachment' ][ 'media' ][ 'source' ] )
                            {
                                $attachment .= $comment[ 'attachment' ][ 'media' ][ 'source' ];
                            } else if ( $comment[ 'attachment' ][ 'media' ][ 'img' ][ 'src' ] )
                            {
                                $attachment .= $comment[ 'attachment' ][ 'media' ][ 'img' ][ 'src' ];
                            } else
                            {
                                $attachment = '';
                            }

                            $message .= $attachment;
                        }
                    }

                    self::insertComment(
                        $post[ 'wp_post_id' ],
                        $comment[ 'id' ],
                        empty( $comment[ 'parent' ][ 'id' ] ) ? 0 : $comment[ 'parent' ][ 'id' ],
                        $message,
                        $isAttachment || empty( $message ) ? 0 : 1,
                        empty( $comment[ 'from' ][ 'name' ] ) ? '' : $comment[ 'from' ][ 'name' ],
                        empty( $comment[ 'created_time' ] ) ? Date::dateTimeSQL() : Date::dateTimeSQL( $comment[ 'created_time' ] )
                    );
                }

                uasort( $dates, function ( $a, $b )
                {
                    return $a == $b ? 0 : ( strtotime( $a ) > strtotime( $b ) ? -1 : 1 );
                } );

                $lastFetchDate = reset( $dates );

                if ( !empty( $lastFetchDate ) )
                {
                    update_post_meta( $post[ 'wp_post_id' ], 'fsp_fb_last_comment_fetch_date_' . $post[ 'id' ], $lastFetchDate );
                }
            }
        }
    }

    private static function getPosts ()
    {
        $dateIntervalString = Settings::get( 'fb_import_comments_published_in', 'last_week' );

        switch ( $dateIntervalString )
        {
            case 'last_month' :
                $dateInterval = 30;
                break;
            case 'last_3_weeks' :
                $dateInterval = 21;
                break;
            case 'last_2_weeks' :
                $dateInterval = 14;
                break;
            default :
                $dateInterval = 7;
        }

        global $_wp_post_type_features;

        $postTypes = [];

        foreach ( $_wp_post_type_features as $postType => $info )
        {
            if ( isset( $info[ 'comments' ] ) && $info[ 'comments' ] )
            {
                $postTypes[] = $postType;
            }
        }

        if ( empty( $postTypes ) )
        {
            return [];
        }

        $fbChannels = Channel::withoutGlobalScopes( 'my_channels' )
            ->where( 'channel_type', 'in', [ 'group', 'ownpage' ] )
            ->where( 'is_deleted', 0 )
            ->where( 'status', 1 )
            ->where(
                'channel_session_id', 'in',
                ChannelSession::where( 'social_network', Bootstrap::getInstance()->getSlug() )
                                ->where( 'method', 'app' )
                                ->select( 'id', true ) )
                                ->select( 'id' )
                                ->fetchAll();

        if ( empty( $fbChannels ) )
        {
            return [];
        }

        $fbChannels = array_map( fn ( $c ) => $c->id, $fbChannels );

        $schedulesSql = DB::raw( 'SELECT tb1.id, tb1.channel_id, tb1.wp_post_id, tb1.remote_post_id from ' . DB::table( 'schedules' ) . ' tb1 LEFT JOIN ' . DB::WPtable( 'posts', true ) . ' tb2 on tb1.wp_post_id = tb2.ID where status=\'success\' AND tb1.channel_id in (' . implode( ',', array_fill( 0, count( $fbChannels ), '%s' ) ) . ') and tb1.send_time >= %s', [ ...$fbChannels, Date::dateTimeSQL( 'now', '-' . $dateInterval . ' days' ) ] );

        $schedules = DB::DB()->get_results( $schedulesSql, 'ARRAY_A' );

        return empty( $schedules ) ? [] : $schedules;
    }

    private static function insertComment ( $postID, $fbCommentID, $fbCommentParentID, $text, $commentApproved, $author = '', $commentDate = '' )
    {
        $commentData = [
            'comment_post_ID'  => $postID,
            'comment_content'  => $text,
            'comment_author'   => $author,
            'comment_date'     => $commentDate,
            'comment_approved' => $commentApproved,
        ];

        if ( $fbCommentParentID !== 0 )
        {
            if ( isset( self::$fbid_to_wpid[ $fbCommentParentID ] ) )
            {
                $parentWpID = self::$fbid_to_wpid[ $fbCommentParentID ];
            } else
            {
                $savedComment = get_comments( [
                    'post_id'    => $postID,
                    'fields'     => 'ids',
                    'number'     => 1,
                    'meta_key'   => 'fsp_fb_comment_id',
                    'meta_value' => $fbCommentParentID,
                ] );

                $parentWpID = reset( $savedComment );
            }

            if ( !empty( $parentWpID ) )
            {
                self::$fbid_to_wpid[ $fbCommentParentID ] = $parentWpID;
                $commentData[ 'comment_parent' ]          = $parentWpID;
            }
        }

        $insertID = wp_new_comment( $commentData );

        if ( $insertID === false || $fbCommentID === 0 )
        {
            return;
        }

        self::$fbid_to_wpid[ $fbCommentID ] = $insertID;

        add_comment_meta( $insertID, 'fsp_fb_comment_id', $fbCommentID, true );
    }
}