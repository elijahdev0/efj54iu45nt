<?php

namespace FSPoster\App\SocialNetworks\Tumblr\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\SocialNetworks\Tumblr\Api\Api;
use FSPoster\App\SocialNetworks\Tumblr\Api\AuthData;
use FSPoster\App\SocialNetworks\Tumblr\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
	    $myInfo = $api->getMyInfo();

	    $channelSessionId = ChannelService::addChannelSession([
			'name'              => $myInfo->name,
			'social_network'    => Bootstrap::getInstance()->getSlug(),
			'remote_id'         => $myInfo->name,
			'proxy'             => $api->proxy,
			'method'            => 'app',
			'data'              => [
				'auth_data' =>  (array)$api->authData
			]
		]);

		$existingChannels = Channel::where( 'channel_session_id', $channelSessionId )
		                           ->select( [ 'id', 'remote_id' ], true )
		                           ->fetchAll();

		$existingChannelsIdToRemoteIdMap = [];

		foreach ( $existingChannels as $existingChannel )
		{
			$existingChannelsIdToRemoteIdMap[ $existingChannel->remote_id ] = $existingChannel->id;
		}

	    $channelsList = [];

	    foreach ( $myInfo->blogs as $blogInf )
	    {
		    $channelsList[] = [
			    'id'                 => $existingChannelsIdToRemoteIdMap[$blogInf->name] ?? null,
			    'social_network'     => Bootstrap::getInstance()->getSlug(),
			    'name'               => $blogInf->title,
			    'channel_type'       => 'blog',
			    'remote_id'          => $blogInf->name,
			    'picture'            => 'https://api.tumblr.com/v2/blog/'.$blogInf->name.'/avatar/40',
			    'channel_session_id' => $channelSessionId
		    ];
	    }

	    return $channelsList;
    }

	public static function updateAuthDataIfRefreshed( Collection $channelSession, AuthData $authData ): bool
	{
		$authDataArray = $channelSession->data_obj->auth_data;

		if( $authDataArray['accessToken'] !== $authData->accessToken )
		{
			$updateSessionData = $channelSession->data_obj->toArray();

			$updateSessionData[ 'auth_data' ] = (array)$authData;

			ChannelSession::where( 'id', $channelSession->id )->update( [
				'data' => json_encode( $updateSessionData )
			] );

			return true;
		}

		return false;
	}

}