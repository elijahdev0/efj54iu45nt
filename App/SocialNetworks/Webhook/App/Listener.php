<?php

namespace FSPoster\App\SocialNetworks\Webhook\App;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Pages\Schedules\CalendarData;
use FSPoster\App\Providers\Channels\ChannelSessionException;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\ScheduleObject;
use FSPoster\App\Providers\Schedules\ScheduleResponseObject;
use FSPoster\App\Providers\Schedules\ScheduleShareException;
use FSPoster\App\SocialNetworks\Webhook\Adapters\PostingDataAdapter;
use FSPoster\App\SocialNetworks\Webhook\Api\Api;

class Listener
{
    /**
     * @throws ChannelSessionException
     * @throws Exception
     */
	public static function sharePost ( ScheduleResponseObject $result, ScheduleObject $scheduleObj ): ScheduleResponseObject
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $result;

		$postingDataAdapter = new PostingDataAdapter( $scheduleObj );
		$postingData = $postingDataAdapter->getPostingData();

		$api = new Api();

		$api->setProxy( $scheduleObj->getChannelSession()->proxy )
		    ->setPostException( ScheduleShareException::class );

		$postId = $api->sendPost( $postingData );

		$result                 = new ScheduleResponseObject();
		$result->status         = 'success';
		$result->remote_post_id = '$postId';// doit

		return $result;
	}

	/**
	 * @param array      $customPostData
	 * @param Channel    $channel
	 * @param string     $socialNetwork
	 *
	 * @return array
	 */
	public static function getCustomPostData( array $customPostData, Collection $channel, string $socialNetwork )
	{
		if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $customPostData;

		$channelData = $channel->data_obj;

		$customPostData["method"] = strtoupper( $channelData->method ?? 'GET' );
		$customPostData["url"] = $channelData->url ?? '';
		$customPostData["content_type"] = $channelData->content_type ?? 'form';
		$customPostData["headers"] = $channelData->headers ?? [];
		$customPostData["form_data"] = $channelData->form_data ?? [];
		$customPostData["json_data"] = $channelData->json_data ?? '[]';

		return $customPostData;
	}

	public static function getCalendarData( CalendarData $calendarData, ScheduleObject $scheduleObj )
	{
		if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $calendarData;

		$postingData = new PostingDataAdapter( $scheduleObj );

		$calendarData->content = $postingData->getPostingDataUrl();

		return $calendarData;
	}

    public static function getPostLink ( string $postLink, ScheduleObject $scheduleObj ): string
    {
        if ( $scheduleObj->getSocialNetwork() !== Bootstrap::getInstance()->getSlug() )
			return $postLink;

        return admin_url( 'admin.php?page=fs-poster-logs&webhook_schedule_id=' . $scheduleObj->getSchedule()->id );// doit
    }

    public static function getChannelLink ( string $channelLink, string $socialNetwork, Collection $channel ): string
    {
        if ( $socialNetwork !== Bootstrap::getInstance()->getSlug() )
			return $channelLink;

        return $channel->data_obj->url;
    }

}