A Laravel package of Easy APNS

* This is a very early beta. Use at your own risk. Please report any bugs *

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

The following command runs the migration to your database

    $ php artisan migrate --package="keithslater/easyapns"

This command copies the config file to app/config/packages/keithslater/easyapns/config.php

    $ php artisan config:publish keithslater/easyapns

Upload your development and production .pem files to app/config/packages/keithslater/easyapns/

Modify app/config/packages/keithslater/easyapns/config.php as needed

## Usage

Add the following to the header where you are calling APNS

```php
use \Keithslater\Easyapns\Easyapns;
```

Then you will be able to use Easy APNS like normal:

```php
$apns = new Easyapns();
$apns->newMessage(1);
$apns->addMessageAlert('Test message sent');
$apns->queueMessage();
$apns->processQueue();
```

Refer to [Easy APNS](http://www.easyapns.com/) for more information.
