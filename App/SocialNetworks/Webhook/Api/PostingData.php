<?php

namespace FSPoster\App\SocialNetworks\Webhook\Api;

class PostingData
{

	public string $url;
	public string $method;
	public array $headers;
    public array $formData;
    public string $jsonData;
    public string $contentType;

}