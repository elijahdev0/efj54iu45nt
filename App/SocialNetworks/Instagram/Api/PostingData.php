<?php

namespace FSPoster\App\SocialNetworks\Instagram\Api;

class PostingData
{

	public string $edge;
	public string $ownerId;
    public string $message;
    public string $link;
    public array $linkConfig;
	public array $uploadMedia;
	public string $firstComment;
	public bool $pinThePost;
	public string $storyHashtag;
	public array $storyHashtagConfig;

}