<?php

/*

Plugin Name: Castlegate IT WP Super Draft
Plugin URI: https://github.com/castlegateit/cgit-wp-superdraft
Description: Post drafts with editorial review and approval.
Version: 0.2
Author: Castlegate IT
Author URI: https://www.castlegateit.co.uk/
Network: true

Copyright (c) 2018 Castlegate IT. All rights reserved.

*/

if (!defined('ABSPATH')) {
    wp_die('Access denied');
}

define('CGIT_SUPERDRAFT_PLUGIN', __FILE__);

require_once __DIR__ . '/classes/autoload.php';

$plugin = new \Cgit\Superdraft\Plugin;

do_action('cgit_superdraft_plugin', $plugin);
do_action('cgit_superdraft_loaded');
