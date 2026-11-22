# Transcription Platform

A web platform where volunteers and staff transcribe the labels of natural history specimens from images, several people independently for each specimen. Values that enough of them agree on are accepted as valid, and the validated data can be exported or written into a collection database (Specify 7) through its REST API. Built with Laravel.

## What it does

- **Collaborative transcription with consensus.** Each submission is weighted by the submitter's level; identical answers add up, and a value becomes valid when they reach the threshold. Contributors are promoted by validated work.
- **Flexible fields.** Text, text area, select, radio, checkboxes and date, with translatable options, suggestions, multiple values per specimen, multi-part values, doubts and "deducted" flags, minimum levels.
- **Projects.** A project is a set of specimens (a spreadsheet of identifiers and image addresses) transcribed with a chosen set of fields, shown all at once or one field at a time.
- **Sync with Specify 7.** Schema-aware field mapping (look up, write, follow a relation), date precision, conflict detection with a human decision before anything is overwritten, a dry run before the first sync.
- **Export** of submissions and validated values (CSV, JSON).
- **Multilingual**, with every text editable in place by administrators, in every language.
- **A small website around it**: menu, pages, footer, managed in the application, with publication dates.
- **Roles and access**: root, administrator, project manager, user; visitors can be allowed to browse or to transcribe anonymously; accounts are created by administrators (who send a link to set the password), through Google or GitHub sign-in, or by people themselves if an administrator switches that on; accounts can be deleted (the contributions are kept, anonymised).
- **Independent of any institution**: logo, name, languages, levels and texts are all set in the application.

## Install

PHP 8.2 or 8.3, MySQL 8+ (MariaDB 10.4+ also works), Composer, and Node.js (only to build the stylesheet).

```bash
git clone <repository-url> transcription && cd transcription
composer install --no-dev --optimize-autoloader
npm install && npm run build
cp .env.example .env            # then set APP_URL, DB_* and MAIL_*
php artisan platform:install    # key, tables, base data, folders and the first administrator
```

Point the web server at the `public` folder. The complete guide, with the server preparation, permissions, SELinux, reverse proxy, backups and updates, is in **[docs/installation.md](docs/installation.md)**.

## Documentation

| | |
|---|---|
| [docs/installation.md](docs/installation.md) | Installing and updating on a server |
| [docs/configuration.md](docs/configuration.md) | `.env`, settings, languages, user levels, roles, sign-in providers, the Specify connection |
| [docs/administration.md](docs/administration.md) | How it works and how to run it: fields, projects, validation, sync, people, the website |
| [docs/transcribing.md](docs/transcribing.md) | A guide for contributors |
| [docs/development.md](docs/development.md) | Working on the code: tests, the build, conventions |
| [CHANGELOG.md](CHANGELOG.md) | What changed in each version, and the manual steps of an update |

The administration and contributor guides are also inside the application, under **Help** in the user menu.

## License

Licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE).
