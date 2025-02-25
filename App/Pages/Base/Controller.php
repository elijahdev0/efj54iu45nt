<?php

namespace FSPoster\App\Pages\Base;

use Exception;
use FSPoster\App\Providers\Core\RestRequest;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\Helpers\CatWalker;
use FSPoster\App\Providers\Helpers\Date;
use FSPoster\App\Providers\Helpers\GuzzleClient;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\PluginHelper;
use FSPoster\App\Providers\License\ActivateLicenseRequest;
use FSPoster\App\Providers\License\LicenseAdapter;
use FSPoster\App\Providers\License\LicenseApiClient;
use FSPoster\App\SocialNetworks\Instagram\Helpers\FFmpeg;

class Controller
{

    public static function getTaxonomies ( RestRequest $request ): array
    {
        $search   = $request->param( 'search', '', RestRequest::TYPE_STRING );
        $search   = ! empty( $search ) ? $search : null;

        $args = [
            'public' => true,
        ];

        if ( ! empty( $search ) )
            $args['name__like'] = $search;

        $taxonomies = get_taxonomies( $args, 'objects' );

        return [
            'taxonomies' => array_values( array_map( function ( $taxonomy ) {
                return [
                    'label'    => $taxonomy->label,
                    'taxonomy' => $taxonomy->name,
                ];
            }, $taxonomies ) ),
        ];
    }

    public static function getTerms(RestRequest $request): array
    {
        $postType = $request->param('post_type', '', RestRequest::TYPE_STRING);
        $search = $request->param('search', '', RestRequest::TYPE_STRING);
        $search = !empty($search) ? $search : null;

        $taxonomies = CatWalker::getCats($search, $postType);
        $terms = [];

        foreach ($taxonomies as $taxonomy) {
            foreach ($taxonomy['options'] as $term) {
                $term['taxonomy'] = [
                    'label' => $taxonomy['label'],
                    'taxonomy' => $taxonomy['taxonomy'],
                ];

                $terms[] = $term;
            }
        }

        return ['terms' => $terms];
    }

    public static function getPostTypes ( RestRequest $request ): array
    {
        return [
            'post_types' => Helper::getPostTypes(),
        ];
    }

    /**
     * @throws Exception
     */
    public static function fetchStatisticsOptions ( RestRequest $request ): array
    {
		$apiClient = new LicenseApiClient();

        $getOptions = $apiClient->sendClientRequest('get_options_of_where_did_you_find_us_select');

        return [
            'options' => $getOptions['data']['options'] ?? []
        ];
    }

    /**
     * @throws Exception
     */
    public static function activateApp ( RestRequest $request ): array
    {
        $code           = $request->param( 'code', '', RestRequest::TYPE_STRING );
		$licenseType    = $request->require( 'license_type', RestRequest::TYPE_STRING, fsp__( 'Please select license type' ), ['free', 'paid'] );
        $statistic      = $request->require( 'statistic', RestRequest::TYPE_STRING, fsp__( 'Please select how did You find us' ) );
        $getEmails      = $request->param( 'receive_emails', false, RestRequest::TYPE_BOOL );
        $email          = $request->require( 'email', RestRequest::TYPE_STRING, fsp__( 'Please enter the email' ) );

		if( $licenseType === 'paid' && empty( $code ) )
			throw new Exception( fsp__( 'Please enter license code' ) );

	    if ( PluginHelper::isPluginActivated() )
		    throw new Exception( fsp__( 'Your plugin is already activated' ) );

	    $requestData = new ActivateLicenseRequest();

	    $requestData->licenseType = $licenseType;
	    $requestData->licenseCode = $code;
	    $requestData->siteUrl = network_site_url();
	    $requestData->pluginVersion = PluginHelper::getVersion();
	    $requestData->email = $email;
	    $requestData->receiveEmails = $getEmails ? 1 : 0;
	    $requestData->statisticData = $statistic;

		$result = LicenseAdapter::activateLicense( $requestData );
		if( ! empty( $result['activated_website'] ) )
			return $result;

	    LicenseAdapter::fetchAndRunMigrationData();

	    register_uninstall_hook( FSP_ROOT_DIR . '/init.php', [ PluginHelper::class, 'removePlugin' ] );

        return [];
    }

    /**
     * @throws Exception
     */
    public static function removeLicense ( RestRequest $request ): array
    {
        $code = $request->require( 'code', RestRequest::TYPE_STRING, fsp__( 'Please enter the purchase code' ) );
        $site = $request->require( 'domain', RestRequest::TYPE_STRING, fsp__( 'Please enter the domain name' ) );

	    if ( PluginHelper::isPluginActivated() )
		    throw new Exception( fsp__( 'Your plugin is already activated' ) );

	    $requestData = [
			'license_code'  => $code,
		    'site_url'      => $site
	    ];

	    $client = new LicenseApiClient();
	    $apiResult = $client->sendClientRequest('remove_license', 'POST', $requestData, true);

        if ( ! ( $apiResult['status'] ?? false ) )
            throw new Exception( $apiResult['message'] ?? fsp__('Something went wrong!') );

        return [];
    }

    /**
     * @throws Exception
     */
    public static function listFonts ( RestRequest $request ): array
    {
        $fonts            = Settings::get( 'google_fonts', [] );
        $fontsLastUpdated = Settings::get( 'google_fonts_last_updated', Date::epoch() ) + 7 * 24 * 3600;

        if ( ( empty( $fonts ) || $fontsLastUpdated > Date::epoch() ) && PluginHelper::isPluginActivated() )
        {
            Settings::set( 'google_fonts_last_updated', Date::epoch() );

            $client = new GuzzleClient();

            $response = $client->get( 'https://www.fs-poster.com/api/api.php', [
                'query' => [
                    'act'           => 'get_fonts',
                    'purchase_code' => Settings::get( 'license_code', '', true ),
                    'domain'        => network_site_url(),
                ],
            ] )->getBody()->getContents();

            $fonts = json_decode( $response, true ) ?: [];

            Settings::set( 'google_fonts', $fonts );
        }

        return [ 'fonts' => array_values( $fonts ) ];
    }

	public static function systemRequirements ()
	{
		$requirements = [
			'required_extensions' => [// doit burda hostnamede check etmek lazimdi. localhost, 127.0.0.1, .loc varsa error versin ki, bezi networkler senin local saytivi grab edib mediani download ede bilmeyecek deye errorlar alasan. Meselen Instagram, Facebook, Threads, etc. Cunki Onlarin API-si mediani URL ile qebul edirler.
				[
					'name'    => 'curl',
					'enabled' => extension_loaded( 'curl' ),
				],
				[
					'name'    => 'gd',
					'enabled' => extension_loaded( 'gd' ),
				],
				[
					'name'    => 'gd2',
					'enabled' => extension_loaded( 'gd' ),
				],
				[
					'name'    => 'mbstring',
					'enabled' => extension_loaded( 'mbstring' ),
				],
				[
					'name'          => 'ffmpeg/ffprobe',
					'enabled'       => FFmpeg::checkFFPROBE() !== false,
					'description'   => fsp__('To share a video on Instagram, FFmpeg, libx264 encoder and FFprobe are required. They are used when rendering the video.')
				],
				[
					'name'          => 'libx264',
					'enabled'       => FFmpeg::checkLibx264() !== false,
					'description'   => fsp__('To share a video on Instagram, FFmpeg, libx264 encoder and FFprobe are required. They are used when rendering the video.')
				],
			],
			'required_functions'  => [
				[
					'name'          => 'proc_open',
					'enabled'       => function_exists( 'proc_open' ),
					'description'   => fsp__('This function is required to execute the FFmpeg library when rendering videos.')
				],
				[
					'name'    => 'shell_exec',
					'enabled' => function_exists( 'shell_exec' ),
					'description'   => fsp__('This function is required to execute the FFmpeg library when rendering videos.')
				],
				[
					'name'    => 'exec',
					'enabled' => function_exists( 'exec' ),
					'description'   => fsp__('This function is required to execute the FFmpeg library when rendering videos.')
				],
				[
					'name'    => 'mb_strcut',
					'enabled' => function_exists( 'mb_strcut' ),
				],
				[
					'name'    => 'mb_substr',
					'enabled' => function_exists( 'mb_substr' ),
				],
				[
					'name'    => 'tempnam',
					'enabled' => function_exists( 'tempnam' ),
				],
				[
					'name'    => 'sys_get_temp_dir',
					'enabled' => function_exists( 'sys_get_temp_dir' ),
				],
				[
					'name'    => 'finfo_open',
					'enabled' => function_exists( 'finfo_open' ),
				],
				[
					'name'    => 'mime_content_type',
					'enabled' => function_exists( 'mime_content_type' ),
				],
				[
					'name'    => 'imagecreatefromwebp',
					'enabled' => function_exists( 'imagecreatefromwebp' ),
				],
			]
		];

		return $requirements;
	}

}