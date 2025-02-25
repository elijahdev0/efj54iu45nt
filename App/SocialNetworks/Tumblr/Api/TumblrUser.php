<?php

namespace FSPoster\App\SocialNetworks\Tumblr\Api;

class TumblrUser
{
    public string $name;

    /** @var TumblrBlog[] $blogs */
    public array $blogs = [];
}