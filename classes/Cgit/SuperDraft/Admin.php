<?php

namespace Cgit\SuperDraft;

class Admin
{
    /**
     * Draft notice parameters
     *
     * @var array
     */
    private $draftAdminNoticeArgs = [];

    /**
     * Construct
     *
     * @return void
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'detectPerformAction']);
        add_action('admin_head', [$this, 'insertStyles']);
        add_action('transition_post_status', [$this, 'preventDraftPublish'], 10, 3);
        add_action('wp_trash_post', [$this, 'deletePostDraftMeta']);
        add_action('delete_post', [$this, 'deletePostDraftMeta']);
        add_action('do_meta_boxes', [$this, 'replaceDraftPublishMetaBox'], 20);
        add_action('post_submitbox_misc_actions', [$this, 'insertPostDraftMetaBoxLinks']);
        add_action('current_screen', [$this, 'insertDraftAdminNotice']);
        add_action('edit_form_before_permalink', [$this, 'insertDraftEditPermalinkSiblingElement']);

        add_filter('page_row_actions', [$this, 'insertPostRowAction'], 10, 2);
        add_filter('post_row_actions', [$this, 'insertPostRowAction'], 10, 2);
        add_filter('display_post_states', [$this, 'insertPostState'], 10, 2);
    }

    /**
     * Detect and perform action
     *
     * Automatically perform an action in response to a valid GET request. To be
     * run on admin_init.
     *
     * @return void
     */
    public function detectPerformAction()
    {
        $action = new Action;
        $action->detectPerform();
    }

    /**
     * Insert custom styles
     *
     * @return void
     */
    public function insertStyles()
    {
        echo $this->view('inline_styles');
    }

    /**
     * Prevent drafts from being published
     *
     * If a draft version of a published post is somehow published, make it a
     * draft again and show an error message. To be run on
     * transition_post_status.
     *
     * @return void
     */
    public function preventDraftPublish($new_status, $old_status, $post)
    {
        if ($new_status == 'draft' || !$this->isDraft($post)) {
            return;
        }

        // Set post status back to draft
        wp_update_post([
            'ID' => $post->ID,
            'post_status' => 'draft',
        ]);

        // Assemble URL of "edit draft" page
        $url = add_query_arg([
            'post_type' => $post->post_type,
        ], admin_url('edit.php'));

        // Show a helpful error message
        wp_die($this->view('draft_publish_message', [
            'url' => $url,
        ]));
    }

    /**
     * Break link between published and draft versions of a post
     *
     * Delete the meta keys that link the published and draft versions of a post
     * to break the link between them. To be run on wp_trash_post and
     * delete_post.
     *
     * @param integer $id
     * @return void
     */
    public function deletePostDraftMeta($id)
    {
        $combo = new SuperPostDraft($id);
        $combo->delete();
    }

    /**
     * Replace draft publish meta box
     *
     * Remove the meta box with the standard publish controls and replace it
     * with a custom one with suitable controls for managing draft versions.
     *
     * @return void
     */
    public function replaceDraftPublishMetaBox()
    {
        global $post;

        $types = get_post_types();

        if (!$this->isDraft($post)) {
            return;
        }

        foreach ($types as $type) {
            $this->removeDefaultPublishMetaBox($type);
            $this->insertCustomPublishMetaBox($type);
        }
    }

    /**
     * Remove default publish meta box
     *
     * @param string $type
     * @return void
     */
    private function removeDefaultPublishMetaBox($type)
    {
        remove_meta_box('submitdiv', $type, 'side');
    }

    /**
     * Insert custom publish meta box
     *
     * @param string $type
     * @return void
     */
    private function insertCustomPublishMetaBox($type)
    {
        if (!$this->draftable($type)) {
            return;
        }

        add_meta_box('cgit-superdraft', 'Super Draft',
            [$this, 'renderCustomPublishMetaBox'], $type, 'side', 'high');
    }

    /**
     * Render custom meta box for drafts
     *
     * @return void
     */
    public function renderCustomPublishMetaBox()
    {
        global $post;

        $combo = new SuperPostDraft($post);
        $action = new Action($post);

        echo $this->view('draft_meta_box', [
            'edit_url' => $combo->post()->edit(),
            'publish_url' => $action->url('publish'),
            'delete_url' => $action->url('delete'),
        ]);
    }

    /**
     * Show draft links in publish meta box on published post
     *
     * @param WP_Post $post
     * @return void
     */
    public function insertPostDraftMetaBoxLinks($post)
    {
        if ($this->isDraft($post) || !$this->draftable($post)) {
            return;
        }

        $combo = new SuperPostDraft($post);
        $action = new Action($post);
        $label = 'Create';
        $url = $action->url('create');

        if ($this->hasDraft($post)) {
            $label = 'Edit';
            $url = $combo->draft()->edit();
        }

        echo $this->view('draft_meta_box_edit', [
            'label' => $label,
            'url' => $url,
        ]);
    }

    /**
     * Display notice on published pages with drafts
     *
     * @param WP_Screen $screen
     * @return void
     */
    public function insertDraftAdminNotice($screen)
    {
        $id = Action::value('post');

        // Wrong screen? No draft? Nope.
        if ($screen->base != 'post' || !$id || !$this->hasDraft($id)) {
            return;
        }

        $combo = new SuperPostDraft($id);
        $action = new Action($id);

        $this->draftAdminNoticeArgs = [
            'edit_url' => $combo->draft()->edit(),
            'publish_url' => $action->url('publish'),
            'delete_url' => $action->url('delete'),
        ];

        add_action('admin_notices', [$this, 'renderDraftAdminNotice']);
    }

    /**
     * Render notice on published pages with drafts
     *
     * @return void
     */
    public function renderDraftAdminNotice()
    {
        echo $this->view('draft_notice', $this->draftAdminNoticeArgs);
    }

    /**
     * Insert emtpy element above "edit permalink" meta box
     *
     * Insert an empty element immediately before the "edit permalink" meta box
     * so we can hide it with CSS on draft versions.
     *
     * @param WP_Post $post
     * @return void
     */
    public function insertDraftEditPermalinkSiblingElement($post)
    {
        if (!$this->isDraft($post)) {
            return;
        }

        echo $this->view('draft_edit_permalink');
    }

    /**
     * Insert post row action
     *
     * If the post is a draft, remove the view link and add a link to the
     * published version instead. If the post has a draft, link to the draft.
     * Otherwise, link to the "create a draft" URL.
     *
     * @param array $links
     * @param WP_Post $post
     * @return array
     */
    public function insertPostRowAction($links, $post)
    {
        if (!$this->draftable($post)) {
            return $links;
        }

        $action = new Action($post);
        $combo = new SuperPostDraft($post);
        $key = 'superdraft';

        // Create new draft
        $url = $action->url('create');
        $label = 'Create Super Draft';

        // Post is a draft? Link to edit published version.
        if ($this->isDraft($post)) {
            $url = $combo->post()->edit();
            $label = 'Super Draft of #' . $combo->post()->id();

            // Remove "view" link
            unset($links['inline hide-if-no-js']);
        }

        // Post already has a draft? Link to edit draft version.
        elseif ($this->hasDraft($post)) {
            $url = $combo->draft()->edit();
            $label = 'Edit Super Draft';
        }

        $links[$key] = '<a href="' . $url . '">' . $label . '</a>';

        return $links;
    }

    /**
     * Insert post state label
     *
     * Replace the "Draft" state label with a custom label for draft versions of
     * published posts in the main post list.
     *
     * @param array $states
     * @param WP_Post $post
     * @return array
     */
    public function insertPostState($states, $post)
    {
        if (!$this->isDraft($post)) {
            return $states;
        }

        return ['Super Draft'];
    }

    /**
     * Return meta value
     *
     * @param mixed $post
     * @param string $key
     * @return mixed
     */
    private function meta($post, $key)
    {
        return (new SuperPost($post))->meta($key);
    }

    /**
     * Return ID of published version of a draft post
     *
     * @param mixed $post
     * @return integer
     */
    private function postId($post)
    {
        return (int) $this->meta($post, Plugin::POST_META_KEY);
    }

    /**
     * Return ID of draft version of published post
     *
     * @param mixed $post
     * @return integer
     */
    private function draftId($post)
    {
        return (int) $this->meta($post, Plugin::DRAFT_META_KEY);
    }

    /**
     * Is the post a published post with a draft version?
     *
     * @param mixed $post
     * @return boolean
     */
    private function hasDraft($post)
    {
        return (bool) $this->draftId($post);
    }

    /**
     * Is the post a draft version of a published post?
     *
     * @param mixed $post
     * @return boolean
     */
    private function isDraft($post)
    {
        return (bool) $this->postId($post);
    }

    /**
     * Load view
     *
     * @param string $view
     * @param mixed $args
     * @return string
     */
    private function view($view, $args = [])
    {
        $args = (object) $args;
        $extension = '.php';

        if (strpos($view, '/') !== 0) {
            $view = dirname(CGIT_SUPERDRAFT_PLUGIN) . '/views/' . $view;
        }

        if (substr($view, -strlen($extension)) != $extension) {
            $view = rtrim($view, '.') . $extension;
        }

        ob_start();
        include $view;
        return ob_get_clean();
    }

    /**
     * Can we create a draft of this post type?
     *
     * @param mixed $type
     * @return boolean
     */
    private function draftable($type = null) {
        $cap = apply_filters('cgit_superdraft_capability', 'edit_posts');
        $types = apply_filters('cgit_superdraft_types', get_post_types());

        if (!current_user_can($cap)) {
            return false;
        }

        if (is_null($type)) {
            return true;
        }

        if (is_a($type, 'WP_Post') || is_int($type)) {
            $post = get_post($type);

            if (!$post) {
                return;
            }

            $type = $post->post_type;
        }

        return in_array($type, $types);
    }
}
