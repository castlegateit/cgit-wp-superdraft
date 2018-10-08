<?php

/*

Plugin Name: Castlegate IT WP Post Drafts
Plugin URI: https://github.com/castlegateit/cgit-wp-post-drafts
Description: Post drafts with editorial review and approval.
Version: 0.1
Author: Castlegate IT
Author URI: https://www.castlegateit.co.uk/
Network: true

Copyright (c) 2018 Castlegate IT. All rights reserved.

*/

if (!defined('ABSPATH')) {
    wp_die('Access denied');
}

define('CGIT_POST_DRAFTS_PLUGIN', __FILE__);

require_once __DIR__ . '/classes/autoload.php';

$plugin = new \Cgit\PostDrafts\Plugin;

do_action('cgit_post_drafts_plugin', $plugin);
do_action('cgit_post_drafts_loaded');
