# KB Manager Plugin

KB Manager is a WordPress plugin that provides a focused knowledge base workflow with:

- A custom post type for KB articles (`kb_article`).
- Parent-child relationships for KB articles.
- A hierarchical taxonomy for sections (`kb_section`).
- Manual ordering for sections and articles.
- A dedicated **KB Editor** role with KB-specific capabilities.
- Restricted admin/media access for KB Editors.
- Shortcodes for listing sections and section articles.

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

### Shortcodes

- `[kb_sections parent="0" hide_empty="false"]`  
  Lists child KB sections for a given parent term, sorted by section order.
- `[kb_section_articles section="" posts_per_page="-1"]`  
  Lists KB articles in a section by term ID or slug, or current KB section archive context, sorted by article order.
- `[kb_all_sections_articles]`  
  Renders all non-empty KB sections and all published KB articles under each section in a nested list, sorted by section/article order.

## Installation

1. Copy `kb_manager_plugin.php` into a plugin directory (for example: `wp-content/plugins/kb-manager/`).
2. Activate **KB Manager** in the WordPress admin Plugins screen.
3. Assign the **KB Editor** role to users who should manage KB content only.

## Notes

- On activation/deactivation, the plugin flushes rewrite rules.
- KB section order editing requires the `manage_kb_sections` capability.
