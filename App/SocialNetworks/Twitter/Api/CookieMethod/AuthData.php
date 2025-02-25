<?php

namespace FSPoster\App\SocialNetworks\Twitter\Api\CookieMethod;

class AuthData
{

    public string $authToken;



	public function setFromArray ( array $data )
	{
		foreach ( $data AS $key => $value )
		{
			if( property_exists( $this, $key ) )
				$this->$key = $value;
		}
	}

}