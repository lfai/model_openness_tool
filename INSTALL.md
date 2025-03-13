## Prerequisites

The MOT is built on [Drupal](https://www.drupal.org). You need to have [PHP](https://www.php.net) and [composer](https://getcomposer.org/) installed.


## Clone the repository

```shell
git clone <repo-url> mot
cd mot
```

## Install dependencies

```shell
composer install
```

## Local development environment

The production environment uses an external database which requires special settings but for development purposes you can simply use the built-in default Drupal sql.lite setup which is enough to test code changes locally.

Here are some simple steps to get you started:

Create an empty `.env` file:
```shell
touch .env
```

Start MOT:
```shell
php web/core/scripts/drupal quick-start
```

A browser window should open with the MOT website. The database is empty though.

*IMPORTANT NOTE*: When submitting PRs from this setup be careful not to include the `web/sites/default/settings.php` file.

## Populate database with Models

Once MOT is up & running, run this command to populate the database with models.
```shell
vendor/bin/drush scr scripts/sync_models.php
```

## Advanced/Production Settings

### Create .env file

Define these environment variables in the .env file:
```
DB_HOST=
DB_USER=
DB_PASS=
DB_NAME=
DB_PORT=
HASH_SALT=
TRUSTED_HOST=
```

### Environment variables:

- `DB_HOST`: The hostname or IP address of your database server.
- `DB_USER`: The username for your database.
- `DB_PASS`: The password for your database user.
- `DB_NAME`: The name of your database.
- `DB_PORT`: The port number your database server is listening on (default is usually `3306` for MySQL).
- `HASH_SALT`: A unique, random string used for securing passwords and other sensitive data in Drupal. This should be a long and complex string.
- `TRUSTED_HOST`: The host used to access the MOT; e.g. `mot\.isitopen\.ai`


### Configure webserver

Ensure the document root is pointed to the `mot/web` directory.

Below is an example of what an Apache virtual host configuration may look like:

```
<VirtualHost *:80>
    ServerName mot.local
    DocumentRoot /path/to/your/repo/mot/web
    <Directory /path/to/your/repo/mot/web>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/mot-error.log
    CustomLog ${APACHE_LOG_DIR}/mot-access.log combined
</VirtualHost>
```

### Install Drupal/ MOT

Visit the site in your browser (e.g., `http://mot.local`) to complete the MOT installation process.

Follow the on-screen instructions.
