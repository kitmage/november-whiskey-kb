# KB Manager Plugin

KB Manager is a WordPress plugin that provides a focused knowledge base workflow with:

- A custom post type for KB articles (`kb_article`).
- Parent-child relationships for KB articles.
- A hierarchical taxonomy for sections (`kb_section`).
- Manual ordering for sections and articles.
- A dedicated **KB Editor** role with KB-specific capabilities.
- Restricted admin access for KB Editors.
- Shortcodes for rendering KB sections and KB article navigation lists.

## Features

### Custom content model

- Registers a public, REST-enabled **Knowledge Base** post type at `/knowledge-base/`.
- Enables hierarchical KB articles so each article can optionally have a parent article.
- Registers a hierarchical **KB Sections** taxonomy at `/kb-section/`.

### Role and permissions

- Adds a `kb_editor` role.
- Grants capabilities to create, edit, publish, and delete KB articles.
- Grants capabilities to manage and assign KB sections.
- Ensures the Administrator role also has KB capabilities so KB menus remain visible to site admins.
- Allows KB Editors to browse and manage the Media Library.

### Manual ordering

- Keeps the KB Section taxonomy controls hidden in wp-admin while preserving frontend taxonomy behavior.
- Adds an **Article Order** metabox to KB Article edit screens.
- Persists section order in term meta (`_kb_section_order`).
- Persists article order using WordPress `menu_order`.
- Sorts sections by order ascending in admin by default.
- Sorts KB articles by `menu_order` then title in admin by default.
- Adds an **Organize Articles** visual editor for drag-and-drop sibling ordering and nested parent-child article relationships.
- Color-codes and labels article drop zones by generation, refreshing the indicators after articles move.
- Adds padded directional-button controls for sibling reordering, promoting an article one generation, and nesting it beneath its previous sibling.

### Admin restrictions for KB Editors

- Hides most wp-admin menu pages not needed for KB management.
- Restricts accessible admin screens to KB, media, and profile flows.
- Redirects dashboard access to the KB article list.
- Adds an **Organize Articles** submenu page with drag-and-drop article ordering and unlimited parent-child nesting.

## Shortcodes

All shortcodes return empty output when no matching content is found.

### 1) `[kb_sections parent="0" hide_empty="false"]`

Lists KB section terms directly under the provided `parent` term ID.

- **Attributes**
  - `parent` (int, default `0`): Parent section term ID to query.
  - `hide_empty` (bool-like string, default `false`): Whether to exclude terms with no posts.
- **Output**
  - `<ul class="kb-sections">` with linked section names.
- **Ordering**
  - Section order meta (`_kb_section_order`) ascending, then section name.

### 2) `[kb_section_articles section="" posts_per_page="-1"]`

Lists published KB articles for one KB section.

- **Attributes**
  - `section` (string/int, default `""`):
    - numeric term ID, or
    - term slug, or
    - empty to use current `kb_section` archive context.
  - `posts_per_page` (int, default `-1`): Max number of articles.
- **Output**
  - `<ul class="kb-section-articles">` with linked article titles.
- **Ordering**
  - `menu_order` ascending, then title.

### 3) `[kb_all_sections_articles]`

Renders all non-empty KB sections recursively with their published KB articles.

- **Attributes**
  - None.
- **Output**
  - Nested `<ul class="kb-all-sections-articles">` lists with section links and article links.
- **Ordering**
  - Sections: section order meta ascending, then section name.
  - Articles inside each section: `menu_order` ascending, then title.

### 4) `[kb_article_titles]`

Renders the entire KB article tree from top-level parents downward.

- **Attributes**
  - None.
- **Output**
  - `<ul class="kb-article-titles">` with nested lists.
  - Adds CSS classes per item depth and relationship:
    - `kb-article-item`
    - `kb-depth-{n}`
    - `kb-child-index-{n}`
    - `kb-parent` or `kb-descendant`
    - `kb-active` for the current queried KB article
- **Ordering**
  - At every tree level: `menu_order` ascending, then title.

### 5) `[kb_article_family_post_order]`

Renders a **filtered tree view** for the current KB article using the same nested structure/class style as `[kb_article_titles]`, but only includes articles related to the current one.

- **Related articles included**
  - All ancestors up to the top-level parent.
  - The current article itself.
  - All descendants (children, grandchildren, etc.).
  - Sibling posts of the current article (when the current article has a parent).
- **Attributes**
  - None.
- **Context behavior**
  - Outputs only on `kb_article` posts.
  - Returns empty outside `kb_article` context.
  - Returns empty when the current article has no parent (single breadcrumb element).
- **Output**
  - `<ul class="kb-article-family-post-order kb-article-titles">` with nested `<ul class="kb-article-children">` trees.
  - Reuses item classes from `[kb_article_titles]`, including `kb-active` for the current queried article.
- **Ordering**
  - Tree rendering keeps sibling order as `menu_order` ascending, then title.
  - Descendant collection is still computed in post-order for family membership.


### 6) `[kb_breadcrumbs separator=">"]`

Renders a breadcrumb trail for the current KB article from top-level ancestor to current article.

- **Attributes**
  - `separator` (string, default `>`): Text shown between breadcrumb items.
- **Context behavior**
  - Outputs only on `kb_article` posts.
  - Returns empty outside `kb_article` context.
  - Returns empty when the current article has no parent (single breadcrumb element).
- **Output**
  - `<nav class="kb-breadcrumbs">` containing linked ancestors and current title as `<span class="kb-breadcrumb-current kb-active">...`.
- **Ordering**
  - Top-level ancestor → ... → immediate parent → current article.

## Installation

1. Copy `kb_manager_plugin.php` into a plugin directory (for example: `wp-content/plugins/kb-manager/`).
2. Activate **KB Manager** in the WordPress admin Plugins screen.
3. Assign the **KB Editor** role to users who should manage KB content only.

## Notes

- On activation/deactivation, the plugin flushes rewrite rules.
- The KB Section taxonomy admin UI, article-editor field, and article-list section column are intentionally hidden. Restore the commented `show_ui` and `show_admin_column` settings and remove `meta_box_cb` in `register_taxonomy()` to expose them again.
