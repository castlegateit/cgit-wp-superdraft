<?php

namespace Cgit\PostDrafts;

use WP_Post;

/**
 * Amend WP admin interface to work with drafts
 *
 * This class makes various amendments to the WP admin interface so that we can
 * work safely with drafts. This includes preventing multiple drafts from being
 * created; providing controls for creating, publishing, and deleting drafts;
 * and enqueueing styles.
 */
class Admin
{
    /**
     * Initialize admin stuff
     *
     * @return void
     */
    public function init()
    {
        // Actions
        add_action('transition_post_status', [$this, 'preventDraftPublish'], 10, 3);
        add_action('delete_post', [$this, 'deletePostDraft']);
        add_action('wp_trash_post', [$this, 'deletePostDraft']);
        add_action('current_screen', [$this, 'showDraftNotification']);
        add_action('do_meta_boxes', [$this, 'removeDraftMetaBox'], 20);
        add_action('add_meta_boxes', [$this, 'insertDraftMetaBox']);
        add_action('post_submitbox_misc_actions', [$this, 'insertPostMetaBoxActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // Filters
        add_filter('post_row_actions', [$this, 'insertPostRowAction'], 10, 2);
        add_filter('page_row_actions', [$this, 'insertPostRowAction'], 10, 2);
        add_filter('admin_body_class', [$this, 'insertBodyClass']);
    }

    /**
     * Load view file
     *
     * If the file does not include a full path, assume that it is relative to
     * the plugin views directory. If the file does not include a ".php"
     * extension, add it.
     *
     * Filters:
     *
     * cgit_post_drafts_view_file
     * cgit_post_drafts_view_args
     * cgit_post_drafts_view_html
     *
     * @param string $file
     * @param array|stdClass $args
     * @return string
     */
    private function view($file, $args = [])
    {
        $prefix = 'cgit_post_drafts_view_';
        $extension = '.php';

        $file = apply_filters($prefix . 'file', $file, (object) $args);
        $args = apply_filters($prefix . 'args', (object) $args, $file);

        // Full path
        if (strpos($file, '/') !== 0) {
            $file = dirname(CGIT_POST_DRAFTS_PLUGIN) . '/views/' . $file;
        }

        // Include extension
        if (substr($file, -strlen($extension)) !== $extension) {
            $file .= $extension;
        }

        ob_start();

        include $file;

        return apply_filters($prefix . 'html', ob_get_clean(), $file, $args);
    }

    /**
     * Prevent drafts from being published
     *
     * Run on transition_post_status action. If a user tries to publish a draft,
     * make sure the post status does not change and display a message.
     *
     * @param string $new_status
     * @param string $old_status
     * @param WP_Post $post
     * @return void
     */
    public function preventDraftPublish($new_status, $old_status, $post)
    {
        // New status is draft? Post is not a draft? Nothing to do here.
        if ($new_status == 'draft' || !$this->isDraft($post)) {
            return;
        }

        // Reset status to "draft"
        wp_update_post([
            'ID' => $post->ID,
            'post_status' => 'draft',
        ]);

        // Show a helpful message
        $url = add_query_arg([
            'post_type' => $post->post_type,
        ], admin_url('edit.php'));

        wp_die($this->view('cannot_publish_draft', ['url' => $url]));
    }

    /**
     * Delete associated draft when deleting post
     *
     * Run on delete_post and wp_trash_post actions. Removes an associated draft
     * post when its "parent" post is deleted.
     *
     * @param integer $post_id
     * @return void
     */
    public function deletePostDraft($post_id)
    {
        (new Draft($post_id))->delete();
    }

    /**
     * Show notification if post has a draft
     *
     * Run on current_screen action. If the screen is a post edit screen and the
     * post has a draft version, show a notification.
     *
     * @param WP_Screen $screen
     * @return void
     */
    public function showDraftNotification($screen)
    {
        // Not a post edit screen?
        if ($screen->base != 'post' || !isset($_GET['post'])) {
            return;
        }

        $id = (int) $_GET['post'];

        // No draft version? Is a draft version?
        if (!$this->hasDraft($id) || $this->isDraft($id)) {
            return;
        }

        // Show notification with link to draft version
        add_action('admin_notices', function () use ($id) {
            echo $this->view('notice_draft_available', [
                'url' => (new Draft($id))->editDraftUrl(),
            ]);
        });
    }

    /**
     * Remove publish meta box from drafts
     *
     * Run on do_meta_boxes action, priority 20. Drafts cannot be published, so
     * we should remove the publish/status meta box from drafts.
     *
     * @return void
     */
    public function removeDraftMetaBox()
    {
        global $post;

        // Not a post?
        if (!($post instanceof WP_Post)) {
            return;
        }

        $types = get_post_types();

        // No post types? Not a draft?
        if (!$types || !$this->isDraft($post)) {
            return;
        }

        foreach ($types as $type) {
            remove_meta_box('submitdiv', $type, 'side');
        }
    }

    /**
     * Insert draft actions meta box
     *
     * Run on add_meta_boxes action. This replaces the publish meta box on
     * drafts and provides buttons for updating, publishing, and deleting the
     * draft version of the post.
     *
     * Filters:
     *
     * cgit_post_draft_meta_box_name
     * cgit_post_draft_meta_box_title
     *
     * @return void
     */
    public function insertDraftMetaBox()
    {
        global $post;

        // Not a post?
        if (!($post instanceof WP_Post)) {
            return;
        }

        $prefix = 'cgit_post_draft_meta_box_';
        $types = get_post_types();
        $action = new Action($post);

        // No post types? Not a draft?
        if (!$types || !$this->isDraft($post)) {
            return;
        }

        foreach ($types as $type) {
            $name = apply_filters($prefix . 'name', 'cgit-wp-post-drafts');
            $title = apply_filters($prefix . 'title', 'Draft');

            // Stuff we need in the view
            $args = [
                'edit_url' => (new Draft($post))->editPostUrl(),
                'publish_url' => $action->url('publish'),
                'delete_url' => $action->url('delete'),
            ];

            // Show the meta box
            add_meta_box($name, $title, function () use ($args) {
                echo $this->view('draft_meta_box', $args);
            }, $type, 'side', 'high');
        }
    }

    /**
     * Insert draft actions in post publish meta box
     *
     * Run on post_submitbox_misc_actions action. If the user is allowed to
     * create drafts of this post, add relevant links to the publish meta box.
     *
     * @param WP_Post $post
     * @return void
     */
    public function insertPostMetaBoxActions($post)
    {
        if (!$this->permitted($post) || $this->isDraft($post)) {
            return;
        }

        // Post has a draft?
        if ($this->hasDraft($post)) {
            echo $this->view('post_meta_box_edit_draft', [
                'url' => (new Draft($post))->editDraftUrl(),
            ]);

            return;
        }

        // Post does not have a draft. Show link to create one.
        echo $this->view('post_meta_box_create_draft', [
            'url' => (new Action($post))->url('create'),
        ]);
    }

    /**
     * Add draft link to post row links
     *
     * Run on post_row_actions filter. Remove the "quick edit" link from drafts,
     * show the "parent" ID next to drafts, and add a create or edit draft link
     * to regular posts.
     *
     * @param array $actions
     * @param WP_Post $post
     * @return array
     */
    public function insertPostRowAction($actions, $post)
    {
        if (!$this->permitted($post)) {
            return $actions;
        }

        // Post is a draft
        if ($this->isDraft($post)) {
            return $this->insertPostRowActionDraft($actions, $post);
        }

        $view = 'post_row_action_create';
        $url = (new Action($post))->url('create');

        // Post has a draft
        if ($this->hasDraft($post)) {
            $view = 'post_row_action_edit';
            $url = (new Draft($post))->editDraftUrl();
        }

        $actions['draft'] = $this->view($view, ['url' => $url]);

        return $actions;
    }

    /**
     * Remove quick edit link from draft post row actions
     *
     * @param array $actions
     * @param WP_Post $post
     * @param Draft $draft
     * @return array
     */
    public function insertPostRowActionDraft($actions, $post)
    {
        unset($actions['inline hide-if-no-js']);

        $actions['draft'] = $this->view('post_row_action_draft', [
            'id' => (new Draft($post))->postId(),
        ]);

        return $actions;
    }

    /**
     * Enqueue CSS and JavaScript
     *
     * @return void
     */
    public function enqueue()
    {
        wp_enqueue_style('cgit-wp-post-drafts',
            plugin_dir_url(CGIT_POST_DRAFTS_PLUGIN) . 'css/style.css');
    }

    /**
     * Add class to draft admin body
     *
     * @param string $classes
     * @return string
     */
    public function insertBodyClass($classes)
    {
        global $post;

        if (!$this->isDraft($post)) {
            return;
        }

        return trim($classes . ' is-cgit-wp-post-draft');
    }

    /**
     * Can the current user create a draft?
     *
     * @param mixed $post
     * @return boolean
     */
    private function permitted($post = null)
    {
        $user_ok = $this->userPermitted();
        $post_ok = $this->postPermitted($post);
        $type_ok = $this->typePermitted($post);

        return $user_ok && $post_ok && $type_ok;
    }

    /**
     * Is the current user allowed to create drafts?
     *
     * Filters:
     *
     * cgit_post_drafts_caps
     * cgit_post_drafts_caps_not_in
     *
     * @return boolean
     */
    private function userPermitted()
    {
        $allowed = apply_filters('cgit_post_drafts_caps', ['edit_posts']);
        $not_allowed = apply_filters('cgit_post_drafts_caps_not_in', []);

        if ($allowed) {
            foreach ($allowed as $cap) {
                if (!current_user_can($cap)) {
                    return false;
                }
            }
        }

        if ($not_allowed) {
            foreach ($not_allowed as $cap) {
                if (current_user_can($cap)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Are we allowed to create drafts of a post?
     *
     * Filters:
     *
     * cgit_post_drafts_posts
     * cgit_post_drafts_posts_not_in
     *
     * @param mixed $post
     * @return boolean
     */
    private function postPermitted($post)
    {
        $allowed = apply_filters('cgit_post_drafts_posts', []);
        $not_allowed = apply_filters('cgit_post_drafts_posts_not_in', []);

        return $this->propertyPermitted($post, 'ID', $allowed, $not_allowed);
    }

    /**
     * Are we allowed to create drafts of a post type?
     *
     * Filters:
     *
     * cgit_post_drafts_types
     * cgit_post_drafts_types_not_in
     *
     * @param mixed $post
     * @return boolean
     */
    private function typePermitted($post)
    {
        $allowed = apply_filters('cgit_post_drafts_types', []);
        $not_allowed = apply_filters('cgit_post_drafts_types_not_in', []);

        return $this->propertyPermitted($post, 'post_type', $allowed,
            $not_allowed);
    }

    /**
     * Are we allowed to create drafts of a post based on property?
     *
     * @param WP_Post $post
     * @param string $property
     * @param array $yep
     * @param array $nope
     * @return boolean
     */
    public function propertyPermitted($post, $property, $yep = [], $nope = [])
    {
        $post = get_post($post);

        if (!$post) {
            return true;
        }

        // Property in whitelist?
        if ($yep && !in_array($post->$property, $yep)) {
            return false;
        }

        // Property in blacklist?
        if ($nope && in_array($post->$property, $nope)) {
            return false;
        }

        return true;
    }

    /**
     * Post is a draft?
     *
     * @param mixed $post
     * @return boolean
     */
    private function isDraft($post = null)
    {
        $id = $this->sanitizePostId($post);
        $draft = new Draft($id);

        return $id == $draft->draftId();
    }

    /**
     * Post has a draft?
     *
     * @param mixed $post
     * @return boolean
     */
    private function hasDraft($post = null)
    {
        $id = $this->sanitizePostId($post);
        $draft = new Draft($id);

        return $draft->draftId() && $draft->draftId() != $id;
    }

    /**
     * Sanitize post ID
     *
     * @param mixed $post
     * @return integer
     */
    private function sanitizePostId($post = null)
    {
        if (is_null($post)) {
            global $post;
        }

        if ($post instanceof WP_Post) {
            $post = (int) $post->ID;
        }

        if (!is_int($post)) {
            return 0;
        }

        return $post;
    }
}
