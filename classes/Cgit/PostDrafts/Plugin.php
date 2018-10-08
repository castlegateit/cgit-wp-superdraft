<?php

namespace Cgit\PostDrafts;

/**
 * Plugin initialization
 *
 * When the plugin loads, make amendments to the WP admin interface and check
 * for GET requests related to drafts.
 */
class Plugin
{
    /**
     * Construct
     *
     * @return void
     */
    public function __construct()
    {
        $action = new Action;
        $admin = new Admin;

        add_action('admin_init', [$action, 'init']);
        add_action('init', [$admin, 'init']);
    }
}
