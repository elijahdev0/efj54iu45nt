<?php

namespace FSPoster\App\SocialNetworks\Mastodon\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Mastodon\Api\Api;
use FSPoster\App\SocialNetworks\Mastodon\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
	    $myInfo = $api->getMyInfo();
	    $name = ($myInfo[ 'display_name' ] ?? '') ?: ($myInfo[ 'username' ] ?? '');

	    $channelSessionId = ChannelService::addChannelSession([
			'name'              => $name,
			'social_network'    => Bootstrap::getInstance()->getSlug(),
			'remote_id'         => $myInfo[ 'id' ],
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
		$channelsList[] = [
			'id'                    => $existingChannelsIdToRemoteIdMap[$myInfo['id']] ?? null,
			'social_network'        => Bootstrap::getInstance()->getSlug(),
			'name'                  => $name,
			'channel_session_id'    => $channelSessionId,
			'channel_type'          => 'account',
			'remote_id'             => $myInfo['id'],
			'picture'               => $myInfo['avatar_static'],
			'data'                  => [
				'username'  => $myInfo['username']
			]
		];

	    return $channelsList;
    }

}