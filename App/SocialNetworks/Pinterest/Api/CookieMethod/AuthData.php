<?php

namespace FSPoster\App\SocialNetworks\Pinterest\Api\CookieMethod;

class AuthData
{

    public string $cookieSess;


	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}