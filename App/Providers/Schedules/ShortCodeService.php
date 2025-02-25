<?php

namespace FSPoster\App\Providers\Schedules;

use FSPoster\App\Providers\Helpers\Helper;
use FSPoster\App\Providers\SocialNetwork\SocialNetworkAddon;


class ShortCodeService
{
    private function __construct () {}

    public static function register ()
    {
        add_filter( 'fsp_add_short_code', function ( $shortCodes )
        {
            $sc = new ShortCodeService();

            $shortCodes['post_id']                 = [ $sc, 'postId' ];
            $shortCodes['post_title']              = [ $sc, 'title' ];
            $shortCodes['post_content']            = [ $sc, 'postContent' ];
            $shortCodes['product_description']     = [ $sc, 'productDescription' ];
            $shortCodes['post_excerpt']            = [ $sc, 'postExcerpt' ];
            $shortCodes['post_author']             = [ $sc, 'postAuthor' ];
            $shortCodes['post_author_username']    = [ $sc, 'postAuthorUserName' ];
            $shortCodes['post_featured_image_url'] = [ $sc, 'postFeaturedImageUrl' ];
            $shortCodes['post_url']                = [ $sc, 'postUrl' ];
            $shortCodes['post_short_url']          = [ $sc, 'postShortUrl' ];
            $shortCodes['product_regular_price']   = [ $sc, 'productPriceRegular' ];
            $shortCodes['product_sale_price']      = [ $sc, 'productPriceSale' ];
            $shortCodes['product_current_price']   = [ $sc, 'productPriceCurrent' ];
            $shortCodes['uniq_id']                 = [ $sc, 'uniqueId' ];
            $shortCodes['hashtags']                = [ $sc, 'hashtags' ];
            $shortCodes['hashtags_categories']     = [ $sc, 'hashtagsCategories' ];
            $shortCodes['hashtags_tags']           = [ $sc, 'hashtagsTags' ];
            $shortCodes['site_name']               = [ $sc, 'siteName' ];
            $shortCodes['site_url']                = [ $sc, 'siteUrl' ];
            $shortCodes['social_network_name']     = [ $sc, 'socialNetworkName' ];
            $shortCodes['social_network_slug']     = [ $sc, 'socialNetworkSlug' ];
            $shortCodes['post_slug']               = [ $sc, 'postSlug' ];
            $shortCodes['schedule_id']             = [ $sc, 'scheduleId' ];
            $shortCodes['customfield']             = [ $sc, 'customField' ];

            return $shortCodes;
        }, 10, 1 );
    }

    public function postId ( ScheduleObject $scheduleObj, $props = [] ): int
    {
        return $scheduleObj->getWPPost()->ID ?? 0;
    }

    public function title ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        $title = $scheduleObj->getWPPost()->post_title ?? 'Deleted';

        if ( isset( $props[ 'ucfirst' ] ) && $props[ 'ucfirst' ] )
        {
            $title = ucfirst( mb_strtolower( $title ) );
        }

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $title = rawurlencode( $title );
        }

        return $title;
    }

    public function postContent ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        if ( empty( $props[ 'limit' ] ) || !is_numeric( $props[ 'limit' ] ) )
        {
            $content = $scheduleObj->getWPPost()->post_content ?? 'deleted';
        } else
        {
            $content = html_entity_decode( Helper::cutText( strip_tags( $scheduleObj->getWPPost()->post_content ?? 'deleted' ), $props[ 'limit' ] ), ENT_QUOTES );
        }

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $content = rawurlencode( $content );
        }

        return $content;
    }

    public function productDescription ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        return $this->postContent( $scheduleObj, $props );
    }

    public function postExcerpt ( ScheduleObject $scheduleObj, $props = [] ): string
    {
		if( ! $scheduleObj->getWPPost() )
			return '';

        $excerpt = html_entity_decode( get_the_excerpt( $scheduleObj->getWPPost() ) );

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $excerpt = rawurlencode( $excerpt );
        }

        return $excerpt;
    }

    public function postAuthor ( ScheduleObject $scheduleObj, $props = [] ): string
    {
		if( ! $scheduleObj->getWPPost() )
			return '';

        $author = get_the_author_meta( 'display_name', $scheduleObj->getWPPost()->post_author );

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $author = rawurlencode( $author );
        }

        return $author;
    }

    public function postAuthorUserName ( ScheduleObject $scheduleObj, $props = [] ): string
    {
		if( ! $scheduleObj->getWPPost() )
			return '';

        $userInf  = get_userdata( $scheduleObj->getWPPost()->post_author );
        $userName = $userInf->user_login ?? '-';

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $userName = rawurlencode( $userName );
        }

        return $userName;
    }

    public function postFeaturedImageUrl ( ScheduleObject $scheduleObj, $props = [] ): string
    {
		if( ! $scheduleObj->getWPPost() )
			return '';

        $mediaId = get_post_thumbnail_id( $scheduleObj->getWPPost()->ID ?? null );

        if ( empty( $mediaId ) )
        {
            $media   = get_attached_media( 'image', $scheduleObj->getWPPost()->ID ?? null );
            $first   = reset( $media );
            $mediaId = $first->ID ?? 0;
        }

        $thumb = $mediaId > 0 ? wp_get_attachment_url( $mediaId ) : '';

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $thumb = rawurlencode( $thumb );
        }

        return $thumb;
    }

    public function postUrl ( ScheduleObject $scheduleObj, $props = [] )
    {
		$urlType = $props['type'] ?? 'custom';

		if( $urlType === 'original' )
			$url = $scheduleObj->getPostOriginalUrl();
		else if( $urlType === 'short' )
			$url = $scheduleObj->getPostShortUrl();
		else
			$url = $scheduleObj->getPostCustomUrl();

        if ( ( ( $props[ 'encoded' ] ?? 'false' ) ?? 'false' ) === 'true' )
            $url = rawurlencode( $url );

        return $url;
    }

    public function postShortUrl ( ScheduleObject $scheduleObj, $props = [] )
    {
	    $url = $scheduleObj->getPostShortUrl();

	    if ( ( ( $props[ 'encoded' ] ?? 'false' ) ?? 'false' ) === 'true' )
		    $url = rawurlencode( $url );

	    return $url;
    }

    public function productPriceRegular ( ScheduleObject $scheduleObj, $props = [] )
    {
		if( ! $scheduleObj->getWPPost() )
			return '';

        $getPrice = Helper::getProductPrice( $scheduleObj->getWPPost() );

        $price = $getPrice[ 'regular' ];

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $price = rawurlencode( $price );
        }

        return $price;
    }

    public function productPriceSale ( ScheduleObject $scheduleObj, $props = [] )
    {
	    if( ! $scheduleObj->getWPPost() )
		    return '';

	    $getPrice = Helper::getProductPrice( $scheduleObj->getWPPost() );

        $price = $getPrice[ 'sale' ];

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $price = rawurlencode( $price );
        }

        return $price;
    }

    public function productPriceCurrent ( ScheduleObject $scheduleObj, $props = [] )
    {
	    if( ! $scheduleObj->getWPPost() )
		    return '';

	    $getPrice = Helper::getProductPrice( $scheduleObj->getWPPost() );

        $productRegularPrice = $getPrice[ 'regular' ];
        $productSalePrice    = $getPrice[ 'sale' ];

        $price = !empty( $productSalePrice ) ? $productSalePrice : $productRegularPrice;

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $price = rawurlencode( $price );
        }

        return $price;
    }

    public function uniqueId ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        $id = uniqid();

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $id = rawurlencode( $id );
        }

        return $id;
    }

    public function hashtags ( ScheduleObject $scheduleObj, $props = [] )
    {
        $taxonomies         = $props[ 'taxonomies' ] ?? '';
        $uppercase          = ( $props[ 'uppercase' ] ?? 'false' ) === 'true';
        $separator          = $props[ 'separator' ] ?? '';
        // $replaceWhitespaces = ( $props[ 'replace_symbols' ] ?? 'false' ) === 'true' ? '_' : '';

        $terms = array_column( $scheduleObj->getWpPostTerms( $taxonomies ), 'name' );

        $terms = array_map( function ( $el ) use ( $uppercase, $separator )
        {
            $el = preg_replace( [ '/\s+/', '/&+/', '/-+/' ], $separator, $el );

            if ( $uppercase )
                $el = mb_strtoupper( $el );

            return '#' . trim( $el, ' _' );
        }, $terms );

        $terms = implode( ' ', $terms );

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $terms = rawurlencode( $terms );
        }

        return $terms;
    }

    public function hashtagsCategories ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        return $this->hashtags( $scheduleObj, array_merge( $props, [ "taxonomies" => "category" ] ) );
    }

    public function hashtagsTags ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        return $this->hashtags( $scheduleObj, array_merge( $props, [ "taxonomies" => "post_tag" ] ) );
    }

    public function siteName ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        $name = get_bloginfo( 'name' );

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $name = rawurlencode( $name );
        }

        return $name;
    }

    public function siteUrl ( ScheduleObject $scheduleObj, $props = [] ): string
    {
        $url = site_url();

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $url = rawurlencode( $url );
        }

        return $url;
    }

    public function socialNetworkName ( ScheduleObject $scheduleObj, $props = [] )
    {
        $sn = SocialNetworkAddon::getNetworkName( $scheduleObj->getChannelSession()->social_network );

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $sn = rawurlencode( $sn );
        }

        return $sn;
    }

    public function socialNetworkSlug ( ScheduleObject $scheduleObj, $props = [] )
    {
        $sn = $scheduleObj->getChannelSession()->social_network;

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $sn = rawurlencode( $sn );
        }

        return $sn;
    }

    public function postSlug ( ScheduleObject $scheduleObj, $props = [] ): string
    {
	    if( ! $scheduleObj->getWPPost() )
		    return '';

	    $slug = $scheduleObj->getWPPost()->post_name;

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $slug = rawurlencode( $slug );
        }

        return $slug;
    }

    public function scheduleId ( ScheduleObject $scheduleObj, $props = [] )
    {
        $id = $scheduleObj->getSchedule()->id;

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $id = rawurlencode( $id );
        }

        return $id;
    }

    public function customField ( ScheduleObject $scheduleObj, $props = [] )
    {
	    $cfKey = $props['key'] ?? '';

		if( empty( $cfKey ) )
			return '';

	    $customFieldValue = get_post_meta( $scheduleObj->getWPPost()->ID, $cfKey, true );

        if ( ( $props[ 'encoded' ] ?? 'false' ) === 'true' )
        {
            $customFieldValue = rawurlencode( $customFieldValue );
        }

        return $customFieldValue;
    }

}
