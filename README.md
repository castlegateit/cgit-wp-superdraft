# Castlegate IT WP Post Drafts

Basic revision control for WordPress posts, pages, and custom post types, allowing users to create unpublished draft versions of published posts. When editing a post, users will be provided with a "create draft version" button, which creates a duplicate version of the post with draft status.

The draft version can be edited without its changes being made public. When the draft is published, its content, meta, and taxonomy data are merged into the original, published version. At this point, the draft version is deleted.

## Filters

By default, any editor or admin user can create, publish, and delete drafts for any post type. The plugin provides various filters to change this and other settings:

*   `cgit_post_drafts_view_file` filters each view file name. An optional second parameter includes the array of variables available within the view file.

*   `cgit_post_drafts_view_args` filters the array of variables that are made available within each view file. The optional second parameter is the name of the view file.

*   `cgit_post_drafts_view_html` filters the rendered HTML of the view file after it has been parsed. The view file name and array of variables are provided as optional parameters.

*   `cgit_post_draft_meta_box_name` filters the name of the "Draft" meta box, which contains the view, save, publish, and delete buttons when editing a draft version of a post. This is not visible to site users.

*   `cgit_post_draft_meta_box_title` filters the title of the "Draft" meta box, which appears when editing a draft version of a post.

*   `cgit_post_drafts_caps` filters the array of user capabilities that can manage drafts. By default, the `edit_posts` capability is the only one.

*   `cgit_post_drafts_caps_not_in` filters the array of user capabilities that cannot manage drafts. By default, this is empty.

*   `cgit_post_drafts_posts` filters an array of posts that can have drafts. By default, this is empty (all posts can have drafts).

*   `cgit_post_drafts_posts_not_in` filters an array of posts that cannot have drafts. By default, this is empty (all posts can have drafts).

*   `cgit_post_drafts_types` filters an array of post types that can have drafts. By default, this is empty (all post types can have drafts).

*   `cgit_post_drafts_types_not_in` filters an array of post types that cannot have drafts. By default, this is empty (all post types can have drafts).
