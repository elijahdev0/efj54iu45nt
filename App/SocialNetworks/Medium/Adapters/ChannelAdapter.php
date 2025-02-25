<?php

namespace FSPoster\App\SocialNetworks\Medium\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\SocialNetworks\Medium\Api\Api;
use FSPoster\App\SocialNetworks\Medium\Api\AuthData;
use FSPoster\App\SocialNetworks\Medium\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
	    $myInfo = $api->getMyInfo();

	    $channelSessionId = ChannelService::addChannelSession([
			'name'              => $myInfo['name'],
			'social_network'    => Bootstrap::getInstance()->getSlug(),
			'remote_id'         => $myInfo['id'],
			'proxy'             => $api->proxy,
			'method'            => 'integration_token',
			'data'              => [
				'username'      => $myInfo['username'],
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
			'name'                  => $myInfo[ 'name' ],
			'channel_session_id'    => $channelSessionId,
			'channel_type'          => 'account',
			'remote_id'             => $myInfo['id'],
			'picture'               => $myInfo['imageUrl'],
			'data'                  => [
				'username'  => $myInfo['username']
			]
		];

	    foreach ( $api->getPublications( $myInfo['id'] ) as $publicationInf )
	    {
		    $channelsList[] = [
			    'id'                 => $existingChannelsIdToRemoteIdMap[$publicationInf[ 'id' ]] ?? null,
			    'social_network'     => Bootstrap::getInstance()->getSlug(),
			    'name'               => $publicationInf['name'],
			    'channel_type'       => 'publication',
			    'remote_id'          => $publicationInf['id'],
			    'picture'            => $publicationInf['imageUrl'],
			    'channel_session_id' => $channelSessionId,
			    'data'                  => [
				    'username'  => str_replace( 'https://medium.com/', '', $publicationInf[ 'url' ] )
			    ]
		    ];
	    }

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