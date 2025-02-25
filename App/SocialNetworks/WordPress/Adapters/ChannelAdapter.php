<?php

namespace FSPoster\App\SocialNetworks\WordPress\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\SocialNetworks\WordPress\Api\Api;
use FSPoster\App\SocialNetworks\WordPress\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
	    $api->getMyInfo();

	    $channelSessionId = ChannelService::addChannelSession([
			'name'              => $api->authData->siteUrl,
			'social_network'    => Bootstrap::getInstance()->getSlug(),
			'remote_id'         => $api->authData->siteUrl,
			'proxy'             => $api->proxy,
			'method'            => 'password',
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
		    'id'                 => $existingChannelsIdToRemoteIdMap[$api->authData->siteUrl] ?? null,
		    'social_network'     => Bootstrap::getInstance()->getSlug(),
		    'name'               => $api->authData->siteUrl,
		    'channel_type'       => 'website',
		    'remote_id'          => $api->authData->siteUrl,
		    'picture'            => null,
		    'channel_session_id' => $channelSessionId
	    ];

	    return $channelsList;
    }

}