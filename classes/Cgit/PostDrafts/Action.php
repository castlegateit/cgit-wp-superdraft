<?php

namespace Cgit\PostDrafts;

use WP_Post;

/**
 * Perform actions in response to GET requests
 *
 * Defines and performs various actions related to managing draft versions of
 * posts in response to GET requests. Also provides convenient access to the
 * URLs needed to trigger those actions.
 */
class Action
{
    /**
     * Valid actions and their corresponding methods
     *
     * @var array
     */
    private $actions = [
        'create' => 'createDraft',
        'delete' => 'deleteDraft',
        'publish' => 'publishDraft',
    ];

    /**
     * Action
     *
     * @var string
     */
    private $action;

    /**
     * Post ID
     *
     * This is the ID of the original post itself, not the ID of the draft
     * version of the post.
     *
     * @var integer
     */
    private $postId = 0;

    /**
     * Action GET key
     *
     * @var string
     */
    private $actionKey = 'cgit_draft_action';

    /**
     * Post ID GET key
     *
     * @var string
     */
    private $postIdKey = 'cgit_draft_post_id';

    /**
     * Draft instance
     *
     * @var Draft
     */
    private $draft;

    /**
     * Construct
     *
     * @param integer $id
     * @return void
     */
    public function __construct($id = null)
    {
        $this->updatePostId($id);
    }

    /**
     * Set post ID based on parameter or GET request
     *
     * If a post ID has been specified as part of a GET request, set the post ID
     * to that value. Otherwise, attempt to fall back to the value of the global
     * post object.
     *
     * @return void
     */
    private function updatePostId($id = null)
    {
        global $post;

        if (!is_null($id)) {
            return $this->setPostId($id);
        }

        if (isset($_GET[$this->postIdKey]) && $_GET[$this->postIdKey]) {
            return $this->setPostId($_GET[$this->postIdKey]);
        }

        if ($post instanceof WP_Post) {
            return $this->setPostId($post->ID);
        }
    }

    /**
     * Set post ID
     *
     * @param integer $id
     * @return void
     */
    public function setPostId($id)
    {
        if ($id instanceof WP_Post) {
            $id = $id->ID;
        }

        $this->postId = (int) $id;
    }

    /**
     * Return action URL
     *
     * Return the URL that will trigger a particular, pre-defined action. If the
     * action does not exist, this will return null. If the post ID is not
     * specified, it will use the automatically detected post ID.
     *
     * @param string $action
     * @param integer $id
     * @return string
     */
    public function url($action, $id = null)
    {
        if (!array_key_exists($action, $this->actions)) {
            return;
        }

        if (is_null($id)) {
            $id = $this->postId;
        }

        return add_query_arg([
            $this->actionKey => $action,
            $this->postIdKey => $id,
        ], admin_url());
    }

    /**
     * Perform a specific action
     *
     * @param string $action
     * @return boolean
     */
    private function perform($action)
    {
        if (!$action || !array_key_exists($action, $this->actions)) {
            return false;
        }

        $method = $this->actions[$action];
        $this->$method();

        return true;
    }

    /**
     * Perform an action automatically based on a GET request
     *
     * @return void
     */
    public function init()
    {
        $this->updatePostId();

        // We cannot perform an action if there is no post ID
        if (!$this->postId) {
            return;
        }

        $this->draft = new Draft($this->postId);

        if (isset($_GET[$this->actionKey]) && $_GET[$this->actionKey]) {
            $this->action = $_GET[$this->actionKey];
        }

        $this->perform($this->action);
    }

    /**
     * Create a draft
     *
     * @return void
     */
    private function createDraft()
    {
        $this->draft->create();
        wp_redirect(get_edit_post_link($this->draft->draftId(), null));

        exit;
    }

    /**
     * Delete a draft
     *
     * @return void
     */
    private function deleteDraft()
    {
        $this->draft->delete();
    }

    /**
     * Publish a draft
     *
     * @return void
     */
    private function publishDraft()
    {
        $this->draft->publish();
    }
}
