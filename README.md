# Introducing WordPress Sage Tools 🚀
---
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