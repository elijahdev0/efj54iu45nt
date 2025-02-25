<?php

namespace FSPoster\App\SocialNetworks\Facebook\Api;

class PostingData
{

	public string $edge;
	public string $ownerId;
	public ?string $posterId;
	public string $channelType;
    public string $message;
    public string $link;
	public array $uploadMedia;
	public string $firstComment;

}