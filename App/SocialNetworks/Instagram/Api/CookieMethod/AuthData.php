<?php

namespace FSPoster\App\SocialNetworks\Instagram\Api\CookieMethod;

class AuthData
{

    public array $cookies;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}