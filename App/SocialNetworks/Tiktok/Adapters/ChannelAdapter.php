<?php

namespace FSPoster\App\SocialNetworks\Tiktok\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\Tiktok\Api\Api;
use FSPoster\App\SocialNetworks\Tiktok\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
        $data = $api->getMyInfo();

		$openId = $data['data']['user']['open_id'] ?? '-';
		$userName = $data['data']['user']['username'] ?? '-';
		$displayName = $data['data']['user']['display_name'] ?? '-';
		$avatar = $data['data']['user']['avatar_url_100'] ?? '';

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $displayName,
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $openId,
		    'proxy'          => $api->proxy,
		    'method'         => 'app',
		    'data'           => [
			    'auth_data' =>  (array)$api->authData
		    ]
	    ] );

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
			'id'                    => $existingChannelsIdToRemoteIdMap[$openId] ?? null,
			'name'                  => $displayName,
			'social_network'        => Bootstrap::getInstance()->getSlug(),
			'channel_type'          => 'account',
			'remote_id'             => $openId,
			'channel_session_id'    => $channelSessionId,
			'picture'               => $avatar,
			'data'                  => [
				'username'  => $userName
			]
		];

        return $channelsList;
    }

}