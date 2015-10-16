# silverstripe-partial-themes

A module for managing themes a little more flexibly; by applying a theme to a subtree of a site, by specifying just a partial set of templates to make up a 'theme', and by allowing code be bound to the theme implementation. 

## Partial Themes

Allows for the creation of themes that may override only a part of the 'main' theme. For example, assume you have /themes/simple bound as your main project theme, you can specify another theme alongside this which changes a single template. So assuming you wanted to create another theme, eg /themes/simplesimon that only differed in the way products were displayed, rather than specifying a whole new theme, you would provide _just_ the required template. 

```
/themes/simple
/themes/simple/templates
/themes/simple/templates/Includes
/themes/simple/templates/Includes/Footer.ss
/themes/simple/templates/Includes/SideBar.ss
/themes/simple/templates/Includes/Navigation.ss
/themes/simple/templates/Includes/SidebarMenu.ss
/themes/simple/templates/Includes/Header.ss
/themes/simple/templates/Includes/BreadCrumbs.ss
/themes/simple/templates/Page.ss
/themes/simple/templates/Layout
/themes/simple/templates/Layout/ProductPage.ss
/themes/simple/templates/Layout/Page_results.ss
/themes/simple/templates/Layout/Page.ss
/themes/simplesimon/templates/Layout/ProductPage.ss
```

Then, inside the CMS, the 'Partial theme' setting field would be set to 'simplesimon'. 

## Theme Helpers

Rather than specifying theme specific code in the page controller init method, the module looks for a `{ThemeName}Helper` class to call for before init and after init. 

## Configuration

```

Page:
  extensions:
    - PartialThemesExtension

Controller:
  extensions:
    - PartialThemesExtension

```

## Project specific requirements

You must have the following defined for the controller(s) that will use partial themes; in particular, the
Page_Controller class


```php

    public function getViewer($action) {
        $viewer = parent::getViewer($action);
        $viewer = $this->overridePartialTemplates($viewer, array(), $action);
        return $viewer;
    }
```

## Usage

Once enabled, Theme settings are available on a page's Settings tab.
