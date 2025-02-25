<?php

namespace FSPoster\App\SocialNetworks\Facebook\Api\CookieMethod;

use Exception;
use FSPoster\App\Providers\Helpers\Curl;
use FSPoster\App\SocialNetworks\Facebook\Api\PostingData;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Cookie\CookieJar;
use FSPoster\GuzzleHttp\Psr7\MultipartStream;
use FSPoster\Psr\Http\Message\ResponseInterface;

class Api
{
	private ?Client $client = null;
	public bool $needsSessionUpdate = false;

	public AuthData $authData;
	public ?string  $proxy = null;

	public string $authException = \Exception::class;
	public string $postException = \Exception::class;

	public function setProxy ( ?string $proxy ): self
	{
		$this->proxy = $proxy;

		return $this;
	}

	public function setAuthException ( string $exceptionClass ): self
	{
		$this->authException = $exceptionClass;

		return $this;
	}

	public function setPostException ( string $exceptionClass ): self
	{
		$this->postException = $exceptionClass;

		return $this;
	}

	private function getClientUserAgent()
	{
		return $this->authData->userAgent ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	}

    public function setAuthData( AuthData $authData ) : Api
    {
        $this->authData = $authData;

        $this->fb_dtsg();

        return $this;
    }

	public function getClient ()
	{
		if( is_null( $this->client ) )
		{
			$this->client = new Client( [
				'cookies'         => $this->buildCookies(),
				'allow_redirects' => [ 'max' => 5 ],
				'proxy'           => empty( $this->proxy ) ? null : $this->proxy,
				'verify'          => false,
				'http_errors'     => false,
				'headers'         => [
					'User-Agent'        => $this->getClientUserAgent() ,
					'Sec-Fetch-Site'    => 'same-origin'
				],
			] );
		}

		return $this->client;
	}

	private function buildHeaders ( array $additionalHeaders = [] ) : array
    {
		$headers = [
			'Accept'                      => '*/*',
			'Accept-Encoding'             => 'gzip',
			'User-Agent'                  => $this->getClientUserAgent(),
			'viewport-width'              => 1229,
			'Content-Type'                => 'application/x-www-form-urlencoded',
			'Origin'                      => 'https://www.facebook.com',
			'sec-ch-prefers-color-scheme' => 'light',
			'sec-ch-ua'                   => '".Not/A)Brand";v="99", "Google Chrome";v="103", "Chromium";v="103"',
			'sec-ch-ua-mobile'            => '?0',
			'sec-ch-ua-platform'          => '"Windows"',
			'Sec-Fetch-Dest'              => 'empty',
			'Sec-Fetch-Mode'              => 'cors',
			'Sec-Fetch-Site'              => 'same-origin',
			'Connection'                  => 'keep-alive',
			'Host'                        => 'www.facebook.com',
			'X-FB-LSD'                    => $this->authData->lsd,
		];

		return array_merge( $headers, $additionalHeaders );
	}

	private function buildCookies () : CookieJar
    {
		$cookies = [
			'c_user'        => $this->authData->fbUserId,
			'xs'            => $this->authData->fbSess,
			'm_page_voice'  => $this->authData->fbUserId,
			'm_pixel_ratio' => '1.5625',
			'dpr'           => '1.5625',
			'oo'            => 'v1',
			'wd'            => '1229x582',
		];

		if ( ! empty( $this->authData->newPageID ) )
		{
			$cookies['i_user'] = $this->authData->newPageID;
		}

		$cooks = [];

		foreach ( $cookies as $k => $v )
		{
			$cooks[] = [
				"Name"     => $k,
				"Value"    => $v,
				"Domain"   => ".facebook.com",
				"Path"     => "/",
				"Max-Age"  => null,
				"Expires"  => null,
				"Secure"   => false,
				"Discard"  => false,
				"HttpOnly" => false,
				"Priority" => "HIGH",
			];
		}

		return new CookieJar( false, $cooks );
	}

    private function request($method, $url, $options): ResponseInterface
    {
        /** @var ResponseInterface $response $response */
        $response = $this->getClient()->$method($url, $options);

        $setCookie = $response->getHeader( 'Set-Cookie' );

        foreach ( $setCookie as $c )
        {
            if( strpos( $c, 'xs=' ) === 0 )
            {
                $xs = substr( explode( ';', $c )[0], 3 );
                $this->authData->fbSess = $xs;
                $this->needsSessionUpdate = true;
            }
        }

        return $response;
    }

    private function get($url, $options = []): ResponseInterface
    {
        return self::request('get', $url, $options);
    }

    private function post($url, $options = []): ResponseInterface
    {
        return self::request('post', $url, $options);
    }

	private function buildSendData ( string $av, string $apiFriendlyName, string $docID, $variables = [] ) : array
    {
		$sendData = [
			'fb_dtsg'                  => $this->authData->fb_dtsg,
			'lsd'                      => $this->authData->lsd,
			'__user'                   => empty( $this->authData->newPageID ) ? $this->authData->fbUserId : $this->authData->newPageID,
			'av'                       => $av,
			'req'                      => '1c',
			'dpr'                      => '2',
			'__ccg'                    => 'GOOD',
			'__comet_reg'              => '1',
			'serve_timestamps'         => 'true',
			'fb_api_req_friendly_name' => $apiFriendlyName,
			'fb_api_caller_class'      => 'RelayModern',
			'doc_id'                   => $docID,
		];

		if ( ! empty( $variables ) )
		{
			$sendData[ 'variables' ] = json_encode( $variables );
		}

		return $sendData;
	}

    public function getMe () : array
    {
		try
		{
			$req = $this->get( 'https://www.facebook.com/', [
				'allow_redirects' => [ 'max' => 0 ],
			] );

			$location = $req->getHeader( 'Location' );

			if ( ! empty( $location ) && strpos( $location[0], '/checkpoint/' ) > -1 )
			{
                throw new Exception(fsp__( 'Your account seems to be blocked by Facebook. You need to unblock it before adding the account.' ));
			}

			$getInfo = (string) $req->getBody();
		}
		catch ( Exception $e )
		{
            throw new $this->authException( $e->getMessage() );
		}

		preg_match( '/\"USER_ID\":\"([0-9]+)\"/i', $getInfo, $accountId );
		$accountId = $accountId[ 1 ] ?? '?';

		preg_match( '/\"NAME\":\"([^\"]+)\"/i', $getInfo, $name );
		$name = json_decode( '"' . ( $name[ 1 ] ?? '?' ) . '"' );

	    if ( $this->authData->fbUserId !== $accountId )
	    {
		    $this->needsSessionUpdate = false;
		    throw new $this->authException();
	    }

		return [
			'id'   => $accountId,
			'name' => $name,
		];
	}

	public function fetchPages () : array
    {
		$myPagesArr = [];

		try
		{
			$result = (string) $this->get( 'https://www.facebook.com/pages/?category=your_pages&ref=bookmarks', [
				'headers' => [
					'Accept'         => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
					'User-Agent'     => $this->getClientUserAgent(),
					'Sec-Fetch-Dest' => 'document',
					'Sec-Fetch-Mode' => 'navigate',
				],
			] )->getBody();
		}
		catch ( Exception $e )
		{
			$result = '';
		}

        preg_match('/"profiles":{"edges":(\[.+?])/', $result, $matches);

        if ( empty( $matches[ 1 ] ) )
        {
            return $myPagesArr;
        }

        $pages = json_decode($matches[1], true) ?: [];

        foreach ( $pages as $page )
        {
            $page = $page['node'];

            if(empty($page['profile']['delegate_page_id']))
            {
                continue;
            }

            $myPagesArr[] = [
                'id' => $page['profile']['id'],
                'name' => $page['profile']['name'],
                'delegate_page_id' => $page['profile']['delegate_page_id'],
                'cover' => 'https://graph.facebook.com/' . $page['profile']['delegate_page_id'] . '/picture',
            ];
        }

        return $myPagesArr;
	}

	public function fetchGroups () : array
    {
		$listTypes = [
			'ADMIN_MODERATOR_GROUPS',
			'NON_ADMIN_MODERATOR_GROUPS',
		];

		$groups = [];

		foreach ( $listTypes as $listType )
		{
			$variables = [
				'count'    => 10,
				'listType' => $listType,
				'scale'    => 2,
			];

			$sendData = $this->buildSendData( $this->authData->fbUserId, 'GroupsLeftRailYourGroupsPaginatedQuery', '5325328520844756', $variables );

			$hasNextPage = true;

			while ( $hasNextPage )
			{
				//sleep(15.0/mt_rand(10, 30));
				try
				{
					$post = (string) $this->post( 'https://www.facebook.com/api/graphql/', [
							'form_params' => $sendData,
						]
					)->getBody();

					$groupList = json_decode( $post, true );

					if ( isset( $groupList['data']['viewer']['groups_tab']['tab_groups_list']['edges'] ) )
					{
						foreach ( $groupList['data']['viewer']['groups_tab']['tab_groups_list']['edges'] as $groupData )
						{
							if ( $groupData['node'] )
							{
								$groups[] = [
									'id'    => $groupData['node']['id'],
									'name'  => $groupData['node']['name'],
									'cover' => $groupData['node']['profile_picture']['uri'] ?? null,
								];
							}
						}

						$hasNextPage = ! empty( $groupList['data']['viewer']['groups_tab']['tab_groups_list']['page_info']['has_next_page'] );

						if ( $groupList['data']['viewer']['groups_tab']['tab_groups_list']['page_info']['end_cursor'] )
						{
							$variables['cursor']   = $groupList['data']['viewer']['groups_tab']['tab_groups_list']['page_info']['end_cursor'];
							$sendData['variables'] = json_encode( $variables );
						}
					}
					else
					{
						break;
					}
				}
				catch ( Exception $e )
				{
					return [];
				}
			}
		}

		return $groups;
	}

	public function getStats ( string $postId ) : array
    {
		try
		{
			$result = (string) $this->get( 'https://touch.facebook.com/' . $postId )->getBody();
		}
		catch ( Exception $e )
		{
			$result = '';
		}

		preg_match( '/,comment_count:([0-9]+),/i', $result, $comments );
		preg_match( '/,share_count:([0-9]+),/i', $result, $shares );
		preg_match( '/,reactioncount:([0-9]+),/i', $result, $likes );

		return [
            [
                'label' => fsp__('Likes'),
                'value' => $likes[ 1 ] ?? 0,
            ],
            [
                'label' => fsp__('Comments'),
                'value' => $comments[ 1 ] ?? 0,
            ],
            [
                'label' => fsp__('Shares'),
                'value' => $shares[ 1 ] ?? 0,
            ],
		];
	}

    public function sendPost ( PostingData $postingData ) : string
    {
        if( $postingData->edge === 'story' )
            return $this->sendToStory( $postingData );
        else
            return $this->sendToTimeline( $postingData );
    }

    public function sendToTimeline ( PostingData $postingData ) : string
    {
        $posterId = $postingData->posterId;
        $nodeFbId = $postingData->ownerId;
        $nodeType = $postingData->channelType;

		$attachedMedia = [];

		if ( ! empty( $postingData->link ) )
		{
			$linkVariables = [
				"feedLocation"                                          => "FEED_COMPOSER",
				"focusCommentID"                                        => null,
				"goodwillCampaignId"                                    => "",
				"goodwillCampaignMediaIds"                              => [],
				"goodwillContentType"                                   => null,
				"params"                                                => [
					"url" => $postingData->link,
				],
				"privacySelectorRenderLocation"                         => "COMET_COMPOSER",
				"renderLocation"                                        => "composer_preview",
				"parentStoryID"                                         => null,
				"scale"                                                 => 2,
				"useDefaultActor"                                       => false,
				"shouldIncludeStoryAttachment"                          => false,
				"__relay_internal__pv__FBReelsEnableDeferrelayprovider" => false,
			];

			$linkSendData = $this->buildSendData(
				empty( $this->authData->newPageID ) ? $this->authData->fbUserId : $this->authData->newPageID,
				empty( $this->authData->newPageID ) ? 'ComposerStoryCreateMutation' : 'ComposerLinkAttachmentPreviewQuery',
				empty( $this->authData->newPageID ) ? '7700513916656935' : '5847144011982556',
				$linkVariables );
			$linkSendData = http_build_query( $linkSendData, '', '&' );

			try
			{
				$post = $this->post( 'https://www.facebook.com/api/graphql/', [
					'body'    => $linkSendData,
					'headers' => $this->buildHeaders( [
						'Content-Length'     => strlen( $linkSendData ),
						'X-FB-Friendly-Name' => empty( $this->authData->newPageID ) ? 'ComposerStoryCreateMutation' : 'ComposerLinkAttachmentPreviewQuery',
					] ),
				] )->getBody()->getContents();

				$linkInfo = json_decode( $post, true );

				$linkScrapeData = $linkInfo[ 'data' ][ 'link_preview' ][ 'share_scrape_data' ] ?? json_encode( [
                    'share_type'   => 100,
                    'share_params' => [ 'url' => $postingData->link ],
                ] );
			}
			catch ( Exception $e )
			{
				$linkScrapeData = json_encode( [ 'share_type' => 100, 'share_params' => [ 'url' => $postingData->link ] ] );
			}

			$attachedMedia = [
				[
					'link' => [
						'share_scrape_data' => $linkScrapeData,
					],
				],
			];
		}
		else if( ! empty( $postingData->uploadMedia ) )
		{
			$mediaType = $postingData->uploadMedia[0]['type'];

			if ( $mediaType === 'image' )
			{
				$sendData['photo_ids'] = [];

				foreach ( $postingData->uploadMedia as $imageEl )
				{
					$photoId = $this->uploadPhoto( $imageEl['url'], empty( $this->authData->newPageID ) ? $nodeFbId : ( $nodeType == 'group' ? $posterId : $nodeFbId ), $nodeType );

					if ( $photoId == 0 )
					{
						continue;
					}

					$attachedMedia[] = [
						'photo' => [
							'id' => $photoId,
						],
					];
				}
			}
			else if ( $mediaType === 'video' )
				throw new $this->postException( "Error! Facebook cookie method doesn't allow sharing videos" );
		}

		$uuid = self::uuid();

		$variables = [
			"input"                                                 => [
				"composer_entry_point"    => "inline_composer",
				"composer_source_surface" => "timeline",
				"source"                  => "WWW",
				"attachments"             => [],
				"audience"                => [
					"privacy" => [
						"allow"               => [],
						"base_state"          => "EVERYONE",
						"deny"                => [],
						"tag_expansion_state" => "UNSPECIFIED",
					],
				],
				"message"                 => [
					"ranges" => [],
					"text"   => $postingData->message,
				],
				"with_tags_ids"           => [],
				"inline_activities"       => [],
				"explicit_place_id"       => "0",
				"text_format_preset_id"   => "0",
				"logging"                 => [
					"composer_session_id" => $uuid,
				],
				"navigation_data"         => [
					"attribution_id_v2" => "ProfileCometTimelineListViewRoot.react,comet.profile.timeline.list,unexpected,1658391971861,956325,190055527696468;CometHomeRoot.react,comet.home,via_cold_start,1658391840657,922327,4748854339",
				],
				"tracking"                => [
					null,
				],
				"actor_id"                => "$nodeFbId",
				"client_mutation_id"      => "1",
			],
			"displayCommentsFeedbackContext"                        => null,
			"displayCommentsContextEnableComment"                   => null,
			"displayCommentsContextIsAdPreview"                     => null,
			"displayCommentsContextIsAggregatedShare"               => null,
			"displayCommentsContextIsStorySet"                      => null,
			"feedLocation"                                          => "TIMELINE",
			"feedbackSource"                                        => 0,
			"focusCommentID"                                        => null,
			"gridMediaWidth"                                        => 230,
			"groupID"                                               => null,
			"scale"                                                 => 2,
			"privacySelectorRenderLocation"                         => "COMET_STREAM",
			"renderLocation"                                        => "timeline",
			"useDefaultActor"                                       => false,
			"inviteShortLinkKey"                                    => null,
			"isFeed"                                                => false,
			"isFundraiser"                                          => false,
			"isFunFactPost"                                         => false,
			"isGroup"                                               => false,
			"isEvent"                                               => false,
			"isTimeline"                                            => false,
			"isSocialLearning"                                      => false,
			"isPageNewsFeed"                                        => false,
			"isProfileReviews"                                      => false,
			"isWorkSharedDraft"                                     => false,
			"UFI2CommentsProvider_commentsKey"                      => "ProfileCometTimelineRoute",
			"hashtag"                                               => null,
			"canUserManageOffers"                                   => false,
			"__relay_internal__pv__FBReelsEnableDeferrelayprovider" => false,
		];

		if ( $nodeType === 'account' )
		{
			$variables['isTimeline']                    = true;
			$variables['input']['idempotence_token']    = $uuid . "_FEED";

			$av              = $this->authData->fbUserId;
			$docID           = '4762364973863293';
			$apiFriendlyName = 'ComposerStoryCreateMutation';
		}
		else if ( $nodeType === 'ownpage' )
		{
			$variables['isFeed']                                            = true;
			$variables['UFI2CommentsProvider_commentsKey']                  = 'CometModernHomeFeedQuery';
			$variables['renderLocation']                                    = 'homepage_stream';
			$variables['input']['idempotence_token']                        = $uuid . "_FEED";
			$variables['feedbackSource']                                    = 1;
			$variables['gridMediaWidth']                                    = null;
			$variables['input']['composer_source_surface']                  = 'newsfeed';
			$variables['input']['navigation_data']['attribution_id_v2']     = 'CometHomeRoot.react,comet.home,via_cold_start,1663822873505,117673,4748854339,';
			$variables['feedLocation']                                      = 'NEWSFEED';

			$av              = $nodeFbId;
			$docID           = '5615191498501965';
			$apiFriendlyName = 'ComposerStoryCreateMutation';
		}
		else
		{
			$variables['isGroup']                                           = true;
			$variables['UFI2CommentsProvider_commentsKey']                  = 'CometGroupDiscussionRootSuccessQuery';
			$variables['renderLocation']                                    = 'group';
			$variables['gridMediaWidth']                                    = null;
			$variables['input']['composer_source_surface']                  = 'group';
			$variables['input']['navigation_data']['attribution_id_v2']     = 'CometGroupDiscussionRoot.react,comet.group,tap_bookmark,1663824614770,694937,462245802259084,';
			$variables['feedLocation']                                      = 'GROUP';
			$variables['input']['audience']                                 = [ 'to_id' => $nodeFbId ];

			$variables['input']['actor_id'] = empty( $posterId ) ? $this->authData->fbUserId : $posterId;

			$av              = empty( $this->authData->newPageID ) ? $this->authData->fbUserId : ( empty( $posterId ) ? $this->authData->fbUserId : $posterId );
			$docID           = '7977194912351758';
			$apiFriendlyName = 'ComposerStoryCreateMutation';
		}

		$variables['input']['attachments'] = $attachedMedia;

		$sendData = $this->buildSendData( $av, $apiFriendlyName, $docID, $variables );
		$sendData = http_build_query( $sendData, '', '&' );

		try
		{
			$post = (string) $this->post( 'https://www.facebook.com/api/graphql/', [
				'headers' => $this->buildHeaders( [
					'Content-Length'     => strlen( $sendData ),
					'X-FB-Friendly-Name' => 'ComposerStoryCreateMutation',
				] ),
				'body'    => $sendData,
			] )->getBody();
		}
		catch ( \Exception $e )
		{
			throw new $this->postException( 'Error! ' . $e->getMessage() );
		}

		preg_match( '/legacy_story_hideable_id\":\"([0-9]+?)\"/', $post, $matches );

		if ( isset( $matches[1] ) )
            return $matches[1];

		$parsedError = $this->parseErrors( $post );

		if ( $parsedError !== false )
            throw new $this->postException( $parsedError );

		/*
		 * Bura giribse nese yolunda deil. getMe() ile bir nov check edir Cookie ishlekdirmi,
		 * ishlek olmasa getMe() authException throw edir zaten ve channel disable olacag avtoamtik;
		 * Yox eger throw etmese artig demekki channellik birshey deil, nese bilinmeyen error chixib paylashim zamani.
		*/
		$this->getMe();

		throw new $this->postException( 'An error occurred while sharing the post.' );
	}

    public function sendToStory ( PostingData $postingData ) : string
    {
        $imgForStory = $postingData->uploadMedia[0];

        $uploadID = $this->uploadPhoto( $imgForStory[ 'path' ], $postingData->ownerId, $postingData->channelType );

        if ( empty( $uploadID ) )
	        throw new $this->postException( 'Failed to upload the image' );

        $uuid = self::uuid();

        $isNewPageORAccount = ! empty( $this->authData->newPageID ) || $postingData->channelType === 'account';

        if ( $isNewPageORAccount )
        {
            $variables = [
                'input' => [
                    'actor_id'              => $postingData->ownerId,
                    'attachments'           => [
                        [
                            'photo' => [
                                'id'       => $uploadID,
                                'overlays' => null,
                            ],
                        ],
                    ],
                    'audiences'             => [
                        [
                            'stories' => [
                                'self' => [
                                    'target_id' => $postingData->ownerId,
                                ],
                            ],
                        ],
                    ],
                    'audiences_is_complete' => true,
                    'client_mutation_id'    => '1',
                    'logging'               => [
                        'composer_session_id' => $uuid,
                    ],
                    'navigation_data'       => [
                        'attribution_id_v2' => 'StoriesCreateRoot.react,comet.stories.create,unexpected,1665383878113,546960,,;CometHomeRoot.react,comet.home,via_cold_start,1665383845813,595122,4748854339,',
                    ],
                    'source'                => 'WWW',
                    'tracking'              => [
                        null,
                    ],
                ],
            ];

            if ( ! empty( $postingData->link ) && $postingData->channelType !== 'account' )
            {
                $variables[ 'input' ][ 'call_to_action_data' ] = [
                    'is_cta_share_post' => true,
                    'link'              => $postingData->link,
                    //'page' => $legatedID,
                    'type'              => 'SEE_MORE',
                ];
            }
        }
        else
        {
            $variables = [
                'input' => [
                    'client_mutation_id' => '1',
                    'base'               => [
                        'actor_id'                        => $postingData->ownerId,
                        'composer_entry_point'            => 'biz_web_content_manager_calendar_tab_stories',
                        'source'                          => 'WWW',
                        'unpublished_content_data'        => null,
                        'attachments'                     => [
                            [
                                'photo' => [
                                    'id'                        => $uploadID,
                                    'story_call_to_action_data' => null,
                                ],
                            ],
                        ],
                        'story_original_attachments_data' => [
                            [
                                'original_photo_id' => $uploadID,
                                'burned_photo'      => [
                                    'story_call_to_action_data' => empty( $postingData->link ) ? null : [
                                        'is_cta_share_post' => true,
                                        'link'              => $postingData->link,
                                        'type'              => 'SEE_MORE',
                                        'link_title'        => 'See more',
                                    ],
                                    'id'                        => $uploadID,
                                ],
                            ],
                        ],
                    ],
                    'channels'           => [
                        'FACEBOOK_STORY',
                    ],
                    'identities'         => [
                        $postingData->ownerId,
                    ],
                    'logging'            => [
                        'composer_session_id' => $uuid,
                    ],
                ],
            ];
        }

        $sendData = [
            'fb_dtsg'                  => $this->authData->fb_dtsg,
            'lsd'                      => $this->authData->lsd,
            '__user'                   => empty( $this->authData->newPageID ) ? $this->authData->fbUserId : $postingData->ownerId,
            'av'                       => $postingData->ownerId,
            '__a'                      => '1',
            'dpr'                      => '2',
            '__ccg'                    => 'GOOD',
            '__comet_req'              => '15',
            'req'                      => '1c',
            'fb_api_caller_class'      => 'RelayModern',
            'fb_api_req_friendly_name' => $isNewPageORAccount ? 'StoriesCreateMutation' : 'BusinessComposerStoryCreationMutation',
            'server_timestamps'        => 'true',
            'doc_id'                   => $isNewPageORAccount ? '5731665720186663' : '5354681964593829',
            'variables'                => json_encode( $variables ),
        ];

        $sendData = http_build_query( $sendData, '', '&' );

        try
        {
            $post = $this->post( 'https://facebook.com/api/graphql/', [
                'headers' => $this->buildHeaders( [
                    'X-FB-Friendly-Name' => 'ComposerStoryCreateMutation',
                    'Content-Length'     => strlen( $sendData ),
                ] ),
                'body'    => $sendData,
            ] )->getBody()->getContents();
        }
        catch ( Exception $e )
        {
	        throw new $this->postException( $e->getMessage() );
        }

        $story = json_decode( $post, true );

        if ( empty( $story ) )
	        throw new $this->postException( 'An error occured while sharing the story' );

        if ( ! $isNewPageORAccount )
        {
            $parsedError = $this->parseErrors( $post );

            if ( $parsedError !== false )
	            throw new $this->postException( $parsedError );

            return (string)$postingData->ownerId;
        }

        if ( ! isset( $story[ 'data' ][ 'story_create' ][ 'viewer' ][ 'actor' ][ 'story_bucket' ][ 'nodes' ][ 0 ][ 'first_story_to_show' ][ 'id' ] ) )
	        throw new $this->postException( 'An error occured while sharing the story.' );

        $storyID = $story[ 'data' ][ 'story_create' ][ 'viewer' ][ 'actor' ][ 'story_bucket' ][ 'nodes' ][ 0 ][ 'first_story_to_show' ][ 'id' ];

        $storyID = base64_decode( $storyID );

        if ( empty( $storyID ) )
	        throw new $this->postException( 'An error occured while sharing the story.' );

        $storyID = explode( ':', $storyID );

        if ( empty( $storyID[2] ) )
	        throw new $this->postException( 'An error occured while sharing the story.' );

        return (string)$storyID[2];
    }

    private function fb_dtsg () : string
    {
		if ( empty( $this->authData->fb_dtsg ) )
		{
			try
			{
				$getFbDtsg = $this->get( 'https://facebook.com/' )->getBody();
			}
			catch ( Exception $e )
			{
				$getFbDtsg = '';
			}

			preg_match( '/DTSGInitialData\",\[],\{\"token\":\"(.+?)\"}/', $getFbDtsg, $fb_dtsg );
			preg_match( '/LSD\",\[],\{\"token\":\"(.+?)\"}/', $getFbDtsg, $LSD );

			if ( isset( $fb_dtsg[ 1 ] ) )
			{
				$this->authData->fb_dtsg = $fb_dtsg[ 1 ];
			}

			if ( isset( $LSD[ 1 ] ) )
			{
				$this->authData->lsd = $LSD[ 1 ];
			}

			if ( strpos( $getFbDtsg, 'cookie/consent' ) > -1 )
			{
				try
				{
                    $this->post( 'https://www.facebook.com/cookie/consent/', [
						'form_params' => [
							'fb_dtsg'        => $this->fb_dtsg(),
							'__a'            => '1',
							'__user'         => $this->authData->fbUserId,
							'accept_consent' => 'true',
							'__ccg'          => 'GOOD',
						],
					] );
				}
				catch ( Exception $e )
				{
					return '';
				}
			}
		}

		return (string)$this->authData->fb_dtsg;
	}

	private function uploadPhoto ( $photo, $target, $targetType )
	{
		$query = [
			'av'      => ! empty( $this->authData->newPageID ) || $targetType == 'ownpage' ? $target : $this->authData->fbUserId,
			'__user'  => empty( $this->authData->newPageID ) ? $this->authData->fbUserId : $target,
			'__a'     => 1,
			'__req'   => '3l',
			'dpr'     => 2,
			'__ccg'   => 'EXCELLENT',
			'fb_dtsg' => $this->authData->fb_dtsg,
			'lsd'     => $this->authData->lsd,
		];

		$basename = basename( $photo );

		if ( strpos( $basename, '.' ) === false )
		{
			$basename = $basename . '.jpg';
			$img      = file_get_contents( $photo );
		}
		else
		{
			$img = Curl::getURL( $photo, $this->proxy );
		}

		$postData = [
			[
				'name'     => 'source',
				'contents' => 8,
			],
			[
				'name'     => 'profile_id',
				'contents' => empty( $this->authData->newPageID ) ? $this->authData->fbUserId : $target,
			],
			[
				'name'     => 'waterfallxapp',
				'contents' => 'comet',
			],
			[
				'name'     => 'farr',
				'contents' => $img,
				'filename' => $basename,
			],
			[
				'name'     => 'upload_id',
				'contents' => 'jsc_c_jh',
			],
		];

		$endpoint = 'https://upload.facebook.com/ajax/react_composer/attachments/photo/upload?' . http_build_query( $query, '', '&' );

		$body = new MultipartStream(
			$postData,
			'------WebKitFormBoundaryEDnegskZbO29yK7o'
		);

		try
		{
			$post = $this->post( $endpoint, [
				'body'    => $body,
				'headers' => $this->buildHeaders(
					[
						'Content-Length' => strlen( $body ),
						'Content-Type'   => 'multipart/form-data; boundary=------WebKitFormBoundaryEDnegskZbO29yK7o',
						'Host'           => 'upload.facebook.com',
					]
				),
			] )->getBody()->getContents();
		}
		catch ( Exception $e )
		{
			$post = '';
		}

        /**
         * If the session is expired, throw an exception to re-authorize the account.
         * This problem reported by portuguese user, that's why we are checking for this message.
         * Other languages should be handled as well.
         */
        if ( str_contains( $post, "sessão iniciada" ) ) {
            throw new $this->authException("Session expired. Please re-authorize the account.");
        }

		preg_match( '/\"photoID\":\"([0-9]+)/i', $post, $photoId );

		return $photoId[ 1 ] ?? 0;
	}

	private function parseErrors ( $post )
	{
		$postDecoded = json_decode( $post, true );

		if ( isset( $postDecoded[ 'errors' ][ 0 ][ 'description' ] ) )
		{
			$desc = $postDecoded[ 'errors' ][ 0 ][ 'description' ];

			if ( is_array( $desc ) && isset( $desc[ '__html' ] ) && is_string( $desc[ '__html' ] ) )
			{
				return htmlspecialchars( $desc[ '__html' ] );
			}
			else if ( is_array( $desc ) )
			{
				return htmlspecialchars( json_encode( $desc[ '__html' ] ) );
			}
			else
			{
				return $desc;
			}
		}
		else
		{
			preg_match( '/errorDescription\":\"(.+?)\"/', $post, $matches );

			if ( isset( $matches[ 1 ] ) )
			{
				return $matches[ 1 ];
			}
		}

		return false;
	}

	private static function uuid () : string
    {
		$uuid = md5( uniqid() );

		$return = substr( $uuid, 0, 8 ) . "-";
		$return .= substr( $uuid, 8, 4 ) . "-";
		$return .= substr( $uuid, 12, 4 ) . "-";
		$return .= substr( $uuid, 16, 4 ) . "-";
		$return .= substr( $uuid, 20, 10 );

		return $return;
	}

}
