<?php

namespace FSPoster\App\SocialNetworks\Xing\Api;

use Exception;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\GuzzleHttp\Client;
use FSPoster\GuzzleHttp\Cookie\CookieJar;
use FSPoster\GuzzleHttp\Exception\GuzzleException;

class Api
{
    public AuthData $authData;
    public ?string  $proxy = null;

    public string $authException = Exception::class;
    public string $postException = Exception::class;

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function setProxy(?string $proxy): self
    {
        $this->proxy = $proxy;

        return $this;
    }

    public function setAuthData (AuthData $authData): self
    {
        $this->authData = $authData;

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

	private Client $client;
	private string $endpoint = 'https://www.xing.com/xing-one/api';
	private string $domain = 'www.xing.com';

	public function sendPost ( PostingData $postingData ) : string
	{
		$message    = trim( $postingData->message );
		$visibility = $postingData->visibility;
		$actorID    = ( $postingData->channelType === 'account' ? 'surn:x-xing:users:user:' : 'surn:x-xing:entitypages:page:' ) . $postingData->remoteId;

		$data = [
			'operationName' => 'CreateTextPosting',
			'variables'     => [
				'actorGlobalId'    => $actorID,
				'comment'          => '',
				'commentArticleV1' => [
					[
						'articleParagraph' => [
							'text'    => $message,
							'markups' => []
						]
					]
				],
				'visibility'       => $visibility === 'public' ? 'PUBLIC' : 'PRIVATE',
				'links'            => null,
				'images'           => null,
				'audience'         => $visibility === 'public' ? null : 'surn:x-xing:contacts:network:' . $postingData->remoteId . ( $visibility === 'same_city' ? ':same_city' : '' )
			],
			'query'         => 'mutation CreateTextPosting($actorGlobalId: GlobalID!, $comment: String!, $commentArticleV1: [ArticlesCreateArticleBlocksInput!], $visibility: PostingsVisibility, $images: [PostingsCreateImageAttachmentInput!], $links: [PostingsCreateLinkAttachmentInput!], $audience: [GlobalID!]) {  postingsCreatePosting(    input: {actorGlobalId: $actorGlobalId, comment: $comment, commentArticleV1: $commentArticleV1, visibility: $visibility, images: $images, links: $links, audience: $audience}  ) {    success {      id      actorGlobalId      activityId      comment      __typename    }    error {      message      details      __typename    }    __typename  }}'
		];

		if ( !empty($postingData->link) )
		{
			$data[ 'variables' ][ 'links' ] = [
				[
					'url' => $postingData->link
				]
			];

            $CVPResponse = self::cmd( 'POST', $this->endpoint, '{"query":"query SharePreview($url: URL!) {\\n  viewer {\\n    id\\n    linkPreview(url: $url) {\\n      success {\\n        title\\n        description\\n        sourceDomain\\n        cachedImageUrl\\n        metadata {\\n          sourceActor {\\n            title\\n            subtitle\\n            image\\n            message\\n            __typename\\n          }\\n          __typename\\n        }\\n        __typename\\n      }\\n      __typename\\n    }\\n    __typename\\n  }\\n}\\n", "operationName": "SharePreview", "variables":{"url":"' . $postingData->link . '"}}', [
                'content-type' => 'application/json',
                'Accept'       => '*/*'
            ] );

            if(isset($CVPResponse['errors']))
            {
                sleep(10);
                $CVPResponse = self::cmd( 'POST', $this->endpoint, '{"query":"query SharePreview($url: URL!) {\\n  viewer {\\n    id\\n    linkPreview(url: $url) {\\n      success {\\n        title\\n        description\\n        sourceDomain\\n        cachedImageUrl\\n        metadata {\\n          sourceActor {\\n            title\\n            subtitle\\n            image\\n            message\\n            __typename\\n          }\\n          __typename\\n        }\\n        __typename\\n      }\\n      __typename\\n    }\\n    __typename\\n  }\\n}\\n", "operationName": "SharePreview", "variables":{"url":"' . $postingData->link . '"}}', [
                    'content-type' => 'application/json',
                    'Accept'       => '*/*'
                ] );
            }
		}
		else if ( !empty($postingData->uploadMedia) )
		{
            $data[ 'variables' ][ 'images' ] = [];

            foreach ($postingData->uploadMedia as $media)
            {
                $uploadId = $this->uploadRequest( $media['path'] );

                if ( is_array( $uploadId ) && ! empty( $uploadId[ 'error_msg' ] ) )
                {
                    throw new $this->postException(
                        $uploadId[ 'error_msg' ]
                    );
                }

                $data[ 'variables' ][ 'images' ][] = [
                    'uploadId' => $uploadId
                ];
            }
		}

		$data = json_encode( $data, JSON_UNESCAPED_SLASHES );

		$result = self::cmd( 'POST', $this->endpoint, $data, [
            'content-type' => 'application/json',
            'Accept'       => '*/*'
        ] );

		if ( ! is_array( $result ) )
		{
            throw new $this->postException(
                fsp__( 'Unknown error' )
            );
		}

		if ( ! empty( $result[ 'error_msg' ] ) )
		{
            throw new $this->postException(
                $result[ 'error_msg' ]
            );
		}

		if ( ! empty( $result[ 'errors' ] ) )
		{
            throw new $this->postException(
                $result[ 'errors' ][ 0 ][ 'message' ]
            );
		}

        if ( isset( $result[ 'data' ][ 'postingsCreatePosting' ]['error']['details']['0'] ) )
        {
            throw new $this->postException(
                $result[ 'data' ][ 'postingsCreatePosting' ]['error']['details']['0']
            );
        }

		if ( ! empty( $postingArray[ 'error' ] ) )
		{
            throw new $this->postException(
                $postingArray[ 'error' ][ 'message' ]
            );
		}

        if ( empty( $result[ 'data' ] ) || empty( $result[ 'data' ][ 'postingsCreatePosting' ] ) )
        {
            throw new $this->postException(
                fsp__( 'Unknown error' )
            );
        }

        return $result[ 'data' ][ 'postingsCreatePosting' ][ 'success' ][ 'activityId' ];
	}

	public function cmd ( $method, $endpoint, $body, $headers = [], $isArray = true )
	{
		try
		{
			$response = $this->client->request( $method, $endpoint, [
				'headers' => $headers,
				'body'    => $body
			] )->getBody();
        }
		catch ( GuzzleException $e )
		{
			return [
				'status'    => 'error',
				'error_msg' => fsp__( 'Error! %s', [ $e->getMessage() ] )
			];
		}

		if ( $isArray )
		{
			return json_decode( $response, true );
		}

		return $response;
	}

    /**
     * @throws Exception
     */
    public function getAccountData () : array
	{
		$data = [
			'operationName' => 'xingFrameQuery',
			'variables'     => [],
			'query'         => 'query xingFrameQuery {  viewer {    id    webTrackingData {      PropHashedUserId      __typename    }    xingId {      academicTitle      birthday      displayName      displayFlag      userFlags {        displayFlag        __typename      }      firstName      gender      globalId      id      lastName      pageName      profileImage(size: [SQUARE_128]) {        url        __typename      }      profileOccupation {        occupationOrg        occupationTitle        __typename      }      occupations {        headline        subline        __typename      }      __typename    }    features {      isXingEmployee      isJobsPoster      isAdmasterUser      isBrandPageCollaborator      isBasic      isPremium      isExecutive      isSales      hasProJobsMembership      isCraUser      isSeatManagerAdmin      showProbusinessInNavigation      showUpsellHint      showJobSeekerBoneyardUpsellHint      showPremiumBoneyardUpsellHint      hasCoachProfile      hasNewSettings      isBrandManagerEditor      __typename    }    featureSwitches    loginState    __typename  }}'
		];

		$accountData = self::cmd( 'POST', $this->endpoint, json_encode( $data ), [
            'Content-Type' => 'application/json',
            'Accept' => '*/*'
        ] );

		if ( ! empty( $accountData[ 'status' ] ) && $accountData[ 'status' ] === 'error' )
		{
            throw new Exception($accountData[ 'error_msg' ]);
		}

		if ( empty( $accountData[ 'data' ] ) || empty( $accountData[ 'data' ][ 'viewer' ][ 'id' ] ) )
		{
            throw new Exception(fsp__( 'The entered cookies are wrong' ));
		}

		return $accountData[ 'data' ][ 'viewer' ];
	}

    /**
     * @throws Exception
     */
    public function getCompanies () : array
	{
		$accountData = self::cmd( 'POST', $this->endpoint, '{"operationName":"xcpManagedCompanies","variables":{"first":9},"query":"query xcpManagedCompanies($first: Int, $after: String) {\n  viewer {\n    id\n    managedCompanies(first: $first, after: $after) {\n      pageInfo {\n        hasNextPage\n        endCursor\n        __typename\n      }\n      edges {\n        node {\n          company {\n            ...CompanyData\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment CompanyData on Company {\n  id\n  entityPageId\n  companyName\n  entityPage {\n    publicationStatus\n    slug\n    contract {\n      type\n      __typename\n    }\n    coverImage(dimensions: [{height: 600, width: 600, reference: \"xcp_medium\"}]) {\n      url\n      __typename\n    }\n    __typename\n  }\n  logos {\n    logo128px\n    __typename\n  }\n  industry {\n    localizationValue\n    __typename\n  }\n  kununuData {\n    ratingAverage\n    ratingCount\n    __typename\n  }\n  links {\n    default\n    public\n    __typename\n  }\n  address {\n    city\n    __typename\n  }\n  userContext {\n    followState {\n      isFollowing\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}', [
            'content-type' => 'application/json',
            'Accept'       => '*/*'
        ] );

		if ( ! empty( $accountData[ 'status' ] ) && $accountData[ 'status' ] === 'error' )
		{
            throw new Exception($accountData[ 'error_msg' ]);
		}

		if ( empty( $accountData[ 'data' ] ) || empty( $accountData[ 'data' ][ 'viewer' ][ 'id' ] ) )
		{
            throw new Exception(fsp__( 'The entered cookies are wrong' ));
		}

		return $accountData[ 'data' ][ 'viewer' ][ 'managedCompanies' ][ 'edges' ];
	}

	public function uploadRequest ( $photo )
	{
		$photoData = file_get_contents( $photo );

		if ( empty( $photoData ) )
		{
			return [
				'status'    => 'error',
				'error_msg' => fsp__( 'The given file path is not valid' )
			];
		}

		$data = [
			'operationName' => 'UploadRequest',
			'variables'     => [
				'application' => 'POSTINGS',
				'fileSize'    => strlen( $photoData ),
				'fileType'    => Helper::mimeContentType( $photo ) //only supports png/jpeg and gif formats
			],
			'query'         => 'mutation UploadRequest($application: UploadApplication!, $fileSize: Long!, $fileType: String) {  uploadRequest(    input: {application: $application, fileSize: $fileSize, fileType: $fileType}  ) {    success {      id      authToken      url      __typename    }    error {      id      message      __typename    }    __typename  }}'
		];

		$resp = self::cmd( 'POST', $this->endpoint, json_encode( $data ), [
            'content-type' => 'application/json',
            'Accept'       => '*/*'
        ] );

		if ( empty( $resp ) || empty( $resp[ 'data' ] ) )
		{
			return [
				'status'    => 'error',
				'error_msg' => ! empty( $resp[ 'errors' ] ) ? $resp[ 'errors' ][ 0 ][ 'message' ] : ( $resp[ 'error_msg' ] ?? fsp__( 'Couldn\'t upload the image or unknown error' ) )
			];
		}

		$uploadResp = $resp[ 'data' ][ 'uploadRequest' ];

		if ( ! isset( $uploadResp[ 'success' ] ) )
		{
			if ( ! empty( $resp[ 'data' ][ 'uploadRequest' ][ 'error' ][ 'message' ] ) )
			{
				return [
					'status'    => 'error',
					'error_msg' => $resp[ 'data' ][ 'uploadRequest' ][ 'error' ][ 'message' ]
				];
			}

			return [
				'status'    => 'error',
				'error_msg' => fsp__( 'Couldn\'t upload the image or unknown error' )
			];
		}

		$result = self::cmd( 'PATCH', $uploadResp[ 'success' ][ 'url' ], $photoData, [
			'Tus-Resumable' => '1.0.0',
			'Upload-Offset' => 0,
			'Authorization' => 'Bearer ' . $uploadResp[ 'success' ][ 'authToken' ],
			'content-type'  => 'application/offset+octet-stream',
            'Accept'        => '*/*'
		] );

		if ( ! empty( $result ) )
		{
			return [
				'status'    => 'error',
				'error_msg' => substr( $result, 0, 300 )
			];
		}

		return $uploadResp[ 'success' ][ 'id' ];
	}

	public function setClient () : Api
    {
		$cookieArr = [];

		foreach ( $this->authData->cookies as $k => $v )
		{
			$cookieArr[] = [
				"Name"     => $k,
				"Value"    => $v,
				"Domain"   => '.' . $this->domain,
				"Path"     => "/",
				"Max-Age"  => null,
				"Expires"  => null,
				"Secure"   => false,
				"Discard"  => false,
				"HttpOnly" => false,
				"Priority" => "HIGH"
			];
		}

		$cookieJar = new CookieJar( false, $cookieArr );

		$this->client = new Client( [
			'cookies'     => $cookieJar,
			'proxy'       => empty( $this->getProxy() ) ? null : $this->getProxy(),
			'verify'      => false,
			'http_errors' => false,
			'headers'     => [
				'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:75.0) Gecko/20100101 Firefox/75.0',
				'X-CSRF-Token' => $this->authData->cookies[ 'xing_csrf_token' ],
				'Host'         => $this->domain
			]
		] );

        return $this;
	}

}
