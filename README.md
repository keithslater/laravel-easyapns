A Laravel package of Easy APNS

Still in development. DO NOT USE

- **Author:** Keith Slater
- **Website:** [http://www.keithslater.com](http://www.keithslater.com)

Code ported from [Easy APNS](http://www.easyapns.com/)

## Installation Instructions

Add the following to your composer.json file

```json
"keithslater/easyapns": "dev-master"
```

Then run composer update

After the project is updated, add the following to your app.php file:

```php
'providers' => array(
    'Keithslater\Easyapns\EasyapnsServiceProvider',
);
```

Run

    $ php artisan migrate --package="keithslater/easyapns"

Run

    $ php artisan config:publish keithslater/easyapns

Upload .pem files to app/config/packages/keithslater/easyapns/

Modify app/config/packages/keithslater/easyapns/config.php

