# Security Guidelines – Lektorat Mac Booking

## General
- Treat all input as untrusted.
- Validate on server side — never trust client JS.

## Forms & Ajax
- Nonces on all requests: `wp_create_nonce('lm_booking_nonce')`.
- Check nonces with `check_ajax_referer`.
- Capability checks on admin endpoints.

## File Uploads (Phase 2)
- Allowlist MIME types:
  - `.doc`, `.docx`, `.odt`, `.rtf`, `.txt`

- Max size: 10MB (configurable).
- Store in `/uploads/lektorat-mac/` with `.htaccess` denying execution.
- Rename files randomly; never trust user file name.

## Data Storage
- Store booking data as CPT or in a custom table.
- Ensure proper sanitization and escaping on save and render.

## Emails
- Use SMTP (via WP Mail SMTP).
- Templates must not allow raw user HTML injection.
- Provide plaintext fallback.

## GDPR & Privacy
- Include consent checkbox in form.
- Link to Privacy Policy.
- Provide admin ability to delete booking data.

