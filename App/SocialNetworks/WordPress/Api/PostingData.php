<?php

namespace FSPoster\App\SocialNetworks\WordPress\Api;

class PostingData
{

    public string $title;
    public string $excerpt;
    public string $message;
    public string $postStatus;
    public array $tags;
    public array $categories;
    public array $uploadMedia;
	public string $postType;
	public bool $preservePostType;

}