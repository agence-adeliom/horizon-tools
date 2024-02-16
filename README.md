# Introducing WordPress Sage Tools 🚀

Unleash the power of WordPress with WordPress Sage Tools, your ultimate toolkit for elevating your development experience!
## Easy Installation

Get started with WordPress Sage Tools in just a few simple steps:

1. **Add Repository**: Incorporate our package into your project by adding our repository to your `composer.json`.
```json
{
  "type": "vcs",
  "url": "git@github.com:LucasVigneron/sage-tools.git"
}
```

2. **Run Composer**: Execute the following command in your terminal to install WordPress Sage Tools:

```bash
composer require lucas-vigneron/sage-tools
```

3. **Voilà!** 🎉 WordPress Sage Tools is now seamlessly integrated into your project, ready to supercharge your development process.

## Unleash All Features

Unlock the full potential of WordPress Sage Tools by ensuring your project meets these requirements:

- WordPress BedRock
- Sage theme
- Acorn installed

Once you've met these prerequisites, head to your template folder's `composer.json` file. Confirm that the `extra` section includes these providers:

```json
{
	"extra": {  
	  "acorn": {  
	    "providers": [  
		      ...
		      "LucasVigneron\\SageTools\\Providers\\AdminServiceProvider",  
		      "LucasVigneron\\SageTools\\Providers\\BlockServiceProvider",  
		      "LucasVigneron\\SageTools\\Providers\\CommentsServiceProvider",  
		      "LucasVigneron\\SageTools\\Providers\\PostTypeServiceProvider"  
		    ]
	    }  
	}
}

```

Experience the magic of WordPress Sage Tools and revolutionize your WordPress development workflow today! 🌟

## Power-Packed Features Showcase 💥

### PostTypes

- **Easy Declaration**: Define PostTypes effortlessly using classes in the `{{theme}}/app/PostTypes` directory, building upon `AbstractPostType`.
- **Slug Customization**: Personalize slugs effortlessly via the `getSlug` method.
- **Tailored Configuration**: Fine-tune base configurations using an array with the `getConfig` method.
- **Field Flexibility**: Rearrange field positions effortlessly with `getFieldsPosition`.
- **Streamlined Editing**: Add fields directly to the Post edit sidebar using the dynamic `getFields` method (powered by Vinkla).
- **Field Location Control**: Customize or add field locations seamlessly with `getFieldsLocation` for enhanced flexibility.

### Taxonomies

- **Seamless Declaration**: Declare taxonomies seamlessly via classes in the `{{theme}}/app/Taxonomies` directory.
- **Slug Selection**: Choose slugs effortlessly using the versatile `getSlug` method.
- **Dynamic Associations**: Select associated PostTypes with ease using `getPostTypes`.
- **Configurational Control**: Tailor default configurations effortlessly using `getConfig`.
- **Custom Field Mastery**: Personalize the custom field space title with finesse using `getFieldsTitle`.
- **Enhanced Customization**: Add custom ACF fields effortlessly with `getFields`.
- **Flexible Field Positioning**: Modify or append custom field locations with flair using `getFieldsLocation`.

### Blocks

- **Effortless Declaration**: Declare blocks effortlessly via classes in the `{{theme}}/app/Blocks` directory, building upon the dynamic `AbstractBlock`.
- **Title Transformation**: Transform block titles dynamically with `getBlockTitle`.
- **Name Customization**: Personalize block names seamlessly with `getBlockName`.
- **ACF Integration**: Seamlessly integrate ACF fields into blocks with ease using `getBlockFields`.
- **Category Flexibility**: Customize block categories effortlessly with `getBlockCategory`.
- **Dynamic Rendering**: Execute code dynamically during block rendering using `renderBlockCallback`.
- **Contextual Enhancement**: Elevate blocks with enhanced context dynamics using `addToContext`.

### QueryBuilder

- **Interactive Generation**: Generate `WP_Query` instances interactively with the powerful `QueryBuilder`.
- **PostType Addition**: Seamlessly add PostTypes with the intuitive `addPostType` method.
- **Page Precision**: Set desired pages with pinpoint precision using `setPage`.
- **Pagination Perfection**: Define pagination effortlessly via `setPerPage` for optimal control.
- **ID Constraint Mastery**: Apply ID constraints effortlessly with `addIdIn`.
- **Exclusion Elegance**: Exclude IDs effortlessly with `addIdNotIn`.
- **Result Retrieval**: Retrieve meticulously crafted `WP_Query` instances with ease using `getQuery`.
- **Singular or Null**: Obtain singular results or null if none exist effortlessly with `getOneOrNull`.

### Menus

- **Dynamic Retrieval**: Retrieve dynamic `MenuViewModel` instances effortlessly.
- **Customized Charm**: Experience custom fields retrieval and child items seamlessly with `MenuItemViewModel`.