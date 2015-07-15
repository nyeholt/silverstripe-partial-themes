# silverstripe-partial-themes
Allows for the creation of themes that may override only a part of the 'main' theme, eg one template


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