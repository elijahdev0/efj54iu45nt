<?php

namespace FSPoster\App\SocialNetworks\Vk\Api;

class PostingData
{
	public string $ownerId;
	/* account, page, event, group */
	public string $ownerType;
    public string $message;
    public string $link;
	public array $uploadMedia;

}