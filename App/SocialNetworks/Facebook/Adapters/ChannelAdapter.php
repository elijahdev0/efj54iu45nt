<?php

namespace FSPoster\App\SocialNetworks\Facebook\Adapters;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Facebook\Api\AppMethod\Api as AppMethodApi;
use FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod\AuthData;
use FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod\Api as CookieMethodApi;
use FSPoster\App\SocialNetworks\Facebook\App\Bootstrap;

class ChannelAdapter
{

    /**
     * @param AppMethodApi|CookieMethodApi $api
     *
     * @return array[]
     * @throws SocialNetworkApiException
     */
    public static function fetchChannels ( $api, $method ): array
    {
	    $me = $api->getMe();

	    $me[ 'name' ] = $me[ 'name' ] ?? '?';

	    $channelSessionId = ChannelService::addChannelSession( [
		    'name'           => $me[ 'name' ],
		    'social_network' => Bootstrap::getInstance()->getSlug(),
		    'remote_id'      => $me[ 'id' ],
		    'proxy'          => $api->proxy,
		    'method'         => $method,
		    'data'           => [
			    'auth_data' => (array)$api->authData,
		    ],
	    ] );

	    $existingChannels = Channel::where( 'channel_session_id', $channelSessionId )
	                               ->select( [ 'id', 'remote_id', 'channel_type' ], true )
	                               ->fetchAll();

	    $existingChannelsIdToRemoteIdMap = [];

	    foreach ( $existingChannels as $existingChannel )
	    {
		    $existingChannelsIdToRemoteIdMap[ $existingChannel->remote_id . ':' . $existingChannel->channel_type ] = $existingChannel->id;
	    }

	    $channelsList = [];

		if( $method === 'cookie' )
		{
			$profileData = [
				'id'                    => $existingChannelsIdToRemoteIdMap[ $me[ 'id' ].':account' ] ?? null,
				'social_network'        => Bootstrap::getInstance()->getSlug(),
				'name'                  => $me[ 'name' ],
				'remote_id'             => $me['id'],
				'channel_type'          => 'account',
				'picture'               => null,
				'channel_session_id'    => $channelSessionId,
				'data'                  => [],
			];

			$channelsList[] = $profileData;

			/* Add profile for story channel */
			$profileData['id'] = $existingChannelsIdToRemoteIdMap[ $me[ 'id' ].':account_story' ] ?? null;
			$profileData['channel_type'] = 'account_story';

			$channelsList[] = $profileData;
		}

	    foreach ( $api->fetchPages() as $accountInfo )
	    {
		    $channelData = [
			    'id'                    => $existingChannelsIdToRemoteIdMap[ $accountInfo[ 'id' ].':ownpage' ] ?? null,
			    'social_network'        => Bootstrap::getInstance()->getSlug(),
			    'name'                  => $accountInfo[ 'name' ],
			    'channel_session_id'    => $channelSessionId,
			    'channel_type'          => 'ownpage',
			    'remote_id'             => $accountInfo[ 'id' ],
			    'picture'               => $accountInfo[ 'cover' ] ?? 'https://graph.facebook.com/' . $accountInfo[ 'id' ] . '/picture?redirect=1&height=40&width=40&type=normal',
			    'data'                  => [],
		    ];

			if( isset( $accountInfo['access_token'] ) )
				$channelData['data']['access_token'] = $accountInfo['access_token'];

			if( isset( $accountInfo['delegate_page_id'] ) )
				$channelData['data']['delegate_page_id'] = $accountInfo['delegate_page_id'];

		    $channelsList[] = $channelData;

			/* Add page for story channel */
			if( $method === 'cookie' )
			{
				$channelData['id'] = $existingChannelsIdToRemoteIdMap[ $accountInfo[ 'id' ].':ownpage_story' ] ?? null;
				$channelData['channel_type'] = 'ownpage_story';
				$channelsList[] = $channelData;
			}
	    }

	    foreach ( $api->fetchGroups() as $accountInfo )
	    {
		    $channelsList[] = [
			    'id'                 => $existingChannelsIdToRemoteIdMap[ $accountInfo[ 'id' ].':group' ] ?? null,
			    'social_network'     => Bootstrap::getInstance()->getSlug(),
			    'name'               => $accountInfo[ 'name' ],
			    'channel_session_id' => $channelSessionId,
			    'channel_type'       => 'group',
			    'remote_id'          => $accountInfo[ 'id' ],
			    'picture'            => $accountInfo[ 'cover' ] ?? 'https://graph.facebook.com/' . $accountInfo[ 'id' ] . '/picture?redirect=1&height=40&width=40&type=normal',
		    ];
	    }

	    return $channelsList;
    }

	public static function updateChannelCookies( $channelSessionId, AuthData $authData )
	{
		$channelSession = ChannelSession::get( $channelSessionId );

		$data = $channelSession->data_obj->toArray();
		$data['auth_data']['fbSess'] = $authData->fbSess;

		ChannelSession::where('id', $channelSession['id'])->update( [
			'data' => json_encode( $data )
		] );
	}

}