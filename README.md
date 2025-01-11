# XlsView Plugin

Quickly enable XLS output of your model data.

This branch is for CakePHP **5.x**. 

## Background

I needed to quickly export XLS of stuff in the database. Using a view class to
iterate manually would be a chore to replicate for each export method, so I
figured it would be much easier to do this with a custom view class,
like JsonView or XmlView.
This Repo is Based on the work of https://github.com/FriendsOfCake/cakephp-Csvview/

## Installation

```
composer require impronta48/cakephp-xlsview
```

### Enable plugin

Load the plugin by running command

    bin/cake plugin load XlsView

## Usage

To export a flat array as a Xls, one could write the following code:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('XlsView.xls')
        ->setOption('serialize', 'data');
}
```

All variables that are to be included in the Xls must be specified in the
`serialize` view option, exactly how `JsonView` or `XmlView` work.

It is possible to have multiple variables in the Xls output:

```php
public function export()
{
    $data = [['a', 'b', 'c']];
    $data_two = [[1, 2, 3]];
    $data_three = [['you', 'and', 'me']];

    $serialize = ['data', 'data_two', 'data_three'];

    $this->set(compact('data', 'data_two', 'data_three'));
    $this->viewBuilder()
        ->setClassName('XlsView.Xls')
        ->setOption('serialize', $serialize);
}
```

If you want headers or footers in your Xls output, you can specify either a
`header` or `footer` view option. Both are completely optional:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $header = ['Column 1', 'Column 2', 'Column 3'];
    $footer = ['Totals', '400', '$3000'];

    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('XlsView.Xls')
        ->setOptions([
            'serialize' => 'data',
            'header' => $header,
            'footer' => $footer,
        ]);
}
```


If you have complex model data, you can use the `extract` view option to
specify the individual [`Hash::extract()`-compatible](http://book.cakephp.org/4/en/core-libraries/hash.html) paths
or a callable for each record:

```php
public function export()
{
    $posts = $this->Posts->find();
    $header = ['Post ID', 'Title', 'Created'];
    $extract = [
        'id',
        function (array $row) {
            return $row['title'];
        },
        'created'
    ];

    $this->set(compact('posts'));
    $this->viewBuilder()
        ->setClassName('XlsView.Xls')
        ->setOptions([
            'serialize' => 'posts',
            'header' => $header,
            'extract' => $extract,
        ]);
}
```

#### Automatic view class switching

You can use the controller's content negotiation feature to automatically have
the XlsView class switched in as follows.

Enable `Xls` extension parsing using `$routes->addExtensions(['xls'])` within required
scope in your app's `routes.php`.

```php
// PostsController.php

// Add the XlsView class for content type negotiation
public function initialize(): void
{
    parent::initialize();

    $this->addViewClasses(['xls' => 'XlsView.Xls']);
}

// Controller action
public function index()
{
    $posts = $this->Posts->find();
    $this->set(compact('posts'));

    if ($this->request->is('xls')) {
        $serialize = 'posts';
        $header = array('Post ID', 'Title', 'Created');
        $extract = array('id', 'title', 'created');

        $this->viewBuilder()->setOptions(compact('serialize', 'header', 'extract'));
    }
}
```

With the above controller you can now access `/posts.xls` or use `Accept` header
`text/xls` to get the data as xls and use `/posts` to get normal HTML page.

For really complex Xlss, you can also use your own view files. To do so, either
leave `serialize` unspecified or set it to null. The view files will be located
in the `xls` subdirectory of your current controller:

```php
// View used will be in templates/Posts/xls/export.php
public function export()
{
    $posts = $this->Posts->find();
    $this->set(compact('posts'));
    $this->viewBuilder()
        ->setClassName('XlsView.xls')
        ->setOption('serialize', null);
}
```

#### Setting the downloaded file name

By default, the downloaded file will be named after the last segment of the URL
used to generate it. Eg: `example.com/my-controller/my-action` would download
`my-action.xls`, while `example.com/my-controller/my-action/first-param` would
download `first-param.xls`.

> In IE you are required to set the filename, otherwise it will download as a text file.

To set a custom file name, use the `Response::withDownload()` method. The following
snippet can be used to change the downloaded file from `export.xls` to `my-file.xls`:

```php
public function export()
{
    $data = [
        ['a', 'b', 'c'],
        [1, 2, 3],
        ['you', 'and', 'me'],
    ];

    $this->setResponse($this->getResponse()->withDownload('my-file.xls'));
    $this->set(compact('data'));
    $this->viewBuilder()
        ->setClassName('XlsView.xls')
        ->setOption('serialize', 'data');
}
```

#### Using a specific View Builder

In some cases, it is better not to use the current controller's View Builder
`$this->viewBuilder()` as any call to `$this->render()` will compromise any
subsequent rendering.

For example, in the course of your current controller's action, if you need to
render some data as Xls in order to simply save it into a file on the server.

Do not forget to add to your controller:

```php
use Cake\View\ViewBuilder;
```
So you can create a specific View Builder:

```php
// Your data array
$data = [];

// Create the builder
$builder = new ViewBuilder();
$builder
    ->setLayout(false)
    ->setClassName('XlsView.Xls')
    ->setOptions(compact('serialize'));

// Then the view
$view = $builder->build($data);
$view->set(compact('data'));

// And Save the file
file_put_contents('/full/path/to/file.xls', $view->render());
```

