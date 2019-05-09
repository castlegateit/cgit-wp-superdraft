# Castlegate IT WP Super Draft

Basic revision control for WordPress posts, pages, and custom post types, allowing users to create unpublished draft versions of published posts. When editing a post, users will be provided with a "create draft version" button, which creates a duplicate version of the post with draft status.

The draft version can be edited without its changes being made public. When the draft is published, its content, meta, and taxonomy data are merged into the original, published version. At this point, the draft version is deleted.

## Filters

By default, any editor or admin user can create, publish, and delete drafts for any post type. The plugin provides filters to change this and other settings:

*   `cgit_superdraft_capability` filters the user capability required to create and edit drafts. The default is `edit_posts`.

*   `cgit_superdraft_types` filters the list of post types that can have draft versions. The default is a list of all post types.

## License

Copyright (c) 2019 Castlegate IT. All rights reserved.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
