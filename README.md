Yii HTML cache
==============

Yii HTML Cache generates cache file with FULL HTML of your action from `DOCTYPE` to `</html>`.


# Instalation

Copy `htmlcache` folder to your `protected/extensions`

Add following to your `components` configs
 
```php
    'htmlcache' => array(
        'class' => 'ext.htmlcache.HtmlCache',
        'lifeTime' => 60*60*24, // 1 day in seconds
        'extra_params' => array(),
        'disabled' => false,
        'excluded_actions' => array(),
        'excluded_params' => array(),
    ),
```

# Usage

Add following code at beginning of `beforeAction` method in your controller

```php
Yii::app()->htmlcache->loadFromCache($this, $action);
```

Where `$action` is a first parameter of `beforeAction`. Or if you don't have `beforeAction` add following code to your controller

```php
/**
 * @param CInlineAction $action
 * @return bool
 */
protected function beforeAction($action)
{
    Yii::app()->htmlcache->loadFromCache($this, $action);
 
    return parent::beforeAction($action);
}
```

Add following code at end of `afterRender` method in your controller

```php
$output = Yii::app()->htmlcache->saveToCache($this, $this->action, $output);
```

Where `$output` is a second parameter of `afterRender`. Or if you don't have `afterRender` add following code to your controller

```php
/**
 * @param string $view
 * @param string $output
 */
public function afterRender($view, &$output){
    $output = Yii::app()->htmlcache->saveToCache($this, $this->action, $output);
}
```


# Settings

* **lifeTime** - lifetime of generated cache in seconds. _Default: 1 day_
* **extra_params** - parameters in controller that can affects on your final HTML. For example if you have action with product description and it variate for different `id_product` add `extra_params => array('id_product')` to configs
* **disabled** - `true` if disabled, `false` if enabled. _Default: false_
* **excluded_actions** - Actions list what don't need to be cached in `array('controller_id'=> array('action1', 'action2'))` format.
* **excluded_params** - Params list what don't need to be cached if exists and equal to exact value. If value not set then checking to not be a false.
 
> NOTE 1: in `excluded_params` you need to store Controller variables, not `$_GET/$_POST/$_REQUEST` variables

> NOTE 2: you can add excluded action or parameter from controller by calling `excludeActions` and `excludeParams` methds. See **Additional functionality** section

# Additional functionality

There is a few additional methods you can use in your controller.

## directReplace

If you have some parts in your HTML what need to be loaded dynamicly in cache you can use placeholders in view and then replace it with `directReplace` method.

### Params

There are two posibilities for parameters

* string `$replace_key` - key what need to be replaced
* string `$value` - HTML part what need to be placed instead of placeholder

**OR**

* array `$replace_array` - array with `$replace_key => $value` 

### Usage

In view

```HTML
<div>{DATE_PLACEHOLDER}</div>
```

In `beforeAction` method of your controller

```PHP
    Yii::app()->htmlcache->directReplace("DATE_PLACEHOLDER", date("Y-m-d H:i:s"));
    
    // OR
    
    Yii::app()->htmlcache->directReplace(array("DATE_PLACEHOLDER"=> date("Y-m-d H:i:s")));
```

> NOTE: placeholder always need to be UPPERCASE and in {BRACES}

## excludeActions

Points what action do not need to be cached.
 
### Params

* CController  `$controller` - Controller of actions
* string|array `$actions` - name of action or list of actions

### Usage

In `beforeAction` method of your controller

```PHP
    Yii::app()->htmlcache->excludeActions($this, "my_action");
    
    // OR
    
    Yii::app()->htmlcache->excludeActions($this, array("my_action", "my_other_action"));
```

## allowActions

Removes action from excluded actions list.
 
### Params

* CController  `$controller` - Controller of actions
* string|array `$actions` - name of action or list of actions

### Usage

In `beforeAction` method of your controller

```PHP
    Yii::app()->htmlcache->allowActions($this, "my_action");
    
    // OR
    
    Yii::app()->htmlcache->allowActions($this, array("my_action", "my_other_action"));
    
    // OR
    
    Yii::app()->htmlcache->allowActions($this, null); // Removes all actions of this controller
```

## excludeParams

Points what params in this action with some values do not need to be cached
 
### Params

* CController  `$controller` - Controller of actions
* string|array `$params` - name of param or list of params

### Usage

In `beforeAction` method of your controller

```PHP
    Yii::app()->htmlcache->excludeParams($this, "my_action"); // Disable cache if $this->my_action != false
    
    // OR
    
    Yii::app()->htmlcache->excludeParams($this, array("my_action" => 1)); // Disable cache if $this->my_action == 1
    
    // OR
    
    Yii::app()->htmlcache->excludeParams($this, array("my_action" => array(1, 2))); // Disable cache if $this->my_action == 1 OR $this->my_action == 2
```

## allowParams

Removes params from list of excluded params
 
### Params

* CController  `$controller` - Controller of actions
* string|array `$params` - name of param or list of params

### Usage

In `beforeAction` method of your controller

```PHP
    Yii::app()->htmlcache->allowParams($this, "my_action"); // Removes all mentions of my_action of this controller if exsists
    
    // OR
    
    Yii::app()->htmlcache->allowParams($this, array("my_action" => 1)); // Removes my_action == 1 mention from this controller if exsists
    
    // OR
    
    Yii::app()->htmlcache->allowParams($this, array("my_action" => array(1, 2))); // Removes my_action == 1 )R my_action == 1 mentions from this controller if exsists
    
    // OR
    
    Yii::app()->htmlcache->allowParams($this, null); // Removes ALL excluded params of this controller
```