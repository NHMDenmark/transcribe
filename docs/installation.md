# Installation

This guide installs the platform on a Linux server from nothing, and explains why each step is there, so you can adapt it to your own environment. It has variants for Debian/Ubuntu (`apt`) and RHEL, Rocky and Alma (`dnf`). Anything in `<angle brackets>` is yours to choose.

- [1. What you need](#1-what-you-need)
- [2. Prepare the server](#2-prepare-the-server)
- [3. Get the code and install it](#3-get-the-code-and-install-it)
- [4. Configure the web server](#4-configure-the-web-server)
- [5. Permissions](#5-permissions)
- [6. Behind a reverse proxy](#6-behind-a-reverse-proxy)
- [7. Backups](#7-backups)
- [8. Updating](#8-updating)
- [9. When something does not work](#9-when-something-does-not-work)

## 1. What you need

| | |
|---|---|
| PHP | 8.2 or 8.3 (the libraries this version locks do not support PHP 8.4 yet), with the extensions `mbstring`, `xml` (dom), `curl`, `zip`, `gd`, `mysql` (pdo_mysql), `fileinfo`, `tokenizer`, `openssl` |
| Database | MySQL 8.0 or newer (or a MariaDB that is compatible with it) |
| Composer | 2.x, to install the PHP libraries |
| Node.js and npm | only to build the stylesheet and scripts (`npm run build`); they can be on another machine, see [Updating](#8-updating) |
| Web server | nginx with PHP-FPM, or Apache; the examples use nginx |
| Mail | an SMTP account (and a sender address, `MAIL_FROM_ADDRESS`): people get their password by an e-mail link, so without mail only Google/GitHub sign-in works |
| A Specify 7 server | **optional**: only if the validated data will be sent (synced) to a collection database. The platform works without it |

**Why GD and zip?** Profile photos are resized with GD, and the specimen lists (`.xlsx`) are read with the zip and xml extensions. Without GD the profile photo upload says that photos are not available on the server.

## 2. Prepare the server

**Debian / Ubuntu**

```bash
sudo apt update
sudo apt install -y nginx mysql-server git unzip php-fpm php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd
sudo systemctl enable --now nginx mysql
```

**RHEL / Rocky / Alma**

```bash
sudo dnf module reset php -y && sudo dnf module enable php:8.3 -y
sudo dnf install -y nginx mysql-server git unzip php php-cli php-fpm php-mysqlnd php-mbstring php-xml php-curl php-zip php-gd
sudo systemctl enable --now nginx mysqld php-fpm
```

**Composer** (both):

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
```

**Node.js** is only needed to build the assets. Install a current LTS version from your distribution or from nodesource.com, or skip it and build elsewhere (see [Updating](#8-updating)).

**The database.** Create an empty database and a user that can use only that database:

```bash
sudo mysql
```
```sql
CREATE DATABASE transcription CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'transcription'@'localhost' IDENTIFIED BY '<a-long-random-password>';
GRANT ALL PRIVILEGES ON transcription.* TO 'transcription'@'localhost';
FLUSH PRIVILEGES;
```

Use a random password (for example `openssl rand -base64 24`) and keep it in a password manager: it goes into `.env` in the next step, and nowhere else. Run `sudo mysql_secure_installation` if the database server is new.

## 3. Get the code and install it

```bash
cd /var/www
sudo git clone <repository-url> transcription
sudo chown -R $USER: transcription          # temporarily, to install as yourself
cd transcription
composer install --no-dev --optimize-autoloader
npm install && npm run build                 # creates public/build (it is not in git)
cp .env.example .env
```

Open `.env` and set at least `APP_URL` (the public address, **with https** if the site is served over https), `APP_ENV=production`, `APP_DEBUG=false`, the `DB_*` lines and the `MAIL_*` lines. Every setting is explained in [configuration.md](configuration.md).

Then let the platform prepare itself:

```bash
php artisan platform:install
```

It asks for the name, e-mail and password of the first administrator and does, in order, only what is missing: creates the application key, checks the database connection, creates the tables (`migrate`), adds the base data (a master language, English, the user levels and the texts of the home page), creates the folders the application writes to, and creates the administrator (role `root`, with a confirmed address). It can be run again at any time without harm. For a script: `php artisan platform:install --name="…" --email=… --password=…`, or `--no-admin` to leave the administrator out.

**Why the base data is not just "migrate":** the tables created by `migrate` are empty, and the platform needs a master language and the user levels to start. Those are added by a *seeder*, which is safe to repeat after an update (it only adds what is missing and never overwrites your own changes), whereas a migration runs once.

Now sign in with the administrator and continue in [administration.md](administration.md): upload the institution's logo in **Settings**, add the fields, create a first project.

### The scheduler (cron)

The platform does some work by itself: the weekly ranking, the nightly figures and, if you switch it on, the automatic sync with the collection database. It all runs from Laravel's scheduler, which needs **one cron line**, in the crontab of the **web server user** (so that the files it writes have the right owner):

```bash
sudo crontab -u www-data -e          # nginx on RHEL and similar
```
```cron
* * * * * cd /var/www/transcription && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler decides each minute what is due. **Admin → Platform → Settings** shows when it last ran. Without the line nothing breaks, but the weekly ranking on the home page stays empty and nothing is synced automatically.

## 4. Configure the web server

The web root is the `public` folder, never the project folder (so that `.env` and the source are not reachable). nginx:

```nginx
server {
    listen 80;
    server_name transcription.example.org;

    root /var/www/transcription/public;
    index index.php;

    # specimen lists and images can be large
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Debian/Ubuntu: unix:/run/php/php8.3-fpm.sock    RHEL: unix:/run/php-fpm/www.sock
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
        include fastcgi_params;
    }
}
```

On Debian/Ubuntu put it in `/etc/nginx/sites-available/` and link it into `sites-enabled/`; on RHEL in `/etc/nginx/conf.d/transcription.conf`. Then `sudo nginx -t && sudo systemctl reload nginx`. Add HTTPS with your usual certificate method (for example certbot).

With Apache, point `DocumentRoot` at `public` and enable `mod_rewrite` and `AllowOverride All`: `public/.htaccess` does the rest.

## 5. Permissions

PHP runs as the **web server user** (`www-data` on Debian/Ubuntu, `nginx` on RHEL and similar; see the `user` line of the PHP-FPM pool). That user must be able to write to:

- `storage` and `bootstrap/cache` (logs, sessions, caches)
- `public/avatars`, `public/uploads`, `public/cover_images`, `public/branding` (photos, images and files uploaded in the application; they are created when the first file is uploaded, or by `platform:install`)

```bash
WEB=www-data                                  # or nginx
sudo chown -R $WEB:$WEB storage bootstrap/cache
sudo mkdir -p public/{avatars,uploads,cover_images,branding}
sudo chown -R $WEB:$WEB public/{avatars,uploads,cover_images,branding}
sudo chmod -R 775 storage bootstrap/cache public/{avatars,uploads,cover_images,branding}
```

**Run `artisan` as the web server user** (`sudo -u www-data php artisan …`) once the site is live. Commands that create files (caches, logs, the folders above) then create them with the right owner; run as another user, they leave files the web server cannot write to, and uploads fail afterwards.

**SELinux** (RHEL and similar) adds a second check: the web server may only write to folders that carry the right *label*, even when owner and mode are correct. If uploads fail although the permissions look right:

```bash
sudo dnf install -y policycoreutils-python-utils
for d in storage bootstrap/cache 'public/(avatars|uploads|cover_images|branding)'; do
  sudo semanage fcontext -a -t httpd_sys_rw_content_t "$PWD/$d(/.*)?"
done
sudo restorecon -Rv .
sudo setsebool -P httpd_can_network_connect 1      # outgoing connections: the collection database, Google/GitHub sign-in, mail
sudo ausearch -m avc -ts recent                    # what SELinux refused, right after a failed attempt
```

## 6. Behind a reverse proxy

If another server ends HTTPS and passes the requests on (a common institutional set-up), tell the application so, in `.env`:

```
APP_URL=https://transcription.example.org
TRUST_PROXIES=*
SESSION_SECURE_COOKIE=true
```

- `APP_URL` must be the full public address **with https**. When it starts with `https` every address the application builds (forms, links, redirects) is `https`, even if the proxy does not say so. Without it a form can post to `http://`; a proxy that only accepts https answers with a redirect, the typed data is lost, and a login "does nothing".
- `TRUST_PROXIES` is `*`, or a comma-separated list of the proxy's addresses, so that the headers `X-Forwarded-Proto`, `-Host` and `-Port` are believed.
- If the platform lives under a path (for example `https://example.org/transcription`), include it in `APP_URL`.

After changing `.env`: `sudo -u www-data php artisan optimize:clear`.

## 7. Backups

What needs to be saved: the **database**, the file `.env`, and the folders `public/avatars`, `public/uploads`, `public/cover_images`, `public/branding` and `storage/app`. The code itself is in git.

Keep the database password out of the command line and of cron: put it in an options file only you can read.

```ini
# ~/.transcription.cnf   (chmod 600)
[client]
user=transcription
password=<the-database-password>
```

```bash
# /usr/local/bin/transcription-backup.sh
#!/bin/sh
set -e
DEST=/var/backups/transcription
mkdir -p "$DEST"
mysqldump --defaults-extra-file=$HOME/.transcription.cnf --single-transaction transcription | gzip > "$DEST/db-$(date +%F).sql.gz"
tar czf "$DEST/files-$(date +%F).tar.gz" -C /var/www/transcription .env public/avatars public/uploads public/cover_images public/branding storage/app
find "$DEST" -type f -mtime +30 -delete          # keep a month
```

```cron
30 23 * * * /usr/local/bin/transcription-backup.sh >> /var/log/transcription-backup.log 2>&1
```

Copy the backups to another machine, and restore one now and then to a test database to know that it works: `gunzip < db-….sql.gz | mysql transcription_test`.

The scheduler writes nothing that needs saving of its own: the weekly rankings and the figures live in the database.

## 8. Updating

1. **Back up the database first**, because an update can include migrations (section 7).
2. Get the new code: `git pull` (or copy the new files).
3. Read the notes of the version in [CHANGELOG.md](../CHANGELOG.md): they say when something needs a manual step.
4. Update libraries, tables and caches:

```bash
composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=BaseDataSeeder --force   # adds texts introduced by the new version; changes nothing of yours
sudo -u www-data php artisan optimize:clear
```

5. Rebuild the stylesheet and scripts **only if** the update changed `resources/sass`, `resources/js` or `package.json` (the CHANGELOG says so). `public/build` is not in git. On a server without Node, build on another machine with the same version of the code and copy the `public/build` folder:

```bash
npm install && npm run build
```

6. Check that the [scheduler](#the-scheduler-cron) is in place (a version that adds scheduled work says so in the CHANGELOG).
7. Apply the [permissions](#5-permissions) again if files were copied or pulled by another user than the web server user.
8. Check: sign in, open a project, upload a profile photo. If something fails, the last lines of `storage/logs/laravel.log` say why.

## 9. When something does not work

| What you see | Usually |
|---|---|
| A login that does nothing, or a "page expired" | `APP_URL` is not `https://…` behind a proxy that forces https, or `TRUST_PROXIES` is empty (section 6) |
| An upload fails, a photo does not save | the web server user cannot write to the folder, or SELinux refuses (section 5); `storage/logs/laravel.log` names the folder |
| Error 500 and no message | `storage` or `bootstrap/cache` is not writable; set `APP_DEBUG=true` for a minute on a private server, never in public |
| `composer install` says your PHP version does not satisfy a package | PHP 8.4 or newer: use PHP 8.2 or 8.3 (section 2) |
| Asking for a new password gives error 500 ("An email must have a From") | `MAIL_FROM_ADDRESS` is empty in `.env` |
| The pages have no style | `public/build` is missing: `npm install && npm run build` |
| "Class … not found" after an update | `composer install` was not run |
| Settings says the scheduler is *Not running*, the weekly ranking is empty | the cron line is missing, or runs as a user that cannot write to `storage` ([the scheduler](#the-scheduler-cron)); test it by hand: `sudo -u www-data php artisan schedule:run` |
| Mail never arrives | the `MAIL_*` lines; test with `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('you@example.org')->subject('test'));` |
| The sync to the collection database fails | see "Sync with Specify" in [administration.md](administration.md) |
