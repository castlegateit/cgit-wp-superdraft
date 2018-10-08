<?php

namespace Cgit\PostDrafts;

use WP_Post;

/**
 * Create, publish, and delete drafts
 *
 * This class can be used to create, publish, and remove draft versions of
 * published posts, including meta data and taxonomies. Provided with an ID that
 * might be a post or a draft, it will work out and provide access to the
 * corresponding draft and post IDs.
 */
class Draft
{
    /**
     * Published post ID
     *
     * @var integer
     */
    private $postId = 0;

    /**
     * Draft ID
     *
     * @var integer
     */
    private $draftId = 0;

    /**
     * Published post meta key
     *
     * @var string
     */
    private $postMetaKey = 'cgit_draft_parent_id';

    /**
     * Draft meta key
     *
     * @var string
     */
    private $draftMetaKey = 'cgit_draft_id';

    /**
     * Construct
     *
     * @param mixed $post_id
     * @return void
     */
    public function __construct($post)
    {
        $this->sanitizePost($post);
    }

    /**
     * Attempt to determine post and draft ID from post
     *
     * @param mixed $post
     * @return void
     */
    private function sanitizePost($post)
    {
        if ($post instanceof WP_Post) {
            $post = (int) $post->ID;
        }

        if (!is_int($post)) {
            return trigger_error('Post ID must be an integer.');
        }

        $draft = get_post_meta($post, $this->draftMetaKey, true);
        $parent = get_post_meta($post, $this->postMetaKey, true);

        // This post has a draft?
        if ($draft) {
            $this->postId = $post;
            $this->draftId = $draft;

            return;
        }

        // This post is a draft?
        if ($parent) {
            $this->postId = $parent;
            $this->draftId = $post;

            return;
        }

        // This post does not have a draft
        $this->postId = $post;
    }

    /**
     * Create draft
     *
     * If a draft post does not already exist, create a new draft post. Return
     * true if a new post is created and false if it is not.
     *
     * @return boolean
     */
    public function create()
    {
        if ($this->draftId) {
            return false;
        }

        $post = get_post($this->postId);

        $this->draftId = wp_insert_post([
            'post_author' => $post->post_author,
            'post_content' => $post->post_content,
            'post_title' => $post->post_title,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_password' => $post->post_password,
            'post_parent' => $post->ID,
            'menu_order' => $post->menu_order,
            'post_type' => $post->post_type,
        ]);

        // Merge terms and meta into draft
        $this->mergeTerms($this->postId, $this->draftId);
        $this->mergeMeta($this->postId, $this->draftId);

        // Record relationship between post and draft
        update_post_meta($this->postId, $this->draftMetaKey, $this->draftId);
        update_post_meta($this->draftId, $this->postMetaKey, $this->postId);

        return true;
    }

    /**
     * Publish draft
     *
     * Merge the content, terms, and meta of the draft into the published
     * version of the post and delete the draft.
     *
     * @return boolean
     */
    public function publish()
    {
        if (!$this->draftId) {
            return false;
        }

        $draft = get_post($this->draftId);

        wp_update_post([
            'ID' => $this->postId,
            'post_author' => $draft->post_author,
            'post_content' => $draft->post_content,
            'post_title' => $draft->post_title,
            'post_excerpt' => $draft->post_excerpt,
            'comment_status' => $draft->comment_status,
            'ping_status' => $draft->ping_status,
            'post_password' => $draft->post_password,
            'menu_order' => $draft->menu_order,
            'post_type' => $draft->post_type,
        ]);

        // Merge terms and meta into published post
        $this->mergeTerms($this->draftId, $this->postId);
        $this->mergeMeta($this->draftId, $this->postId);

        // Delete draft version
        $this->delete();

        return true;
    }

    /**
     * Delete draft
     *
     * Return true if the draft and its meta data have been deleted and false if
     * there is no draft to delete.
     *
     * @return boolean
     */
    public function delete()
    {
        if (!$this->draftId) {
            return false;
        }

        // Delete meta
        delete_post_meta($this->draftId, $this->postMetaKey);
        delete_post_meta($this->postId, $this->draftMetaKey);

        // Delete post
        wp_delete_post($this->draftId);

        return true;
    }

    /**
     * Merge terms from one post into another
     *
     * @param integer $source
     * @param integer $destination
     * @return void
     */
    private function mergeTerms($source, $destination)
    {
        $taxons = get_object_taxonomies(get_post_type($source));

        foreach ($taxons as $taxon) {
            $terms = wp_get_object_terms($source, $taxon, [
                'fields' => 'slugs',
            ]);

            wp_set_object_terms($destination, $terms, $taxon, false);
        }
    }

    /**
     * Merge meta from one post into another
     *
     * Copies all meta values from the source post to the destination post,
     * excluding the draft meta values used by this plugin. It then deletes any
     * meta keys from the destination that do not occur in the source.
     *
     * @param integer $source
     * @param integer $destination
     * @return void
     */
    private function mergeMeta($source, $destination)
    {
        $exclude = [
            $this->postMetaKey,
            $this->draftMetaKey,
        ];

        $meta1 = array_diff_key(get_post_meta($source), $exclude);
        $meta2 = array_diff_key(get_post_meta($destination), $meta1, $exclude);

        // Copy data from source to destination
        foreach ($meta1 as $key => $values) {
            foreach ($values as $value) {
                update_post_meta($destination, $key, $value);
            }
        }

        // Delete data from destination when keys do not exist in source
        foreach ($meta2 as $key => $values) {
            delete_post_meta($destination, $key);
        }
    }

    /**
     * Return post ID
     *
     * @return integer
     */
    public function postId()
    {
        return $this->postId;
    }

    /**
     * Return draft ID
     *
     * @return integer
     */
    public function draftId()
    {
        return $this->draftId;
    }

    /**
     * Return post edit URL
     *
     * @return string
     */
    public function editPostUrl()
    {
        return get_edit_post_link($this->postId);
    }

    /**
     * Return draft edit URL
     *
     * @return string
     */
    public function editDraftUrl()
    {
        return get_edit_post_link($this->draftId);
    }
}
