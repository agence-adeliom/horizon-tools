# Introducing WordPress Horizon Tools 🚀

Unleash the power of WordPress with WordPress Horizon Tools, your ultimate toolkit for elevating your development experience!
## Easy Installation

Get started with WordPress Horizon Tools in just a few simple steps:

1. **Add Repository**: Incorporate our package into your project by adding our repository to your `composer.json`.
```json  
{
  "type": "vcs",
  "url": "git@github.com:agence-adeliom/horizon-tools.git"
}  
```  

2. **Run Composer**: Execute the following command in your terminal to install WordPress Horizon Tools:

```bash  
composer require agence-adeliom/horizon-tools  
```  

3. **Voilà!** 🎉 WordPress Horizon Tools is now seamlessly integrated into your project, ready to supercharge your development process.

## Unleash All Features

Unlock the full potential of WordPress Horizon Tools by ensuring your project meets these requirements:

- WordPress BedRock
- Sage theme
- Acorn installed

Once you've met these prerequisites, head to your template folder's `composer.json` file. Confirm that the `extra` section includes the Horizon Tools provider :

```json  
{  
  "extra": {  
    "acorn": {  
      "providers": [  
        ...  
        "Adeliom\\HorizonTools\\Providers\\HorizonToolsServiceProvider"  
      ]
    }  
  }  
}  
  
```  

Experience the magic of WordPress Horizon Tools and revolutionize your WordPress development workflow today! 🌟

## Activate Features Individually

While unlocking the full potential of WordPress Horizon Tools is fantastic, sometimes you may want to enable specific features tailored to your project's needs. Fear not, as Horizon Tools offers the flexibility to activate features on a case-by-case basis using the following providers:

- **AdminServiceProvider:** Facilitates the creation of custom administration pages within WordPress, integrating Advanced Custom Fields (ACF) to streamline the process of building and managing custom admin interfaces.
- **BlockServiceProvider:** Unleashes the power of Gutenberg blocks within your Sage theme, allowing you to create rich and dynamic content layouts effortlessly.
- **CommentsServiceProvider:** Provides the functionality to disable the WordPress commenting system entirely, allowing you to manage comments externally or implement alternative commenting solutions.
- **PostTypeServiceProvider:** Facilitates the management and customization of custom post types, enabling you to create and manage various content types seamlessly.
- **HooksServiceProvider:** Auto-discovers and registers hook classes from the `app/Hooks` directory.
- **SearchEngineServiceProvider:** Registers the search engine admin page and configuration.
- **ShareServiceProvider:** Registers the share options admin page (config-gated via `share.enable`).
- **SeoServiceProvider:** Registers SEO-related hooks (RankMath breadcrumbs, link obfuscation).
- **FormsServiceProvider:** Adds Gravity Forms confirmation pages support.
- **MiddlewareServiceProvider:** Registers the trailing slash middleware.
- **LivewireServiceProvider:** Optional Livewire integration.

To activate specific features, simply include the corresponding provider within your `composer.json` file in the `extra` section, like so:

```json
{
  "extra": {
    "acorn": {
      "providers": [
        ...
        "Adeliom\\HorizonTools\\Providers\\AdminServiceProvider",
        "Adeliom\\HorizonTools\\Providers\\BlockServiceProvider",
        "Adeliom\\HorizonTools\\Providers\\CommentsServiceProvider",
        "Adeliom\\HorizonTools\\Providers\\PostTypeServiceProvider"
      ]
    }
  }
}
```

Experience the versatility of WordPress Horizon Tools as you tailor your development workflow to suit your project's specific requirements! 🚀

## Power-Packed Features Showcase 💥

### PostTypes

- **Easy Declaration**: Define PostTypes effortlessly using classes in the `{{theme}}/app/PostTypes` directory, building upon `AbstractPostType`.
- **Slug Customization**: Personalize slugs effortlessly via the `getSlug` method.
- **Tailored Configuration**: Fine-tune base configurations using an array with the `getConfig` method.
- **Field Flexibility**: Rearrange field positions effortlessly with `getFieldsPosition`.
- **Streamlined Editing**: Add fields directly to the Post edit sidebar using the dynamic `getFields` method (powered by Vinkla).
- **Field Location Control**: Customize or add field locations seamlessly with `getFieldsLocation` for enhanced flexibility.
- **Automatic Registration**: Automatically register classes within the `app/PostTypes` directory.

### Taxonomies

- **Seamless Declaration**: Declare taxonomies seamlessly via classes in the `{{theme}}/app/Taxonomies` directory.
- **Slug Selection**: Choose slugs effortlessly using the versatile `getSlug` method.
- **Dynamic Associations**: Select associated PostTypes with ease using `getPostTypes`.
- **Configurational Control**: Tailor default configurations effortlessly using `getConfig`.
- **Custom Field Mastery**: Personalize the custom field space title with finesse using `getFieldsTitle`.
- **Enhanced Customization**: Add custom ACF fields effortlessly with `getFields`.
- **Flexible Field Positioning**: Modify or append custom field locations with flair using `getFieldsLocation`.
- **Automatic Registration**: Automatically register classes within the `app/Taxonomies` directory.

### Blocks

- **Effortless Declaration**: Declare blocks effortlessly via classes in the `{{theme}}/app/Blocks` directory, building upon the dynamic `AbstractBlock`.
- **Title Transformation**: Transform block titles dynamically with `getBlockTitle`.
- **Name Customization**: Personalize block names seamlessly with `getBlockName`.
- **ACF Integration**: Seamlessly integrate ACF fields into blocks with ease using `getBlockFields`.
- **Category Flexibility**: Customize block categories effortlessly with `getBlockCategory`.
- **Dynamic Rendering**: Execute code dynamically during block rendering using `renderBlockCallback`.
- **Contextual Enhancement**: Elevate blocks with enhanced context dynamics using `addToContext`.
- **Automatic Registration**: Automatically register classes within the `app/Blocks` directory.
- **Categories:** Populate the `category` property with some default categories from `BlockCategoriesEnum` like `BlockCategoriesEnum::OTHERS->value`

### QueryBuilder

- **Interactive Generation**: Generate `WP_Query` instances interactively with the powerful `QueryBuilder`.
- **PostType Addition**: Seamlessly add PostTypes with the intuitive `postType` method.
- **Page Precision**: Set desired pages with pinpoint precision using `setPage`.
- **Pagination Perfection**: Define pagination effortlessly via `setPerPage` for optimal control.
- **ID Constraint Mastery**: Apply ID constraints effortlessly with `whereIdIn`.
- **Exclusion Elegance**: Exclude IDs effortlessly with `whereIdNotIn`.
- **Result Retrieval**: Retrieve meticulously crafted `WP_Query` instances with ease using `getQuery`.
- **Singular or Null**: Obtain singular results or null if none exist effortlessly with `getOneOrNull`.
- **Meta Magic**: Unlock the power of metadata manipulation with the dynamic `addMetaQuery` method, seamlessly accepting instances of the `MetaQuery` class. 🌟
- **Count:** Retrieve the total count of queries posts by using the `getCount` method
- **Order:** Retrieve posts ordered by default or custom meta, string or numeric with the `orderBy` method

### MetaQuery
- **Dynamic Class**: Harness the power of metadata manipulation with the versatile `MetaQuery` class, empowering your QueryBuilder experience.
- **Nested Query Nirvana**: Seamlessly nest MetaQueries using the `add` method within the `MetaQuery` class, for unparalleled query flexibility.
- **Parametric Precision**: Customize your queries effortlessly with the `add` method, featuring parameters such as:
  - `nameOrMetaQuery`: Key of the meta or an instance of `MetaQuery`.
  - `value`: Value to be tested.
  - `comparator`: Comparator for the query.
  - `type`: Value type for comparison.
- **Relation Refinement**: Fine-tune query relations with the `setRelation` method, defining the `AND` or `OR` aspect of the MetaQuery for enhanced query precision.

### TaxQuery
- **Dynamic Class**: Harness the power of taxonomy manipulation with the versatile `TaxQuery` class, empowering your QueryBuilder experience.
- **Nested Query Nirvana**: Seamlessly nest TaxQueries using the `add` method within the `TaxQuery` class, for unparalleled query flexibility.
- **Parametric Precision**: Customize your queries effortlessly with the `add` method, featuring parameters such as:
  - `taxonomyOrTaxQuery`: Taxonomy name or an instance of `TaxQuery`.
  - `field`: Field to query (`term_id`, `name`, `slug`, etc.).
  - `terms`: String, int or array of term param to be tested.
  - `operator`: Operator for the query (`IN`, `NOT IN`, etc.).
- **Relation Refinement**: Fine-tune query relations with the `setRelation` method, defining the `AND` or `OR` aspect of the TaxQuery for enhanced query precision.

### LatLngQuery

- **Geolocation Queries**: Filter posts by geographic proximity using the `LatLngQuery` class with the `addLatLngQuery` method on QueryBuilder.

### Templates

- **Gutenberg Templates**: Define block templates for post types via classes in `{{theme}}/app/Templates/`, extending `AbstractTemplate`.
- **PostType Mapping**: Associate templates to post types with `getPostTypes()`.
- **Block Composition**: Define the block layout with `getBlocks()`.
- **Automatic Registration**: Automatically registered via `PostTypeServiceProvider`.

### Repositories

- **AbstractRepository**: Base repository class with built-in QueryBuilder integration providing `getAll()`, `getOneBySlug()`, and `getOneById()` methods.
- **AbstractTaxonomyRepository**: Specialized repository for taxonomy queries.
- **Easy Scaffolding**: Generate a new repository with the `make:repository` command.

### ViewModels

- **BasePostViewModel**: Post data with custom fields, featured image, excerpt, reading time, and terms.
- **BaseTermViewModel**: Term data with custom fields.
- **MenuViewModel / MenuItemViewModel**: Menu structure with nested items and custom fields.
- **AssetViewModel**: Compiled asset with URL, path, and integrity hash (Bud/Vite support).

### Menus

- **Dynamic Retrieval**: Retrieve dynamic `MenuViewModel` instances effortlessly.
- **Customized Charm**: Experience custom fields retrieval and child items seamlessly with `MenuItemViewModel`.

### Hooks

- **Classy Hook Handling**: Seamlessly manage hooks in separate classes using `AbstractHook`.
- **Init Method**:  Use the `init` method with calls to `add_filter` or `add_action`.
- **Automatic Registration**: Automatically register classes within the `app/Hooks` directory.

### Commanding with Style

- With Acorn, just whip up a class extending `Roots\Acorn\Console\Commands\Command` within the `App\Console\Commands` namespace, and voilà! You're ready to rock it via Acorn's CLI.

#### Create elements with commands

- **Create a PostType**: Generate a new PostType class with the `make:posttype` command.
- **Create a Taxonomy**: Create a new Taxonomy class with the `make:taxonomy` command.
- **Create a Block**: Craft a new Block class with the `make:block` command.
- **Create an Admin**: Develop a new Admin class with the `make:admin` command.
- **Create a Template**: Design a new Template class with the `make:template` command.
- **Create a Hook**: Construct a new Hook class with the `make:hook` command.
- **Create a Repository**: Build a new Repository class with the `make:repository` command.

#### List elements with commands

- **List PostTypes**: Retrieve a list of all PostTypes with the `list:posttypes` command.
- **List Taxonomies**: Obtain a list of all Taxonomies with the `list:taxonomies` command.
- **List Blocks**: Retrieve a list of all Blocks with the `list:blocks` command.

#### Customize posts

- **Add custom prefix**: Allow to customize posts URL prefix inside `/web/app/{theme}/config/posts.php`

```php
<?php

return [
    'prefix' => 'articles',
];
```

#### Allow new media types

- **Allow and sanitize SVG files**: Allow to enable or disable SVG files on the fly and sanitize them
  - Simply edit the `/web/app/{theme}/config/medias.php` file

```php
<?php

return [
    'allow' => [
        'svg' => false,
    ],
    'sanitize' => [
        'svg' => false,
    ],
];

```

### Custom ACF Fields

Horizon Tools ships with ready-to-use Extended ACF field classes:

- **TextWithTags**: Text input with configurable replacement tags (e.g. `{bold}text{/bold}`). Includes a clickable button toolbar to insert tags. Configured via `config/text.php`.
- **HeadingField**: Heading with tag selection (h2–h6).
- **IconField / FontAwesomeIcon**: Icon pickers.
- **WysiwygField**: WYSIWYG editor with preset toolbars (`default`, `simple`, `minimal`).
- **ImageField / VideoField / MediaField**: Media fields with sensible defaults.
- **ButtonField**: Link/button field.
- **CompositionSelectField**: Select reusable block compositions.
- **FormField**: Select Gravity Forms.
- **PostTypeSelectField / TaxonomySelectField**: Select registered post types or taxonomies.
- **ContentTab / GlobalTab / LayoutTab / MediaTab / SettingsTab**: Pre-configured ACF tab fields.

### Search Engine

A built-in search engine with admin configuration page:

- **Admin Page**: Configure the results page, searchable post types, pagination, header (title, image, breadcrumbs), and SEO meta title via `SearchEngineOptionsAdmin`.
- **Searchable Fields**: PostTypes can expose searchable ACF fields via `getSearchableFields()`.
- **SearchEngineService**: Full-featured search with pagination, filtering by type, excluded posts, and caching.
- **Config**: Registered automatically via `SearchEngineServiceProvider`.

### Share Options

Configurable sharing options with admin page:

- **Admin Page**: Toggle sharing platforms (Copy Link, Email, SMS, WhatsApp, Messenger, ChatGPT, Claude, Perplexity) via `ShareOptionsAdmin`.
- **ShareService**: Retrieve cached share options.
- **Config-gated**: Activate via `config/share.php`. Optionally restrict which services are available with the `services` key:

```php
<?php

return [
    'enable' => true,
    'services' => [
        'copyLink',
        'email',
        'sms',
        'whatsapp',
        'messenger',
        'chatGPT',
        'claude',
        'perplexity',
    ],
];
```

If `services` is omitted or empty, all platforms are available.

### Text Replacement Tags

Configurable tag replacement system for ACF text fields:

- **Custom ACF Field**: `text_with_tags` field type with a clickable tag toolbar.
- **Automatic Replacement**: Tags like `{bold}text{/bold}` are replaced with HTML on output.
- **Config-gated**: Activate via `config/text.php`:

```php
<?php

return [
    'replacements' => [
        'enable' => true,
        'values' => [
            'bold' => [
                'name' => 'Gras',
                'open' => '{bold}',
                'close' => '{/bold}',
                'openReplacement' => '<strong>',
                'closeReplacement' => '</strong>',
            ],
        ],
    ],
];
```

### SEO Automations

- **Multi-plugin Support**: Works with RankMath, Yoast, and SEOPress for title, breadcrumbs, and indexation detection.
- **Auto-add archives in RankMath breadcrumbs**: Automatically add (if able to) archives in RankMath breadcrumbs.
  - To work, you need to add a `pages` Group in your global ACF option fields containing PostObjects where field keys has to be the classname of the PostType.
  - It will then use the retrieved WP_Post instance to compare the rewrite url set in PostType and the WP_Post slug.
  - If they match, it will add the WP_Post to the breadcrumbs.
- **Link Obfuscation**: Obfuscate links in WYSIWYG content with configurable tag, class, and attribute. Includes a TinyMCE toolbar button. Configure via `config/seo.php`:

```php
<?php

return [
    'links' => [
        'obfuscation' => [
            'allow' => true,
            'tag' => 'span',
            'class' => 'obfuscated-link',
        ],
    ],
];
```

### Access restrictions

- **Restrict access with HTTP Basic Auth**: Simply add the following line to the environment configuration file
  - Default ID : `adeliom`
  - Default password : `@deliomYYYY!` _(where `YYYY` is the current year)_

```php
Config::define('ENABLED_HTTP_LOGIN', true);
```

- To change default ID, set the `HTTP_USER` constant
- To change the default password, set the `HTTP_PASSWD` constant

### Back-Office Customization

Customize the WordPress admin appearance via `config/back-office.php`:

- **Login Page Branding**: Custom logo (URL, width, height, border radius, background color), custom URL and title.
- **Main Color**: Automatically extracted from the site icon with brightness adjustment.
- **Footer Text**: Custom admin footer text.
- **User Columns**: Add custom columns to the users list via `config/users.columns`.

### Date Formatting

- **DateService**: French date formatting with Carbon (e.g. "1er janvier 2024").
- **ACF Constants**: Pre-configured display and return format constants for ACF date fields.

### Gravity Forms

- **Confirmation Pages**: Optional post type for Gravity Forms confirmation pages (config-gated).
- **Optional Labels**: Automatically replaces "nécessaire" with "facultatif" on optional fields.
- **FormField**: ACF field to select Gravity Forms in your custom fields.

### WYSIWYG Customization

- **Custom Toolbars**: Three preset toolbar configurations (`default`, `simple`, `minimal`) available via `WysiwygField`.
- **Heading Restriction**: H1 tag removed from TinyMCE heading options.
- **Link Obfuscation Button**: Adds an obfuscation toggle button to the TinyMCE toolbar.

### Asset Compilation

- **BudService**: Bud.js manifest association, URL and path generation.
- **ViteService**: Vite support with hot reload detection and manifest handling.
- **AssetViewModel**: Unified asset representation with URL, path, and integrity hash.

### Services

Horizon Tools includes ~29 service classes in `src/Services/` for common WordPress tasks:

| Service | Purpose |
|---|---|
| `PostService` | Raw text extraction, reading time, post rendering |
| `BlogPostService` | Summary blocks extraction, hierarchical heading extraction |
| `PaginationService` | Pagination data generation with URLs |
| `ImageService` | Image color extraction via League\ColorExtractor |
| `ColorService` | Brightness adjustment, main color from site icon |
| `MediaLibraryService` | Import media from URL with mime type detection |
| `SocialNetworksService` | Social sharing URLs (Facebook, Twitter, LinkedIn, Email) |
| `CompositionService` | Reusable block compositions rendering and choices |
| `EnvironmentService` | Environment detection (production/staging/development) |
| `NumberService` | Price formatting (French format) |
| `StringService` | String truncation, singular/plural, camelCase |
| `ValidationService` | Regex patterns for phone, email |
| `VersionService` | Package version detection with caching |