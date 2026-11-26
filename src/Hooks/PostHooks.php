<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Illuminate\Support\Facades\Config;

class PostHooks extends AbstractHook
{
    private ?string $prefix = null;

    public function init(): void
    {
        if (Config::get('posts.prefix')) {
            $this->prefix = Config::get('posts.prefix');
            add_action('generate_rewrite_rules', [$this, 'add_rewrite_rules']);
            add_filter('post_link', [$this, 'change_blog_links'], 1, 3);
        }
    }

    public function add_rewrite_rules($wp_rewrite)
    {
        $new_rules = [
            $this->prefix . '/(.+?)/?$' => 'index.php?post_type=post&name=' . $wp_rewrite->preg_index(1),
        ];

        $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
    }

    public function change_blog_links($post_link, $id = 0, bool $leaveName = false)
    {
        $post = get_post($id);

        if (is_object($post) && $post->post_type == 'post') {
            // Article pas encore publié (brouillon, en attente, planifié…) : WordPress lui donne le lien ?p=<ID>,
            // seul lien valable tant qu'il n'a pas de post_name définitif. Le préfixe donnerait /<prefix>// et
            // l'aperçu tomberait en 404. Le permalien modifiable de l'éditeur ($leaveName) garde le préfixe.
            if (!$leaveName && wp_force_plain_post_permalink($post)) {
                return $post_link;
            }

            return home_url(sprintf('/%s/%s/', $this->prefix, $leaveName ? '%postname%' : $post->post_name));
        }

        return $post_link;
    }
}
