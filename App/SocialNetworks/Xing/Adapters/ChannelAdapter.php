<?php

namespace FSPoster\App\SocialNetworks\Xing\Adapters;

use Exception;
use FSPoster\App\Models\Channel;
use FSPoster\App\Providers\Channels\ChannelService;
use FSPoster\App\Providers\Schedules\SocialNetworkApiException;
use FSPoster\App\SocialNetworks\Xing\Api\Api;

class ChannelAdapter
{
    /**
     * @throws SocialNetworkApiException
     * @throws Exception
     */
    public static function fetchChannels (Api $api) : array
    {
        $accountData = $api->getAccountData();

        if ( empty( $accountData[ 'id' ] ) )
        {
            throw new SocialNetworkApiException(fsp__( 'The entered cookies are wrong' ));
        }

        $channelSessionId = ChannelService::addChannelSession([
            'name'           => $accountData[ 'xingId' ][ 'displayName' ],
            'social_network' => 'xing',
            'remote_id'      => $accountData[ 'id' ],
            'proxy'          =>  empty($api->getProxy()) ? '' : $api->getProxy(),
            'method'         => 'cookie',
            'data'           => [
                'auth_data' => (array) $api->authData
            ]
        ]);

        $existingChannels = Channel::where('channel_session_id', $channelSessionId)->select(['id', 'remote_id'], true)->fetchAll();

        $existingChannelsIdToRemoteIdMap = [];

        foreach ( $existingChannels as $existingChannel )
        {
            $existingChannelsIdToRemoteIdMap[$existingChannel->remote_id] = $existingChannel->id;
        }

	    $channelsList = [];
        $channelsList[] = [
            'id'                 => $existingChannelsIdToRemoteIdMap[$accountData[ 'id' ]] ?? null,
            'social_network'     => 'xing',
            'name'               => $accountData[ 'xingId' ][ 'displayName' ],
            'channel_type'       => 'account',
            'remote_id'          => $accountData[ 'id' ],
            'channel_session_id' => $channelSessionId,
            'picture'            => $accountData[ 'xingId' ][ 'profileImage' ][ 0 ][ 'url' ],
            'data'               => [
                'username'  => $accountData[ 'xingId' ][ 'pageName' ],
                'url'       => 'https://www.xing.com/profile/' . $accountData[ 'xingId' ][ 'pageName' ]
            ]
        ];

        $nodesData = $api->getCompanies();

        if ( ! empty( $nodesData ) )
        {
            foreach ( $nodesData as $node )
            {
                $node = $node[ 'node' ]['company'];

                $channelsList[] = [
                    'id'                 => $existingChannelsIdToRemoteIdMap[$node[ 'entityPageId' ]] ?? null,
                    'social_network'     => 'xing',
                    'name'               => $node[ 'companyName' ],
                    'channel_type'       => 'company',
                    'remote_id'          => $node[ 'entityPageId' ],
                    'channel_session_id' => $channelSessionId,
                    'picture'            => $node[ 'logos' ][ 'logo128px' ] ?? '',
                    'data'               => [
                        'url'   => $node['links']['public'] ?? ($node['links']['default'] ?? ''),
                    ]
                ];
            }
        }

        return $channelsList;
    }

}