<?php

namespace FSPoster\App\SocialNetworks\Youtube\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\SocialNetworks\Youtube\Api\Api;
use FSPoster\App\SocialNetworks\Youtube\Api\AuthData;
use FSPoster\App\SocialNetworks\Youtube\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
        $data = $api->getMyInfo();

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $data['name'],
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $data['id'],
		    'proxy'          => $api->proxy,
		    'method'         => 'cookie',
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
			'id'                    => $existingChannelsIdToRemoteIdMap[$data['id']] ?? null,
			'name'                  => $data['name'],
			'social_network'        => Bootstrap::getInstance()->getSlug(),
			'channel_type'          => 'account',
			'remote_id'             => $data['id'],
			'channel_session_id'    => $channelSessionId,
			'picture'               => $data['image']
		];

        return $channelsList;
    }

	/**
	 * @param ChannelSession $channelSession
	 * @param AuthData       $authData
	 *
	 * @return bool
	 */
	public static function updateAuthDataIfRefreshed( Collection $channelSession, AuthData $authData ): bool
	{
		$authDataArray = $channelSession->data_obj->auth_data;

		if( $authDataArray['cookieLastUpdatedAt'] !== $authData->cookieLastUpdatedAt )
		{
			$updateSessionData = $channelSession->data_obj->toArray();

			$updateSessionData['auth_data'] = (array)$authData;

			ChannelSession::where( 'id', $channelSession->id )->update( [
				'data' => json_encode( $updateSessionData )
			] );

			return true;
		}

		return false;
	}

}