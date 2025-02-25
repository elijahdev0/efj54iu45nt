<?php

namespace FSPoster\App\Providers\Helpers;

use WP_Post;


trait WPHelper
{
	private static ?int $originalBlogId = null;

	public static function setBlogId ( int $new_blog_id )
	{
		if ( ! is_multisite() )
			return;

		if ( is_null( self::$originalBlogId ) )
			self::$originalBlogId = self::getBlogId();

		switch_to_blog( $new_blog_id );
	}

	public static function getBlogId () : int
    {
		return get_current_blog_id();
	}

	public static function resetBlogId ()
	{
		if ( ! is_multisite() )
			return;

		if ( ! is_null( self::$originalBlogId ) )
			switch_to_blog( self::$originalBlogId );
	}

	public static function getBlogs () : array
    {
		if ( ! is_multisite() )
			return [ 1 ];

		$sites   = get_sites();
		$siteIDs = [];

		foreach ( $sites as $site )
		{
			$siteIDs[] = $site->blog_id;
		}

		return $siteIDs;
	}

    public static function getPostTypes(): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');

        $postTypesFiltered = [];

        foreach ($postTypes as $type => $object)
        {
            if ($type === 'fsp_post')
            {
                continue;
            }

            $postTypesFiltered[] = [
                'label' => $object->label,
                'value' => $type
            ];
        }

        return $postTypesFiltered;
    }

	public static function getProductPrice ( WP_Post $productInf )
	{
		$productRegularPrice = '';
		$productSalePrice    = '';
		$productId           = $productInf->post_type === 'product_variation' ? $productInf->post_parent : $productInf->ID;

		if ( ( $productInf->post_type === 'product' || $productInf->post_type === 'product_variation' ) && function_exists( 'wc_get_product' ) )
		{
			$product = wc_get_product( $productId );

			if ( $product->is_type( 'variable' ) )
			{
				$variations = wc_products_array_orderby(
					$product->get_available_variations( 'objects' ),
					'price',
					'asc'
				);

				$variations_in_stock = [];

				foreach ( $variations as $variation )
				{
					if ( $variation->is_in_stock() )
					{
						$variations_in_stock[] = $variation;
					}
				}

				if ( empty( $variations_in_stock ) )
				{
					$variable_product = empty( $variations ) ? $product : $variations[ 0 ];
				}
				else
				{
					$variable_product = $variations_in_stock[ 0 ];
				}

				$productRegularPrice = $variable_product->get_regular_price();
				$productSalePrice    = $variable_product->get_sale_price();
			}
			else //else if ( $product->is_type( 'simple' ) )
			{
				$productRegularPrice = $product->get_regular_price();
				$productSalePrice    = $product->get_sale_price();
			}
		}

		if ( empty( $productRegularPrice ) && $productSalePrice > $productRegularPrice )
		{
			$productRegularPrice = $productSalePrice;
		}

		$productRegularPrice = self::formatProductPrice( $productRegularPrice );
		$productSalePrice    = self::formatProductPrice( $productSalePrice );

		return [
			'regular' => $productRegularPrice,
			'sale'    => $productSalePrice
		];
	}

	public static function formatProductPrice ( $price ) : string
    {
		if ( $price === '' || is_null( $price ) || ! function_exists( 'wc_get_price_decimal_separator' ) || ! function_exists( 'wc_get_price_thousand_separator' ) || ! function_exists( 'wc_get_price_decimals' ) )
			return $price;

		return number_format( (float) $price, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
	}
}
