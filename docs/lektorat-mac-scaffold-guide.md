# Scaffold Guide for Cursor – Lektorat Mac Booking Plugin

This guide tells Cursor **what to scaffold, where to put files, and how each module should look**. It includes the **architecture tree**, prompts, checklists, and acceptance criteria.

---

## 1) Architecture Tree (Plugin-Only)

```
plugin/
├─ lektorat-mac-booking.php          # Bootstrap (plugin header, autoload, boot)
├─ uninstall.php                     # (optional) cleanup on uninstall
├─ includes/
│  ├─ Autoloader.php                 # PSR-4-esque autoloader for LM\Booking
│  ├─ Plugin.php                     # Orchestrates registration of submodules
│  ├─ Assets.php                     # Enqueue CSS/JS + localized data
│  ├─ PostTypes.php                  # Registers CPT: lm_booking
│  ├─ Admin/
│  │  ├─ AdminMenu.php               # Adds Settings page under Settings >
│  │  ├─ Settings.php                # Registers options + fields (services, emails)
│  │  └─ Columns.php                 # Admin columns for lm_booking list table
│  ├─ Public/
│  │  ├─ Shortcode.php               # Renders [lm_booking_form] -> templates/form.php
│  │  ├─ Ajax.php                    # Handles submission (save + emails)
│  │  └─ Uploads.php                 # (Phase 2) Secure upload handling
│  ├─ Domain/
│  │  ├─ Pricing.php                 # Business logic for totals/surcharges (extensible)
│  │  ├─ Validator.php               # Centralized validation rules
│  │  └─ DTOs.php                    # Simple data transfer objects (if needed)
│  ├─ Infra/
│  │  ├─ Repo.php                    # Persistence: save/retrieve lm_booking
│  │  ├─ Email.php                   # Email sending (client + admin), templates
│  │  └─ Analytics.php               # (Phase 2) GTM/GA events integration
│  └─ Migrations/
│     └─ 001_init.php                # (Optional) DB migrations if using custom tables
├─ assets/
│  ├─ css/form.css                   # Public CSS
│  └─ js/form.js                     # Public JS (price display, submit handler)
└─ templates/
   ├─ form.php                       # Booking form markup (no business logic)
   ├─ email-client.php               # (Optional) email HTML
   └─ email-admin.php                # (Optional) email HTML
```

**Namespaces & text domain:**
- Namespace root: `LM\Booking`
- Text domain: `lm-booking`
- Shortcode: `[lm_booking_form]`

---

## 2) Scaffold Order (Step-by-Step)

1. **Bootstrap + Autoloader**
   - `plugin/lektorat-mac-booking.php` with plugin header, constants, include Autoloader, boot `Plugin`.
   - `includes/Autoloader.php` PSR-like class.

2. **Core Orchestrator**
   - `includes/Plugin.php` that instantiates: `PostTypes`, `Assets`, `Public\Shortcode`, `Public\Ajax`, `Admin\AdminMenu`, `Admin\Settings`, `Admin\Columns`.

3. **CPT & Assets**
   - `includes/PostTypes.php`: register `lm_booking` (private, show_ui true).
   - `includes/Assets.php`: enqueue `assets/css/form.css`, `assets/js/form.js` and localize `ajax` + `nonce`.

4. **Public Interface**
   - `Public/Shortcode.php`: render `templates/form.php`.
   - `templates/form.php`: form fields for service selection, name, email, total, hidden nonce/action.
   - `assets/js/form.js`: price display and Ajax submit.
   - `Public/Ajax.php`: receive data, validate, save, send emails.

5. **Infra: Repo & Email**
   - `Infra/Repo.php`: save booking as CPT with meta (name, email, service, total, breakdown).
   - `Infra/Email.php`: send client + admin emails from stored meta + admin options.

6. **Admin**
   - `Admin/AdminMenu.php`: settings page.
   - `Admin/Settings.php`: serialize services/prices JSON + email templates; `register_setting` + `add_settings_field`.
   - `Admin/Columns.php`: customer + total columns in bookings list.

7. **Domain**
   - `Domain/Pricing.php`: pure function(s) to compute totals; easy to extend to Phase 2 (extras, words, surcharges).

8. **Phase 2 (Optional later)**
   - `Public/Uploads.php`: secure uploads with allowlist + size caps.
   - `Infra/Analytics.php`: fire GTM/GA events on each step / submit.
   - `Migrations/001_init.php`: if moving to custom DB tables.

---

## 3) Cursor Prompts (Exact, Copy-Paste)

**Bootstrap & Autoloader**
> Create `plugin/lektorat-mac-booking.php` with plugin header, constants (VERSION, DIR, URL), include `includes/Autoloader.php`, and boot `LM\Booking\Plugin` on `plugins_loaded`. Then create `includes/Autoloader.php` with a minimal PSR-like autoloader for the `LM\Booking` namespace.

**Plugin Orchestrator**
> Create `includes/Plugin.php` that constructs and registers: `PostTypes`, `Assets`, `Public\Shortcode`, `Public\Ajax`, `Admin\AdminMenu`, `Admin\Settings`, `Admin\Columns`.

**CPT + Assets**
> Implement `includes/PostTypes.php` registering `lm_booking` (private=false? No, public=false; show_ui=true). `includes/Assets.php` enqueues `assets/css/form.css`, `assets/js/form.js`, and localizes `LM_BOOKING = { ajax, nonce }`.

**Shortcode + Form**
> Implement `Public/Shortcode.php` to render `templates/form.php`. Build `templates/form.php` markup with: service select, name, email, computed total, hidden `action=lm_booking_submit`, hidden nonce, and friendly messages.

**Ajax Submit**
> Implement `Public/Ajax.php` hooks for `wp_ajax_{,nopriv}_lm_booking_submit`. Validate nonce, sanitize `name`, `email`, `service`, `total`, then call `Infra\Repo->save_booking()` and `Infra\Email->send_*()`. Return JSON success or error with messages.

**Repo + Email**
> Implement `Infra/Repo.php` to save a booking as a CPT and meta (name, email, service, total, breakdown JSON). Implement `Infra/Email.php` to send emails using templates from options (`Settings`).

**Admin Settings**
> Implement `Admin/AdminMenu.php` (options page). Implement `Admin/Settings.php` storing one options array `lm_booking_settings` with `services` (JSON textarea) and `email_client`/`email_admin`. Render fields; register via `register_setting`. Implement `Admin/Columns.php` with customer + total columns.

**Pricing (Domain)**
> Implement `Domain/Pricing::calculate($servicePrice, $extras=[], $words=null, $delivery='standard')` returning `base`, `extras`, `surcharge`, `total`, `breakdown`.

**Uploads (Phase 2)**
> Implement `Public/Uploads.php` with MIME allowlist (`.doc, .docx, .odt, .rtf, .txt`), 10MB max, randomized names, stored under `/uploads/lektorat-mac/` with `.htaccess` denying execution.

---

## 4) Data Contracts

### Form POST (Ajax)
```jsonc
{
  "action": "lm_booking_submit",
  "nonce": "...",
  "service": "Premium",
  "name": "Jane Doe",
  "email": "jane@example.com",
  "total": 5.99,
  "breakdown": "{\"base\":5.99,\"total\":5.99}"
}
```

### Stored Meta (per booking post)
- `lm_customer_name`: string
- `lm_customer_email`: string
- `lm_service`: string
- `lm_total`: float
- `lm_breakdown`: JSON string

### Options (`lm_booking_settings`)
```json
{
  "services": [
    {"label": "Premium", "price": 5.99},
    {"label": "Mac", "price": 6.99},
    {"label": "All-In", "price": 7.99}
  ],
  "email_client": "Thank you {{name}}, total {{total}}",
  "email_admin": "New request from {{name}} ({{email}}), total {{total}}"
}
```

---

## 5) Acceptance Criteria Checklist

- [ ] Shortcode `[lm_booking_form]` renders on a page and loads assets.
- [ ] Selecting a service updates the **Total** in real-time.
- [ ] Submitting form creates a **Booking (lm_booking)** post with meta.
- [ ] Client receives confirmation email; Admin receives detailed email.
- [ ] Settings page allows editing services (JSON) and email templates.
- [ ] Admin bookings list shows **Customer** and **Total** columns.
- [ ] All inputs sanitized; outputs escaped; nonce verified; no PHP notices.
- [ ] Strings wrapped in `__()`/`_e()` with `lm-booking` domain.
- [ ] Code passes PHPCS (WordPress standards).

---

## 6) Security & Privacy Checklist
- [ ] Nonce checked on Ajax; reject if missing/invalid.
- [ ] Rate-limit or honeypot to reduce spam (optional).
- [ ] No user-supplied HTML is sent unescaped in emails.
- [ ] Consent checkbox and Privacy Policy link present (if required).

---

## 7) Extension Points (Phase 2)
- Add **word count** → norm pages; recompute base price.
- Add **extras** and **delivery surcharges** (+15%, +50%, +24h buffer in display only).
- Add **file uploads** with secure storage above webroot or `.htaccess` block.
- Replace CPT storage with **custom DB tables** via `Migrations/`.
- Emit **GTM/GA events** for step views and submission success.

---

## 8) Do / Don’t Quick Rules
**Do**
- Keep business logic in `Domain/` and `Infra/`.
- Keep templates dumb (render only).
- Use localized data (nonce, ajax URL) via `wp_localize_script`.

**Don’t**
- Don’t access `$_POST` directly in templates.
- Don’t echo unsanitized values.
- Don’t store uploads in executable locations.

---

## 9) Sample Commit Plan
- `feat: plugin bootstrap + autoloader`
- `feat: cpt + assets + shortcode`
- `feat: form + ajax submit + repo save`
- `feat: admin settings + columns`
- `feat: email client + admin`
- `chore: docs + phpcs`
- `fix: validation and escaping`

---

With this guide, Cursor can scaffold the plugin consistently and safely, and you can extend it in Phase 2 without refactoring the foundations.
