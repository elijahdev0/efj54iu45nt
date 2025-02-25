<?php

namespace FSPoster\App\SocialNetworks\Telegram\App;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Telegram\Adapters\ChannelAdapter;
use FSPoster\App\SocialNetworks\Telegram\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Telegram\Api\Api;
use FSPoster\App\SocialNetworks\Telegram\Api\AuthData;

class Listener
{

    public static function sharePost ( ScheduleResponseObject $result, ScheduleObject $scheduleObj ): ScheduleResponseObject
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
        {
            return $result;
        }

        $authData         = new AuthData();
        $authData->token  = $scheduleObj->getChannelSession()->data_obj->bot_token;
        $authData->chatId = $scheduleObj->getChannel()->remote_id;

        $postingData = new PostingDataAdapter( $scheduleObj );
	    $postingData = $postingData->getPostingData();

        $lib = new Api();

        $tgPostId = $lib->setProxy( $scheduleObj->getChannelSession()->proxy )
            ->setAuthException( ChannelSessionException::class )
            ->setPostException( ScheduleShareException::class )
            ->setAuthData( $authData )
            ->sendPost( $postingData );

        $result->status         = 'success';
        $result->remote_post_id = $tgPostId;

        return $result;
    }

	/**
	 * @param array   $customPostData
	 * @param Channel $channel
	 * @param string  $socialNetwork
	 *
	 * @return array
	 */
	public static function getCustomPostData ( array $customPostData, Collection $channel, string $socialNetwork ): array
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $customPostData;

		$channelSettings = $channel->custom_settings_obj->custom_post_data;

		$customPostData["add_read_more_button"] = (bool)Settings::get( 'telegram_add_read_more_button', false );
		$customPostData["read_more_button_link"] = '';
		$customPostData["read_more_button_text"] = Settings::get( 'telegram_read_more_button_text', '' );
		$customPostData["upload_media"] = (bool)Settings::get( 'telegram_upload_media', false );
		$customPostData["upload_media_type"] = Settings::get( 'telegram_media_type_to_upload', 'featured_image' );
		$customPostData["silent_notifications"] = (bool)Settings::get( 'telegram_silent_notifications', false );

		if( ! empty( $channelSettings[ 'use_custom_post_content' ] ) )
			$customPostData['post_content'] = $channelSettings[ 'post_content' ];
		else
			$customPostData['post_content'] = Settings::get( 'telegram_post_content', '{post_title}' );

		return $customPostData;
	}

	/**
	 * @param string     $socialNetwork
	 * @param Channel $channel
	 * @param ChannelSession $channelSession
	 *
	 * @return void
	 */
    public static function disableSocialChannel ( string $socialNetwork, Collection $channel, Collection $channelSession ): void
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
            return;

        Channel::where( 'channel_session_id', $channelSession->id )->update( [ 'status' => 0 ] );
    }

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content   = $postingData->getPostingDataMessage();
		$calendarData->mediaList = $postingData->getPostingDataUploadMedia();

		return $calendarData;
	}

	/**
	 * @param string     $postLink
	 * @param string     $socialNetwork
	 * @param Schedule $schedule
	 * @param Channel $channel
	 *
	 * @return string
	 */
    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return 'http://t.me/' . ( $scheduleObj->getChannel()->data_obj->username ?? '' );
    }

	/**
	 * @param string     $channelLink
	 * @param string     $socialNetwork
	 * @param Channel $channel
	 *
	 * @return string
	 */
    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() ) return $channelLink;

        return 'https://t.me/' . esc_html( $channel->data_obj->username );
    }

    /**
     * @throws SocialNetworkApiException
     * @throws ChannelSessionException
     * @throws SocialNetworkApiException
     */
    public static function refreshChannel ( array $updatedChannel, string $socialNetwork, Collection $channel, Collection $channelSession )
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
        {
            return $updatedChannel;
        }

        $authData         = new AuthData();
        $authData->token  = $channelSession->data_obj->bot_token;
        $authData->chatId = $channel->remote_id;

        $tgSDK = new Api();
        $tgSDK->setProxy( $channelSession->proxy )
              ->setAuthException( ChannelSessionException::class )
            ->setAuthData( $authData );

        $refreshedChannels = ChannelAdapter::fetchChannels( $tgSDK );

        foreach ( $refreshedChannels as $refreshedChannel )
        {
            if ( $refreshedChannel[ 'remote_id' ] == $channel->remote_id )
            {
                return $refreshedChannel;
            }
        }

        return $updatedChannel;
    }

}