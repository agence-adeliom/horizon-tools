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
```

There is no test suite in this repository.

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

### Code Generation

`src/Console/Commands/` contains `Make*` commands that generate classes from stubs in `src/Console/stubs/`. Each stub uses `%%PLACEHOLDER%%` tokens.

## Code Style

- Prettier with `@prettier/plugin-php`: 140 char width, 4-space indent, single quotes, trailing commas
- All files use `declare(strict_types=1)`
- PSR-4 autoloading: `Adeliom\HorizonTools\` → `src/`
- French language for WordPress admin labels in stubs and default strings
- Typed parameters and return types on all methods
