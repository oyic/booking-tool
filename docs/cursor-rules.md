# Cursor Rules for Lektorat Mac Booking Plugin

These rules guide AI-assisted coding inside Cursor.

## Project Context
- **Type:** WordPress custom plugin
- **Name:** Lektorat Mac Booking
- **Namespace:** `LM\Booking`
- **Text domain:** `lm-booking`
- **Scope:** Booking request form with pricing display, email notifications, and WP storage.

## Coding Placement
- **Frontend (UI, form, Ajax):** `includes/Public/`
- **Admin (settings, columns, menus):** `includes/Admin/`
- **Domain logic (pricing, validation):** `includes/Domain/`
- **Infrastructure (emails, repo, analytics):** `includes/Infra/`
- **Templates (form, emails):** `plugin/templates/`

## Coding Guidance
- Always sanitize input (`sanitize_text_field`, `sanitize_email`, etc.).
- Escape all output (`esc_html`, `esc_attr`, `esc_url`).
- Use nonces for Ajax: `wp_create_nonce('lm_booking_nonce')` + `check_ajax_referer`.
- No logic in templates — only rendering.
- Wrap all user-facing text with `__()` or `_e()` using `lm-booking` domain.
- Follow coding-guidelines.md for style and structure.
- File uploads (Phase 2): allowlist editable formats only, enforce size limits, block direct access with `.htaccess`.

## Example Prompts
- “Create a shortcode `[lm_booking_form]` that renders `templates/form.php`.”
- “Implement Pricing::calculate() with base price, extras, surcharges, and breakdown.”
- “Add Admin settings page under Settings > Lektorat Mac to manage services and email templates.”
