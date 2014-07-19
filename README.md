Gtf.php
=======

Gtf.php is an intrinsic PHP web framework. The framework didn't try to change the way you think. We just provided a set of convensions and helper classes/functions to maintain the consistency.

At present, the framework drafted some convension about how view will be displayed and how services are accessed. We are preasant to hear from you!

To use...
---------

The usage is very simple. Just present a file named 'index.php'(or other files that you like) to bootstrap the whole helper classess/functions.

```php
require('gtf.php');

poweredByGtf(array(
  'viewDir' => 'view',
  'dbConfig' => 'db.php'
));
```

The `viewDir` is the folder that contains the view layer of the website. You can use relative path or absolute path. Parameter `dbConfig` is the file that configures the database.

A sample `dbConfig` could be found here:

```php
<?php if (!defined('GTF_PHP')) die(); return (object) array(

  'dsn' => 'sqlite:test.db',
  'username' => '',
  'password' => ''

);
```

This configuration will use a embedded SQLite database which is shipped with PHP release and use for convenience. The framework follow the convension of [PDO](http://cn2.php.net/manual/en/class.pdo.php). Of couse you have to prepare the database. :)

In the `viewDir`, design a template for some similar pages. Here we named it 'base.php'. 

```php
<?php if(!defined('GTF_PHP') die(); ?>
<!doctype html>
<html>
  <head>
    <title><?php Tpl::usePart('title') ?></title>
  </head>
  <body>
    <?php Tpl::usePart('body') ?>
  </body>
</html>
```

In this template, we drafted a basic html file with two placeholder `title` and `body`. Then write a page to display some data and fill in the template. We named the file 'hello.php'.

```php
<?php if (!defined('GTF_PHP')) die(); ?>
<?php $data = Stq::query('select * from test'); ?>

<?php Tpl::base('base.php') ?>

<?php Tpl::part('title') ?>
Hello, these are the data.
<?php Tpl::part() ?>

<?php Tpl::part('body') ?>
<h1>Data</h1>
<ul>
  <?php foreach ($data as $datum): ?>
  <li><?= $datum['name'] ?></li>
  <?php endforeach; ?>
</ul>
<?php Tpl::part() ?>
```

`Stq::query(...)` is a wrapper around the PDO object. It will help you take care of the database connection and return the result dataset of the specific SQL. Other part of the file just fill in the parts of the base template specified by `Tpl::base(...)`.

Finally, start your server like Apache HTTPd, and then open a web browser with URL [http://localhost/index.php/hello](http://localhost/index.php/hello) and test it.

To explore more about the framework, see the source or [wiki](https://github.com/yfwz100/gtf.php/wiki). Enjoy the journey. :)

About
-----

The framework was initially started by [yfwz100](http://github.com/yfwz100).
