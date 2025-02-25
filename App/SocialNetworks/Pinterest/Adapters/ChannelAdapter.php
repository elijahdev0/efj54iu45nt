<?php

namespace FSPoster\App\SocialNetworks\Pinterest\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Pinterest\Api\AppMethod\Api AS AppMethodApi;
use FSPoster\App\SocialNetworks\Pinterest\Api\AppMethod\AuthData AS AppMethodAuthData;
use FSPoster\App\SocialNetworks\Pinterest\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( $api, $method ): array
    {
        $data = $api->getMyInfo();

        $channelSessionId = ChannelService::addChannelSession( [
            'name'           => $method === 'app' ? $data['username'] : $data['full_name'],
            'social_network' => Bootstrap::getInstance()->getSlug(),
            'remote_id'      => $data['username'],
            'proxy'          => $api->proxy,
            'method'         => $method,
            'data'           => [
                'auth_data' => (array)$api->authData
            ],
        ]);

        $existingChannels = Channel::where( 'channel_session_id', $channelSessionId )
                                   ->select( [ 'id', 'remote_id' ], true )
                                   ->fetchAll();

        $existingChannelsIdToRemoteIdMap = [];

        foreach ( $existingChannels as $existingChannel )
        {
            $existingChannelsIdToRemoteIdMap[ $existingChannel->remote_id ] = $existingChannel->id;
        }

		$boards = $method === 'app' ? $api->getMyBoards() : $api->getMyBoards( $data['username'] );

	    $channelsList = [];
	    foreach ( $boards as $board )
	    {
		    $channelData = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[$board['id']] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $board['name'],
			    'channel_type'          => 'board',
			    'remote_id'             => $board['id'],
			    'picture'               => $board['photo'],
			    'channel_session_id'    => $channelSessionId
		    ];

			if( $method === 'cookie' )
				$channelData['data']['url'] = 'https://pinterest.com/' . trim($board[ 'url' ], '/');

			$channelsList[] = $channelData;
	    }

        return $channelsList;
    }

	/**
	 * @param ChannelSession $channelSession
	 * @param AppMethodAuthData       $authData
	 *
	 * @return bool
	 */
	public static function updateAuthDataIfRefreshed( Collection $channelSession, AppMethodAuthData $authData ): bool
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