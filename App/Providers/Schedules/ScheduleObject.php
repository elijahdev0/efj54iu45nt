<?php

namespace FSPoster\App\Providers\Schedules;

use FSPoster\App\Models\Channel;
use FSPoster\App\Models\ChannelSession;
use FSPoster\App\Models\Schedule;
use FSPoster\App\Providers\Core\Settings;
use FSPoster\App\Providers\DB\Collection;
use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\Helpers\UrlShortenerService;
use FSPoster\App\Providers\Helpers\WPPostThumbnail;
use WP_Post;

class ScheduleObject
{

    /**
     * @var Schedule
     */
    private ?Collection $schedule;

    /**
     * @var Channel
     */
    private ?Collection $channel;

    /**
     * @var ChannelSession
     */
    private ?Collection $channelSession;

    public ?WP_Post $wpPost;

	private ?string $postOriginalUrl = null;
	private ?string $postCustomUrl = null;
	private ?string $postShortUrl  = null;

    private ?string $wpmlLangSaved = null;
    public bool $readOnlyMode;

    public function __construct ( int $scheduleId, bool $readOnlyMode = false )
    {
		/*
		 * Read Only Mode - Burada bezi caseler olur ki, meselen, URL Shorterde:
		 * Rest Request atir URL Shortener serviceye ve URL-i short edir.
		 * Ve ya diger bir case`de AI addonu request atib AI content generasiya edir.
		 * Caledardan cari object chagrilanda ve orda Usere hansi content paylashilacagi gosterilende bu requestler ishe dushur ve calendar gereksiz yere gec yuklenir
		 * Read only mood ona gore yaradilib. True edilir ve bu casede hechbir sorgu atilmir, url short edilmir.
		 * Hemchinin bezi social networklerde story image/video generasiya/render edilir. Bu da calendarin loadini gecikdirir. Bu caselerde de readOnlyMode qulag asilir.
		*/
		$this->readOnlyMode     = $readOnlyMode;
        $this->schedule         = Schedule::get( $scheduleId );
	    $this->channel          = $this->getSchedule()->channel->withoutGlobalScope('soft_delete')->fetch();
	    $this->channelSession   = $this->channel->channel_session->fetch();
	    $this->wpPost           = get_post( $this->getSchedule()->wp_post_id );

	    if ( ! $this->readOnlyMode && ( !$this->schedule || !$this->wpPost || !$this->channel || !$this->channel ) )
            throw new ScheduleShareException( fsp__( 'Something went wrong! Schedule, Channel or WP post was deleted!' ) );

	    ShortCodeService::register();
        $this->setSiteLang();
        $this->cleanPostContent();
    }

	public function __destruct()
	{
		if ( ! empty( $this->wpmlLangSaved ) )
			do_action( 'wpml_switch_language', $this->wpmlLangSaved );

		WPPostThumbnail::clearCache();
	}

    /**
     * sets site wpml language to post's lang
     */
    private function setSiteLang ()
    {
        if ( empty( $this->getSchedule()->wp_post_id ) )
            return;

        $postLang = apply_filters( 'wpml_post_language_details', null, $this->getSchedule()->wp_post_id );

        if ( ! empty( $postLang['language_code'] ) )
        {
            $this->wpmlLangSaved = apply_filters( 'wpml_current_language', null );
            do_action( 'wpml_switch_language', $postLang['language_code'] );
        }
    }

    public function getSocialNetwork ()
    {
        return $this->channelSession->social_network;
    }

    /**
     * @return Schedule
     */
    public function getSchedule ()
    {
        return $this->schedule;
    }

    /**
     * @return Channel
     */
    public function getChannel ()
    {
        return $this->channel;
    }

    /**
     * @return ChannelSession
     */
    public function getChannelSession ()
    {
        return $this->channelSession;
    }

	public function getPostThumbnailID()
	{
		if( ! $this->getWPPost() )
			return null;

		if( $this->getWPPost()->post_type === 'attachment' )
			return $this->getWPPost()->ID;

		return get_post_thumbnail_id( $this->getWPPost()->ID );
	}

	public function getPostAllAttachedImagesID() : array
	{
		if( ! $this->getWPPost() )
			return [];

		$allImages = [];

		$thumb = get_post_thumbnail_id( $this->getWPPost()->ID );

		if( ! empty( $thumb ) )
			$allImages[] = $thumb;

		$wpPostType = $this->getWPPost()->post_type;
		if ( ( $wpPostType === 'product' || $wpPostType === 'product_variation' ) && function_exists( 'wc_get_product' ) )
		{
			$product   = wc_get_product( $this->getWPPost()->ID );
			$productImages = $product->get_gallery_image_ids();
			if( ! empty( $productImages ) )
			{
				$allImages = array_merge( $allImages, $productImages );
			}
		}
		else
		{
			$allAttachedImages = get_attached_media( 'image', $this->getWPPost()->ID );

			foreach ( $allAttachedImages as $attachedImage )
			{
				if ( isset( $attachedImage->ID ) )
				{
					$allImages[] = $attachedImage->ID;
				}
			}

			preg_match_all( '/<img.*?data-id="(\d+)"|wp-image-(\d+)/', $this->getWPPost()->post_content, $allWpImages );

			$allWpImages = array_merge( $allWpImages[ 1 ] ?? [], $allWpImages[ 2 ] ?? [] );

			foreach ( $allWpImages as $wpImage )
			{
				$allImages[] = $wpImage;
			}
		}

		return array_unique( $allImages );
	}

	public function getPostOriginalUrl(): string
	{
		if( is_null( $this->postOriginalUrl ) )
		{
			$this->postOriginalUrl = $this->getWPPost() ? get_permalink( $this->getWPPost()->ID ) : '#';
		}

		return $this->postOriginalUrl;
	}

	public function getPostCustomUrl(): string
	{
		if( is_null( $this->postCustomUrl ) )
		{
			$useCustomUrl = (bool)Settings::get('use_custom_url', false);
			if( ! $useCustomUrl )
			{
				$this->postCustomUrl = $this->getPostOriginalUrl();

				$divider = str_contains( $this->postCustomUrl, '?' ) ? '&' : '?';
				$sidParameter = $divider . 'fsp_sid=' . $this->schedule->id;

				$this->postCustomUrl .= $sidParameter;
			}
			else
			{
				$customUrl    = Settings::get('custom_url', '{post_url type="original"}');
				$queryParams  = Settings::get('query_params', ['fsp_sid' => '{schedule_id}']);

				add_filter( 'fsp_add_short_code', [$this, 'infinityLoopPrevent'], 99);

				$customUrl = $this->replaceShortCodes( $customUrl );

				if( filter_var( $customUrl, FILTER_VALIDATE_URL ) === false )
				{
					$this->postCustomUrl = $this->getPostOriginalUrl();
				}

				$params = '';

				if( !empty( $queryParams ) )
				{
					$params = [];

					foreach ($queryParams as $k => $v)
					{
						$params[] = $k . '=' . $v;
					}

					$params = implode( '&', $params );
					$params = $this->replaceShortCodes( $params );
					$params = str_replace( ' ', '%20', $params );
				}

				$hasQuery = strpos($customUrl, '?') !== FALSE;

				if( ! empty( $params ) )
				{
					$prefix = $hasQuery ? '&' : '?';

					$customUrl = $customUrl . $prefix . $params;
				}

				remove_filter( 'fsp_add_short_code', [$this, 'infinityLoopPrevent'], 99);

				$this->postCustomUrl = $customUrl;
			}
		}

		return $this->postCustomUrl;
	}

	public function infinityLoopPrevent( $shortCodes )
	{
		$shortCodes['post_url'] = function ( ScheduleObject $scheduleObj, $props = [] )
		{
			return $scheduleObj->getPostOriginalUrl();
		};

		$shortCodes['post_short_url'] = $shortCodes['post_url'];

		return $shortCodes;
	}

	public function getPostShortUrl(): string
	{
		if( is_null( $this->postShortUrl ) )
		{
			if ( $this->readOnlyMode || ! Settings::get( 'use_url_shortener', false ) )
			{
				$this->postShortUrl = $this->getPostCustomUrl();
			}
			else
			{
				$service = Settings::get( 'shortener_service', '' );

				$this->postShortUrl = UrlShortenerService::short( $this->getPostCustomUrl(), $service );
			}
		}

		return $this->postShortUrl;
	}

	public function getPostLink (): ?string
	{
		return $this->getPostShortUrl();
	}

	public function getWPPost()
	{
		return $this->wpPost;
	}

	private function cleanPostContent() : void
	{
		if( ! $this->getWPPost() )
			return;

		$this->wpPost->post_content = str_replace( '<br>', "\n", $this->wpPost->post_content );

		/* Begin: Removing builder short codes */

		$this->wpPost->post_content = str_replace( [
			'[[',
			']]',
		], [
			'&#91;&#91;',
			'&#93;&#93;',
		], $this->wpPost->post_content );

		$this->wpPost->post_content = preg_replace( [ '/\[(.+)]/', "/<!--(.*?)-->\r*\n?/" ], '', $this->wpPost->post_content );

		$this->wpPost->post_content = str_replace( [
			'&#91;&#91;',
			'&#93;&#93;',
		], [
			'[[',
			']]',
		], $this->wpPost->post_content );

		/* End: Removing builder short codes */

		if ( Settings::get( 'replace_wp_shortcodes', 'off' ) === 'on' )
		{
			$this->wpPost->post_content = do_shortcode( $this->wpPost->post_content );
		}
		else if ( Settings::get( 'replace_wp_shortcodes', 'off' ) === 'del' )
		{
			$this->wpPost->post_content = strip_shortcodes( $this->wpPost->post_content );
			//remove Divi shortcodes
			$this->wpPost->post_content = preg_replace( '/\[\/?et_pb.*?]/', '', $this->wpPost->post_content );
		}

		if ( Settings::get( 'multiple_newlines_to_single', false ) )
		{
			$this->wpPost->post_content = preg_replace( "/\n\s*\n\s*/", "\n\n", $this->wpPost->post_content );
		}
	}

	public function getWpPostTerms ( ?string $taxonomies = null ) :array
	{
		if( ! $this->getWPPost() )
			return [];

		$taxes = empty( $taxonomies ) ? get_post_taxonomies( $this->getWPPost()->ID ) : explode(',', $taxonomies);

		$terms = [];

		$postIdToGetTerms = get_post_type( $this->getWPPost()->ID ) === 'product_variation' ? $this->getWPPost()->post_parent : $this->getWPPost()->ID;

		foreach ( $taxes as $tax )
		{
			$tax_terms = wp_get_post_terms( $postIdToGetTerms, $tax, [ 'fields' => 'all' ] );

			if ( is_array( $tax_terms ) )
				$terms = array_merge( $terms, $tax_terms );
		}

		return array_map( function ( $el )
		{
			$el = (array)$el;

			$el['name'] = htmlspecialchars_decode( $el['name'] );
			$el['name'] = preg_replace( '/[!@#$%^*()=+{}\[\]\'\",>\/?;:]/', '', $el['name'] );
			$el['name'] = preg_replace( '/_+/', '_', $el['name'] );

			return $el;
		}, $terms );
	}

	public function replaceShortCodes(?string $message): string
	{
		$message = $message ?? '';

		$regexMatched = preg_match_all('/(\{[a-z_]+(?:\s+[a-z_]+="[^\"]+")*?})/', $message, $matches);

		if( empty( $regexMatched ) || empty( $matches[1] ) )
			return $message;

		$shortCodes = $matches[1];

		$shortCodes = array_unique( $shortCodes );

		$shortCodeCallbacks = apply_filters('fsp_add_short_code', [], 10, 1);

		$replaces = [];

		foreach ( $shortCodes as $shortCode )
		{
			preg_match('/{([a-z_]+)/', $shortCode, $codeName);

			if( empty( $codeName[1] ) )
				continue;

			preg_match_all('/(\s+[a-z_]+=".+?")/', $shortCode, $properties);
			$props = [];

			if( ! empty($properties[1]) )
			{
				foreach ($properties[1] as $property)
				{
					[$propKey, $propVal] = explode('=', $property);
					$props[trim($propKey)] = trim($propVal, '"');
				}
			}

			if( ! isset( $shortCodeCallbacks[$codeName[1]] ) || ! is_callable( $shortCodeCallbacks[$codeName[1]] ) )
				continue;

			$replaces[$shortCode] = $shortCodeCallbacks[$codeName[1]]($this, $props);
		}

		if( ! empty( $replaces ) )
		{
			$message = str_replace(array_keys($replaces), array_values($replaces), $message);
		}

		$message = html_entity_decode( $message, ENT_QUOTES );
		$message = str_replace( [ '&nbsp;', "\r" ], [ ' ', '' ], $message );

		return Helper::spintax( $message );
	}

}