# Code Review Rules

---

## General Rules (always active — ALL files)

REJECT if:
- Hardcoded secrets, API keys, or credentials in code
- `var_dump`, `print_r`, or debug `echo` left in PHP files
- `console.log` or `debugger` left in JS files
- Empty `catch` blocks (silent error swallowing)
- Commented-out blocks of code
- Code duplication — clear DRY violation
- Descriptive variable/function names violated (`$x`, `$tmp`, `$data2`)

---

## PHP

REJECT if:
- Missing `declare(strict_types=1)` at top of file
- Missing type hints on method parameters or return types
- Bare `catch (\Throwable $e) {}` — must log or rethrow
- `@` error suppressor used
- `die()` or `exit()` with no HTTP status code
- Direct `$_GET` / `$_POST` access inside `src/` classes
- Business logic inside `views/` files

REQUIRE:
- `throw new \RuntimeException(...)` for service-level failures
- All env vars read from `$_ENV`, never `getenv()` (dotenv loads into `$_ENV`)
- `htmlspecialchars()` on every user-facing output in views

---

## JavaScript

REJECT if:
- `var` used — require `const` or `let`
- `console.log` or `debugger` left in
- External library imported (project is vanilla JS only)
- Direct DOM mutation outside of IIFE or event handler scope

REQUIRE:
- `fetch` with `.catch` or `try/catch` around `await fetch`
- User-facing errors shown in UI, not via `alert()` (except existing translate flow)

---

## CSS

REJECT if:
- `!important` used without a clear justification comment
- Inline `style=` attributes added to PHP views (except dynamic runtime values)
- New class names that break existing BEM-style convention

---

## Security

REJECT if:
- Any `eval()` or `shell_exec()` / `exec()` without sanitised input
- User-supplied file extension used directly without allowlist check
- `move_uploaded_file` destination not within `uploads/`
- SQL queries built by string concatenation (no DB in this project, but reject pattern if introduced)

---

## Response Format

FIRST LINE must be exactly one of:
STATUS: PASSED
STATUS: FAILED

If FAILED, list violations as:
`file:line - rule - issue description`
