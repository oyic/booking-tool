# Coding Guidelines – Lektorat Mac Booking

## Standards
- PHP 8.1+, PSR-12, and WordPress Coding Standards.
- Enforce with PHPCS (WordPress + WordPress-Docs rulesets).

## Structure
- Use small, single-responsibility classes.
- Organize by domain (`Domain`, `Infra`, `Admin`, `Public`).
- Use dependency injection over globals when possible.

## Naming
- Classes: `StudlyCaps` (e.g., `BookingForm`).
- Methods & vars: `camelCase`.
- Constants: `UPPER_SNAKE_CASE`.

## Data Handling
- Sanitize **all input** (user form data, Ajax).
- Escape **all output** (HTML, attributes, URLs).
- Use prepared statements or WP APIs for DB access.

## Security
- Nonces on all Ajax/forms.
- Capability checks for admin actions (`current_user_can('manage_options')`).

## Internationalization
- All user-facing text wrapped in `__()` or `_e()`.
- Use text domain `lm-booking`.

## Error Handling
- Use `WP_Error` for recoverable errors.
- Display user-friendly error messages.

## Versioning & Commits
- Semantic Versioning (SemVer).
- Conventional commits: `feat:`, `fix:`, `docs:`, `refactor:`.

