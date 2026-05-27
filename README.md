# KB Manager Plugin

KB Manager is a WordPress plugin that provides a focused knowledge base workflow with:

- A custom post type for KB articles (`kb_article`).
- Parent-child relationships for KB articles.
- A hierarchical taxonomy for sections (`kb_section`).
- Manual ordering for sections and articles.
- A dedicated **KB Editor** role with KB-specific capabilities.
- Restricted admin/media access for KB Editors.
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
- Limits media browsing and deletion so KB Editors only manage their own uploads.

### Manual ordering

- Adds an **Order** field to KB Section add/edit forms.
- Adds an **Article Order** metabox to KB Article edit screens.
- Persists section order in term meta (`_kb_section_order`).
- Persists article order using WordPress `menu_order`.
- Displays section order in taxonomy list columns.
- Sorts sections by order ascending in admin by default.
- Sorts KB articles by `menu_order` then title in admin by default.

### Admin restrictions for KB Editors

- Hides most wp-admin menu pages not needed for KB management.
- Restricts accessible admin screens to KB, media, and profile flows.
- Redirects dashboard access to the KB article list.
- Adds an **Organize KB** submenu page with drag-and-drop section and article ordering.

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

For the **current KB article**, renders related family links in **post-order traversal semantics**:

- **Parent chain** (if present): immediate parent up through the top-level parent.
- **Descendants** (if present): all children and deeper descendants in post-order (visit deeper descendants before each direct child).

- **Attributes**
  - None.
- **Context behavior**
  - Outputs only on `kb_article` posts.
  - Returns empty outside `kb_article` context.
- **Output**
  - Wrapper: `<div class="kb-article-family-post-order">`
  - Parents list: `<ul class="kb-article-parents">`
  - Descendants list: `<ul class="kb-article-descendants">`
- **Ordering**
  - Parent chain: immediate parent first, then its parent, continuing to top-level.
  - Descendants: post-order using sibling ordering of `menu_order` ascending, then title.

## Installation

1. Copy `kb_manager_plugin.php` into a plugin directory (for example: `wp-content/plugins/kb-manager/`).
2. Activate **KB Manager** in the WordPress admin Plugins screen.
3. Assign the **KB Editor** role to users who should manage KB content only.

## Notes

- On activation/deactivation, the plugin flushes rewrite rules.
- KB section order editing requires the `manage_kb_sections` capability.
