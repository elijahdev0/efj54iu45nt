<?php

namespace FSPoster\App\SocialNetworks\Threads\Api;

class ThreadsClientAuthData
{
    public string $clientId;

    public string $clientSecret;

    public string $userAccessToken;

    public ?int $userAccessTokenExpiresAt;

    public string $userId;

    public function setFromArray ( array $data )
    {
        foreach ( $data AS $key => $value )
        {
            if( property_exists( $this, $key ) )
                $this->$key = $value;
        }
    }

    public function __construct(array $data = [])
    {
        $this->setFromArray($data);
    }
}
