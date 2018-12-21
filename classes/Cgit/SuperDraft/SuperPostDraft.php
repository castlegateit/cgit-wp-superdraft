<?php

namespace Cgit\SuperDraft;

class SuperPostDraft
{
    /**
     * Published post that may or may not have a draft version
     *
     * @var SuperPost
     */
    private $post;

    /**
     * Draft version of a published post
     *
     * @var SuperPost
     */
    private $draft;

    /**
     * Construct
     *
     * @param mixed $object
     * @return void
     */
    public function __construct($object = null)
    {
        $this->setPostDraft($object);
    }

    /**
     * Set post and draft properties
     *
     * Provided with a post ID or object that could be a published post or a
     * draft version of a published post, find the matching post and/or draft
     * and assign them to instance properties.
     *
     * @param mixed $object
     * @return void
     */
    public function setPostDraft($object)
    {
        $unknown = new SuperPost($object);

        // Not a valid post?
        if (!$unknown->id()) {
            return;
        }

        $post_id = $unknown->meta(Plugin::POST_META_KEY);
        $draft_id = $unknown->meta(Plugin::DRAFT_META_KEY);

        // Post is a published post with a draft version
        if ($draft_id) {
            $this->post = $unknown;
            $this->draft = new SuperPost($draft_id);

            return;
        }

        // Post is a draft version of a published post
        if ($post_id) {
            $this->post = new SuperPost($post_id);
            $this->draft = $unknown;

            return;
        }

        // Post is a published post with no draft version
        $this->post = $unknown;
    }

    /**
     * Return post instance
     *
     * @return SuperPost
     */
    public function post()
    {
        return $this->post;
    }

    /**
     * Return draft instance
     *
     * @return SuperPost
     */
    public function draft()
    {
        return $this->draft;
    }

    /**
     * Create draft
     *
     * If a draft version of the published post does not exist, create a copy
     * and save the post and draft IDs as post meta.
     *
     * @return boolean
     */
    public function create()
    {
        if (!is_null($this->draft) || is_null($this->post)) {
            return false;
        }

        $post_id = $this->post->id();

        // Insert post
        $draft_id = wp_insert_post(array_merge($this->post->export(), [
            'post_status' => 'draft',
        ]));

        // Import data from published post
        $this->draft = new SuperPost($draft_id);
        $this->draft->import($this->post);

        // Establish relationship between published and draft versions
        update_post_meta($post_id, Plugin::DRAFT_META_KEY, $draft_id);
        update_post_meta($draft_id, Plugin::POST_META_KEY, $post_id);

        return true;
    }

    /**
     * Delete draft
     *
     * Delete the draft version of the post and the meta values that linked the
     * draft to its published version.
     *
     * @return boolean
     */
    public function delete()
    {
        if (is_null($this->draft)) {
            return false;
        }

        $post_id = $this->post->id();
        $draft_id = $this->draft->id();

        // Break relationship between published and draft versions
        delete_post_meta($post_id, Plugin::DRAFT_META_KEY);
        delete_post_meta($draft_id, Plugin::POST_META_KEY);

        // Delete post
        wp_delete_post($draft_id);

        // Delete instance
        $this->draft = null;

        return true;
    }

    /**
     * Publish draft
     *
     * Merge the content from the draft into the published version, then delete
     * the draft version.
     *
     * @return boolean
     */
    public function publish()
    {
        if (is_null($this->draft)) {
            return false;
        }

        // Copy content from draft to published version and delete draft
        $this->post->import($this->draft);
        $this->delete();

        return true;
    }
}
