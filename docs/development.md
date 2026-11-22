# Working on the code

## Local set-up

```bash
composer install
npm install
cp .env.example .env
php artisan platform:install     # creates the key, the tables, the base data and an administrator
npm run dev                      # the stylesheet and scripts, rebuilt as you edit (or npm run build once)
php artisan serve
```

Create the database first (an empty one, `DB_DATABASE` in `.env`). `platform:install` can be run again at any time.

## Tests

The tests run against a **dedicated MySQL database that is emptied on every run**, so never point them at a real one.

```bash
cp .env.testing.example .env.testing        # set DB_DATABASE, DB_USERNAME, DB_PASSWORD of a throw-away database
php artisan test                            # or: vendor/bin/phpunit
```

PHP needs the `gd` extension for the tests of profile photos (it is off in some PHP installations: `php -d extension=gd vendor/bin/phpunit` turns it on for one run). The suite is large: run one file or one test while you work, `vendor/bin/phpunit tests/Feature/HelpTest.php` or `--filter name_of_the_test`, and the whole of it before you push.

Conventions that the tests rely on:

- **Feature tests build their own data** (a language, a level, a user) and use `RefreshDatabase`; the base data seeder is not run for them unless a test asks for it.
- Several services keep their data in memory for the duration of a request (the texts of a language, the shared view data). A test that changes a text and then reads it again should use a **fresh request**, and a test that changes users should sign the previous one out first.
- **The collection database is never reached by a test.** The sync is tested against a small stand-in for Specify, `tests/Support/fake_specify.php`, started by the test with PHP's built-in web server on a free port (see `AutomaticSyncTest`, which starts it in `specify()` and stops it in `tearDown()`). The test describes what the server holds and how it behaves in a JSON state file (the records, a data model, a refused login, a refused collection, an error on a write…); the file's own header lists what it understands. To test a new situation, add it there and read the result back from the state (`logins`, `puts`). Real HTTP and real cookies are used, so what is tested is what the sync does on the wire, not a mocked method.
- **A test never uses the credentials or the address of a real installation.** The connection is set in the test (`config(['services.specify.url' => …])`), whatever `.env` says. If you try the sync by hand against a real Specify, do it from your own `.env` and not in a test.
- A test that starts a process must stop it in `tearDown()`, otherwise it is left running and can hold the test run: if a run hangs, look for a leftover `php -S` process.
- Dates are tested with `Carbon::setTestNow(…)` (and reset in `tearDown()`), never with the clock: the schedule of the automatic sync and the weekly ranking depend on it.
- `FreshInstallTest` also fails if a word that identifies an institution, a server or a person appears in the code, the documents or the example environment files: **keep the software free of any institution's name, address or credentials**.

## The build

`public/build` is not in git. It is produced by `npm run build` (Vite) from `resources/sass` and `resources/js`; only a change in those, or in `package.json`, needs a new build. The brand colour is `$primary` in `resources/sass/_variables.scss`.

## Where things are

| | |
|---|---|
| `config/roles.php` | roles and what each may do (capabilities); the routes use them as `can:…` |
| `config/access.php` | what visitors who are not signed in may reach, by access mode |
| `config/transcription.php` | transcription defaults |
| `app/helpers.php` | the text helpers (`get_dialogs`, the edit pencil), the field-view resolution, the Specify schema reader |
| `app/Services/` | texts of languages, the website menu and footer, anonymising accounts, comparing values |
| `app/Http/Controllers/ApiController.php` | the sync with Specify (`syncRecords()` is what the manual page and the automatic sync both call) |
| `app/Services/SyncRunner.php`, `AutoSyncSchedule.php`, `RootLookup.php` | the automatic sync and its history, when a project is due, and how the record to write into is found |
| `app/Console/Kernel.php` | everything that runs from the scheduler (the cron line): the heartbeat, the weekly ranking, the nightly figures, the sync |
| `app/Http/Controllers/ValidationController.php` | consensus validation, validated values, export |
| `database/seeders/BaseDataSeeder.php` | what a new installation starts with |
| `app/Console/Commands/InstallPlatform.php` | `php artisan platform:install` |

## Texts of the interface

Almost all visible text goes through `get_dialogs('Some label', $language)`: the **English label is the identifier**, so a text with no stored translation shows as that English label. A new label in the code needs no migration; administrators translate it with the pencils. Only text that is not a readable label (long messages, for example) needs a row in `BaseDataSeeder::DIALOGS`.
