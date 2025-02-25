<?php

namespace FSPoster\App\SocialNetworks\GoogleBusinessProfile\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\Api\AuthData;
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\Api\Api;
use FSPoster\App\SocialNetworks\GoogleBusinessProfile\App\Bootstrap;

class ChannelAdapter
{

    public static function fetchChannels ( Api $api ): array
    {
	    $accounts = $api->getMyAccounts();
	    $channelsList = [];

		foreach ($accounts as $account)
		{
			$accountId  = $account['name'] ?? '-';
			$name       = $account[ 'accountName' ] ?? '-';

			$channelSessionId = ChannelService::addChannelSession([
				'name'              => $name,
				'social_network'    => Bootstrap::getInstance()->getSlug(),
				'remote_id'         => $accountId,
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

			foreach ( $api->getLocations( $accountId ) AS $location )
			{
				$channelsList[] = [
					'id'                 => $existingChannelsIdToRemoteIdMap[$location['name']] ?? null,
					'social_network'     => Bootstrap::getInstance()->getSlug(),
					'name'               => $location['title'],
					'channel_session_id' => $channelSessionId,
					'channel_type'       => 'location',
					'remote_id'          => $location['name'],
					'picture'            => null
				];
			}
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