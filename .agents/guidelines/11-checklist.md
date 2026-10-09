# Pre-Generation Checklist

> Verify before committing or submitting code.

---

## PHP & Standards

| Check | Verify |
|-------|--------|
| PHP Version | Minimum 8.3 declared in plugin header and composer.json |
| PHP Features | Features above 8.3 documented with `@requires PHP 8.4` |
| Strict Types | `declare(strict_types=1)` in every file |
| Typing | All params/returns typed, PHPDoc complete |
| Modern PHP | Constructor promotion, match, union types, enums used |
| Standards | WPCS followed, tabs for indentation |

---

## Naming & Prefixes

| Check | Verify |
|-------|--------|
| Slug Unique | Plugin slug registered in registry table; no collision |
| Constants | Only `{PLUGIN_NAME}_FILE` and `{PLUGIN_NAME}_PATH` as globals |
| Class Constants | All others in `Plugin` class |
| Namespace | `{PluginName}\` matches the registered slug (ask user — only for large plugins) |
| Hooks | All hooks prefixed with `{plugin-name}/` |
| Prefix | Options, transients, meta keys, nonces use `Plugin::PREFIX` |
| DB Tables | Custom tables use `{$wpdb->prefix}{plugin_name}_{slug}_` |
| REST Routes | Namespace is `{plugin-name}/{plugin-slug}/v1` |
| Script Handles | Format: `{plugin-name}-{plugin-slug}-{purpose}` |

---

## Security

| Check | Verify |
|-------|--------|
| Input Sanitized | All `$_GET`, `$_POST`, `$_REQUEST` sanitized |
| Output Escaped | All output uses `esc_html`, `esc_attr`, `esc_url` |
| Nonces | All forms and AJAX use nonces with `Plugin::PREFIX` |
| Capabilities | `current_user_can()` before sensitive operations |
| ABSPATH Check | Every PHP file starts with ABSPATH check |
| REST Permissions | `permission_callback` is never `__return_true` |
| SQL | `$wpdb->prepare()` for any direct SQL |

---

## WooCommerce

| Check | Verify |
|-------|--------|
| CRUD Classes | Using `wc_get_product()`, `wc_get_order()` — no direct SQL |
| HPOS Declared | Plugin declares HPOS compatibility |
| No Direct SQL | Never query `wp_posts`/`wp_postmeta` for orders |
| Block Checkout | Considered if extending checkout |

---

## Performance

| Check | Verify |
|-------|--------|
| Conditional Loading | Assets only load on needed pages |
| Script Strategy | Using `defer` or `async` for scripts |
| Action Scheduler | Heavy tasks (> 2s) are async |
| Caching | Expensive operations cached with prefix |
| Cache Invalidation | Every write operation invalidates caches |
| Query Limits | All `wc_get_orders()`/`wc_get_products()` have `limit` |
| Object Cache | Per-request data uses `wp_cache_*` |

---

## JavaScript

| Check | Verify |
|-------|--------|
| Frontend | Vanilla JS with `fetch()`, no jQuery dependency |
| Admin | jQuery acceptable |
| Data Passing | `wp_add_inline_script` for config data |
| i18n | `wp_localize_script` only for translatable strings |

---

## Quality & Testing (Mandatory)

**All tests must be executed and passing before commit. No exceptions.**

| Check | Command | Verify |
|-------|---------|--------|
| Unit Tests Run | `bun run test` | All unit tests pass (exit code 0) |
| Unit Tests Exist | — | Every service, handler, controller, enum has tests |
| E2E Tests Run | `bun run test:e2e` | All Playwright tests pass (if E2E tests exist) |
| E2E Tests Exist | — | Shopper flows, merchant flows, API endpoints covered |
| PHPStan | `bun run stan` | Level 6+ passes with no ignored errors |
| Bug Fixes | — | Every bug fix has a failing test written BEFORE the fix |
| Edge Cases | — | Zero, negative, null, empty, max, UTF-8, duplicates covered |
| Dependencies | — | WooCommerce version check with admin notice fallback |

### Test Execution Order

```bash
# 1. Unit tests (fast, run first)
bun run test

# 2. Static analysis
bun run stan

# 3. E2E tests (slower, run last — ask user first)
bun run test:e2e
```

### If Tests Fail

- **Do not commit.** Fix the failing test first.
- If a test fails unexpectedly, investigate the root cause — don't just skip or delete the test.
- If E2E tests fail due to environment issues, notify the user and document the issue.

---

## Build

| Check | Verify |
|-------|--------|
| CHANGELOG.md | Updated with current version and changes |
| Bundle Script | `package.json` has `bundle` script using bestzip |
| Bundle Command | `bun run bundle` creates `{plugin-name}.zip` |
| No Dev Dependencies | Production `vendor/` has no dev packages |
| Excluded | `tests/`, `phpunit.xml`, `phpstan.neon`, `.git/`, `node_modules/` not in bundle |
| Zip Created | `{plugin-name}.zip` ready for upload |
