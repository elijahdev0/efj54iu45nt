<?php

namespace FSPoster\App\SocialNetworks\Xing\Api;

class AuthData
{
    public array $cookies;
    public string $username;

    public function setFromArray ( array $data )
    {
        foreach ( $data AS $key => $value )
        {
            if( property_exists( $this, $key ) )
                $this->$key = $value;
        }
    }
}