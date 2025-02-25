<?php

namespace FSPoster\App\Providers\SocialNetwork;


use FSPoster\App\Providers\Core\Request;

class SocialNetworkAddon
{

	private static $socialNetworks = [];

	protected $name;
	protected $icon;
	protected $slug;
	protected $sort;


	public function getName()
	{
		return $this->name;
	}

	public function getIcon()
	{
		return $this->icon;
	}

	public function getSlug()
	{
		return $this->slug;
	}

	public function getSort()
	{
		return $this->sort;
	}

    public function getCallbackUrl() : ?string
    {
        return self_admin_url('admin.php?fs-poster-callback=true');
    }

	public function checkIsCallbackRequest () : bool
	{
		$callbackParameter = Request::get('fs-poster-callback', '', 'string', ['true']);

		return ! empty( $callbackParameter );
	}

    public static function register()
	{
		$newSN = new static();

		if( method_exists( $newSN, 'init' ) )
			$newSN->init();

		self::$socialNetworks[ $newSN->getSlug() ] = $newSN;
	}

	/**
	 * @return SocialNetworkAddon[]
	 */
	public static function getSocialNetworks()
	{
		uasort( self::$socialNetworks, function ($a, $b)
		{
			if ( $a->getSort() == $b->getSort() )
				return 0;

			return ($a->getSort() < $b->getSort()) ? -1 : 1;
		});

		return self::$socialNetworks;
	}

	/**
	 * @param $networkSlug
	 * @return SocialNetworkAddon|null
	 */
	public static function getNetwork( $networkSlug )
	{
		return self::$socialNetworks[ $networkSlug ] ?? null;
	}

	public static function getNetworkName( $networkSlug )
	{
		$network = self::getNetwork( $networkSlug );

		return $network ? $network->getName() : $networkSlug;
	}

	public static function getInstance()
	{
		$ins = new static();
		$slug = $ins->getSlug();
		unset( $ins );

		return self::getNetwork( $slug );
	}

}