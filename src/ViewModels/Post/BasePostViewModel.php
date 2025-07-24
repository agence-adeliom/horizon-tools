<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ViewModels\Post;

use Adeliom\HorizonTools\Services\PostService;

class BasePostViewModel
{
    public ?int $id = null;
    public ?int $ID = null;
    public ?string $title = null;
    public ?string $post_title = null;
    public ?string $post_excerpt = null;
    public ?int $thumbnail = null;
    public ?string $slug = null;
    public null|false|array $fields = null;
    public ?string $card = null;

    public function __construct(\WP_Post $post, bool $withFields = true)
    {
        $this->id = $post->ID;
        $this->ID = $post->ID;
        $this->title = $post->post_title;
        $this->post_title = $post->post_title;
        $this->post_excerpt = $post->post_excerpt;
        $this->slug = $post->post_name;
        $this->thumbnail = get_post_thumbnail_id($post);

        if ($withFields) {
            $this->fields = get_fields($post);
        }

        if ($card = PostService::getCardByPostType($post->post_type)) {
            $this->card = $card;
        }
    }

    public function toStdClass(): \stdClass
    {
        $array = get_object_vars($this);

        return json_decode(json_encode($array));
    }
}
