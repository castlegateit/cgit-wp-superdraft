<?php

namespace Cgit\SuperDraft;

class Plugin
{
    /**
     * Post meta key
     *
     * This is used to save the ID of the "parent" post of a draft post in the
     * post meta table.
     *
     * @var string
     */
    const POST_META_KEY = 'cgit_superdraft_post_id';

    /**
     * Draft meta key
     *
     * This is used to save the ID of the draft version of a published post in
     * the post meta table.
     *
     * @var string
     */
    const DRAFT_META_KEY = 'cgit_superdraft_draft_id';

    /**
     * Construct
     *
     * @return void
     */
    public function __construct()
    {
        $admin = new Admin;
    }
}
