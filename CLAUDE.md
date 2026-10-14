# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**agence-adeliom/horizon-tools** is a PHP library (Composer package) providing a toolkit for WordPress development with the Sage theme and Roots Acorn. It manages PostTypes, Taxonomies, Gutenberg Blocks, ACF fields, Hooks, Admin pages, and various WordPress features through a provider-based architecture.

## Requirements

- PHP >= 8.2 (strict types enforced everywhere: `declare(strict_types=1)`)
- WordPress Bedrock + Sage theme + Acorn (^4.0 || ^5.0)
- ACF (Advanced Custom Fields) for field management
- `vinkla/extended-acf` ^14.0 for field registration

## Commands

All Composer and npm commands must be run through DDEV to guarantee a consistent environment (PHP 8.4, Composer 2, Node.js).

```bash
# Install dependencies
ddev composer install
ddev npm install

# Format code (Prettier with PHP plugin)
ddev npx prettier --write src/

# Artisan commands (run from the consuming Sage theme, not this repo)
wp acorn make:posttype       # Generate a PostType class
wp acorn make:taxonomy        # Generate a Taxonomy class
wp acorn make:block           # Generate a Block class
wp acorn make:admin           # Generate an Admin page class
wp acorn make:template        # Generate a Template class
wp acorn make:hook            # Generate a Hook class
wp acorn list:posttypes       # List all registered PostTypes
wp acorn list:taxonomies      # List all registered Taxonomies
wp acorn list:blocks          # List all registered Blocks
wp acorn encryption:status    # Clé de chiffrement + valeurs chiffrées lisibles / illisibles
wp acorn encryption:rotate    # Rechiffre avec APP_KEY (ancienne clé dans APP_PREVIOUS_KEYS)
wp acorn encryption:snapshot  # Sauvegarde les valeurs chiffrées locales avant un import de base
wp acorn encryption:restore   # Les restaure après l'import : options absentes/vides/illisibles, métadonnées chiffrées illisibles seulement ; instantané conservé si des entrées sont ignorées
```

There is no unit test suite. `tests/integration/*.php` are integration checks run from a consuming Horizon project with `wp eval-file` (they need WordPress, ACF and Acorn); each exits with code 1 on failure. Run them with the container's absolute path, e.g. `wp eval-file /var/www/html/vendor/agence-adeliom/horizon-tools/tests/integration/<x>.php`. They have no `declare(strict_types=1)`: WP-CLI `eval-file` refuses it as the first statement.

## Architecture

### Provider-Based Bootstrap

`HorizonToolsServiceProvider` is the main orchestrator. It boots sub-providers sequentially:

1. `CommandsServiceProvider` → CLI commands
2. `HttpLoginServiceProvider` → HTTP Basic Auth
3. `CommentsServiceProvider` → Comment system control
4. `PostTypeServiceProvider` → PostTypes, Taxonomies, Templates
5. `AdminServiceProvider` → Admin/option pages
6. `BlockServiceProvider` → Gutenberg blocks (unregisters defaults)
7. `HooksServiceProvider` → Auto-discovered hook classes
8. `MiddlewareServiceProvider`, `LivewireServiceProvider`, `SeoServiceProvider`, `SearchEngineServiceProvider`, `FormsServiceProvider`
9. Optional: `HorizonBlocksServiceProvider`, `HorizonPostTypesServiceProvider` (if companion packages installed)

Providers use `SkipProviderException` for graceful feature disabling.

### Auto-Discovery Pattern

`ClassService` and `FileService` scan directories in the consuming theme to auto-register classes:

- `app/PostTypes/` → classes extending `AbstractPostType`
- `app/Blocks/` → classes extending `AbstractBlock`
- `app/Taxonomies/` → classes extending `AbstractTaxonomy`
- `app/Hooks/` → classes extending `AbstractHook`
- `app/Admin/` → classes extending `AbstractAdmin`

### Abstract Class Hierarchy

Each feature domain has an abstract base in `src/` that consuming theme classes extend:

| Abstract | Purpose | Key methods |
|---|---|---|
| `AbstractPostType` | Custom post types | `getConfig()`, `getFields()`, `getCustomColumns()` |
| `AbstractTaxonomy` | Custom taxonomies | `getConfig()`, `getFields()`, `getPostTypes()` |
| `AbstractBlock` | Gutenberg blocks | `getBlockFields()`, `getBlockTitle()`, `renderBlockCallback()`, `addToContext()` |
| `AbstractAdmin` | Admin/option pages | Via ACF option pages |
| `AbstractHook` | WordPress hooks | `init()` with `add_filter`/`add_action` calls |
| `AbstractTemplate` | Gutenberg templates | Maps blocks to post types |
| `AbstractRepository` | Data access | `getAll()`, `getOneBySlug()`, `getOneById()`, uses `QueryBuilder` |

### Query Builder

`Database/QueryBuilder` extends `HorizonQueryBuilder` (external package) with fluent interface: `postType()`, `setPage()`, `setPerPage()`, `addMetaQuery()`, `orderBy()`, `getQuery()`, `getOneOrNull()`.

Supporting classes: `MetaQuery`, `TaxQuery`, `LatLngQuery`.

### Services

`src/Services/` contains ~29 service classes for business logic (SEO multi-plugin support, media handling, image color extraction, search engine, caching, etc.). Services use Laravel facades (`Config`, `Cache`, `View`).

### Encrypted Text Field

ACF field type `encrypted_text` (`src/ACF/AcfFieldEncryptedText.php`, registered in `Hooks/DefaultAcfHooks` via `acf_register_field_type`, its hooks in `registerEncryptedTextHooks()`) with the Extended ACF wrapper `Fields/Text/EncryptedText` (`->writeOnly()` opt-in). Values are encrypted with Acorn's Laravel Encrypter (`APP_KEY`, rotation through `APP_PREVIOUS_KEYS`); reading the value needs both the code (`.env`) and the database.

| Class | Role |
|---|---|
| `Services/Encryption/EncryptedValueCodec` | Only place that encrypts/decrypts. Stored format `hz_enc:v1:<Laravel payload>`; unprefixed values are legacy plaintext, read as-is |
| `ACF/AcfFieldEncryptedText` | Encrypts in `update_value`, decrypts in `load_value` (never `format_value`), admin rendering, validation, hidden from ACF REST |
| `ACF/EncryptedBlockData` | ACF blocks: `acf/pre_save_block`, `use_post_meta` pending store, `widget_block` option, `isBlockFormField()` (`acf-block_` prefix) |
| `Services/Encryption/EncryptedValueRepository` | Finds encrypted values by prefix (`TABLES`, `TOKEN_PATTERN` built from the codec prefix): options, post/term/user meta, tokens embedded in `post_content` and serialized options. `write()` returns whether the value was written |
| `Services/Encryption/SnapshotStore` | Snapshot/restore around a DB import (keys differing per environment) |
| `Console/Commands/Encryption{Status,Rotate,Snapshot,Restore}` | `encryption:*` commands (no `Command` suffix, repo convention) |
| `Console/Listeners/PreventKeyGenerate` | `CommandStarting` listener: throws to block `key:generate` (without `--show`) while readable values exist |

Invariants to preserve:

- **Never write plaintext.** Without a key, admin saves are refused (`validate_value`); block paths store `''` and log, they never throw (that would break the whole page save).
- **Never silently empty a value.** An unreadable value (missing key or other key) is kept on save, flagged in admin (warning + `error_log`), and only the « Effacer » checkbox clears it.
- **Admin form arrays are slashed** (`['value' => …, 'clear' => …]` from `$_POST`): `wp_unslash` in that branch only; strings from `update_field()` and block data are not slashed.
- `writeOnly` is ignored in blocks and inside repeater/flexible-content rows (the stored value is read back by an indexed name).
- `APP_KEY` becomes data to back up: `key:generate` is blocked by `PreventKeyGenerate` (registered in `CommandsServiceProvider` before the commands' `try`) while readable values exist. Never let `key:generate` (without `--show`) run against a project's `.env`, even with an injected key: Acorn's version replaces the current key, or prepends a new `APP_KEY` line when it cannot find it. `tests/integration/commands.php` redirects the environment file to a temp file.
- Snapshot/restore only covers options and meta (not block content nor widgets); restore recreates absent values for `options` only, because post/term/user IDs are not stable across environments.

Known unsupported paths (documented in README « Limites connues »): classic ACF widget, Customizer, ACF REST_Save array values.

### Code Generation

`src/Console/Commands/` contains `Make*` commands that generate classes from stubs in `src/Console/stubs/`. Each stub uses `%%PLACEHOLDER%%` tokens.

## Code Style

- Prettier with `@prettier/plugin-php`: 140 char width, 4-space indent, single quotes, trailing commas
- All files use `declare(strict_types=1)`
- PSR-4 autoloading: `Adeliom\HorizonTools\` → `src/`
- French language for WordPress admin labels in stubs and default strings
- Typed parameters and return types on all methods
