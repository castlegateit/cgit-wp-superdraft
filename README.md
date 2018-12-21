# Castlegate IT WP Super Draft

Basic revision control for WordPress posts, pages, and custom post types, allowing users to create unpublished draft versions of published posts. When editing a post, users will be provided with a "create draft version" button, which creates a duplicate version of the post with draft status.

The draft version can be edited without its changes being made public. When the draft is published, its content, meta, and taxonomy data are merged into the original, published version. At this point, the draft version is deleted.

## Filters

By default, any editor or admin user can create, publish, and delete drafts for any post type. The plugin provides filters to change this and other settings:

*   `cgit_superdraft_capability` filters the user capability required to create and edit drafts. The default is `edit_posts`.

*   `cgit_superdraft_types` filters the list of post types that can have draft versions. The default is a list of all post types.
