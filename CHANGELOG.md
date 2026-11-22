# Changelog

All notable changes to this project are documented here.

---

## [1.0.0] — 2026-11-22

### Added

**Installation and documentation**
- `php artisan platform:install`: prepares a new installation in one go (`.env`, application key, database connection check, tables, base data, writable folders, first administrator). Safe to run again.
- `BaseDataSeeder`: the base data a new installation needs (master language, user levels, home page texts). It only adds what is missing, so `php artisan db:seed --class=BaseDataSeeder --force` after an update brings the texts of the new version without touching yours.
- `docs/`: installation, configuration, administration, contributor and development guides. The contributor, administration and configuration guides are also inside the application, under **Help** in the user menu, each for the roles it is meant for.
- `.env.testing.example`.
- Small **?** hints next to the settings where a decision is taken (field form, project form, Settings, user levels, upload), with a link to the section of the guide that explains the logic; the texts are editable and translatable with the pencils.

**Website and administration**
- Website management: menu (groups, pages, links; publish and archive dates; members only), pages with several contents, footer blocks, a server file browser for the editor, English labels editable in place in every language with the edit-mode pencils (menus and CMS texts included).
- Field options as rows (a saved value and a label per language), checkboxes with a separator, a form that shows only what applies to each type.
- Field view per user and per project (one field per screen or all fields), hiding fields for oneself, configurable sync identifier per project (the table and field the specimen identifier is matched against).
- Access for visitors who are not signed in: login required, browse, or transcribe.
- Lists: search, sort and column choice for users, fields, submissions and validated values; project filter; CSV and JSON export; project managers see the submissions of their projects; an *ignore* status for validated values that must not be sent.
- Account deletion (anonymising): by an administrator for anybody, and by each person for themselves.
- Conflict resolution before a sync overwrites a value; dry run of a sync.
- **Automatic sync** with the collection database: a master switch and a time zone in Settings, and a schedule per project (every hour, every few hours, every day at a time, every week on a day and a time). The sync is now a method that runs without a browser (`platform:sync-due`, scheduled every minute); every run, automatic or from the manual page, is kept in **Sync history** with its log, and the administrators are e-mailed when a project's automatic sync starts to fail. A value that fails is marked and not retried by itself. Off by default; the manual sync is unchanged.
- **Creating accounts** (Settings): an administrator can let people create their own account (e-mail confirmation, lowest level). Off by default, so an installation stays by invitation until it is switched on.
- **Collection to sync into** per project (project form), for installations whose projects belong to different Specify collections; `API_COLLECTION` in `.env` is the default.

### Changed
- **Scheduled work.** The platform now runs its recurring work from Laravel's scheduler instead of from page visits: the weekly ranking (until now calculated by the first visitor of the week, and written twice if two arrived together), the nightly progress figures of the projects and the nightly recount of the contributors' figures. Progress figures are recalculated on a submission only for the projects the specimen belongs to, not for all of them. The weekly report is written once per week (there is now a unique key) and the manual route cannot duplicate it. Settings shows whether the scheduler is running. **This needs one cron line on the server** (see the upgrade notes).
- **Catalog numbers are matched whatever the zeros the collection writes** ("264", "00000264", "000000264"): the platform used to turn every specimen id into 9 digits, a convention of one installation. It now asks for the number written with 1 to 12 digits in one request. If several records match, it refuses to guess and says so; the project's collection (if set) chooses between them. This applies to the sync, to the preview of the next record and to the comparison of a conflict.
- **"CSRF verification failed" (HTTP 403) when the same browser session was used with two Specify installations** (for example a test one and then the real one): the login to the collection database is kept in one file per Specify address now, and the CSRF token is the one of the server the request goes to.
- **Writing into a related object that the record links by its address** (a collecting event that is not embedded, as on some Specify installations): the sync used to replace the link by a new object, which Specify then created (a duplicate, and refused without a discipline: "Column 'DisciplineID' cannot be null"). The linked object is now fetched and updated in place.
- **Sync into a record that has no collecting event (or any other object Specify must create from the nested value)** no longer fails with "Column 'DisciplineID' cannot be null": the new object gets the discipline of the record's collection, asked for directly when the session's own list does not include that collection. A value that Specify refuses with a database error now keeps the explanation in its error text, not just the code ("1048").
- **Values written into a list of objects of the record (determinations, preparations, other identifiers, a collector's remarks) were silently lost**: Specify answered 200 and nothing was saved, because the value was sent under the name of the table (`determination`) instead of the name of the relation (`determinations`). They are now saved. In tables without an `ordernumber` (determinations, preparations, other identifiers) line 1 is the first entry, line 2 the second, and a line past the end adds one; a new preparation still needs its type, so Specify's error is shown for it.
- **Yes/no fields** (a boolean in Specify) are sent as true/false; the text "true" made Specify answer HTTP 500. Dates and yes/no values are now handled for fields of the record itself and inside lists too.
- **A mapping to a name that does not exist in Specify** (a typo in a field or a relation, such as `preparationproperty` instead of `preparationProperties`) is refused with an error that names it. Specify ignores unknown names and answers 200, so the value used to be reported as synced with nothing saved. The check uses Specify's data model.
- **The text editor (CKEditor 4) is now 4.22.1**, the last open-source release (it was 4.20.1); the later 4.x releases are only sold under a paid long-term-support licence. The configuration (`public/js/ckeditor/config.js`, with the file browser) is unchanged, and an unused copy of the map library inside the editor's folder was removed. CKEditor 4 is no longer maintained by its vendor: replacing it with a maintained editor is planned for a later version.
- **Dependencies updated within the versions the project already allowed** (Laravel 10.50, Guzzle, CommonMark, PhpSpreadsheet/Excel, Symfony...): `composer audit` went from 99 security advisories in 19 packages to 5 in one (`laravel/framework`, whose fixes exist only from Laravel 11: a later, separate upgrade). The lock is resolved for PHP 8.2 (`config.platform.php`), so it serves PHP 8.2 and 8.3. Run `composer install --no-dev --optimize-autoloader` on update.
- **The field form asks for the required relations of a new object**, not only the required fields: a preparation needs its type, an accession its division. The value is chosen from the objects Specify holds (the types of preparation of the collection, the divisions) and is saved as the default of the field. The link to the parent (the collection object of a preparation, the collecting event of a collector), the discipline and the collection are not asked: they are set from where the object is written.
- **The defaults of a field apply to the new objects of a list too** (a new preparation, collector, determination...), until now only to objects made by a search (an agent, a locality). A default never replaces what was transcribed, and an entry that already exists in Specify is left as it is. A yes/no default written as text goes as a boolean.
- **Geography, taxon and other tree names are looked up in the tree of the record's own discipline.** A rank was stored with the id of one discipline's tree ("5_200"), so a record of another collection was linked to a country of the wrong tree, or a name was created in it. One mapping now serves collections of different disciplines.
- **A warning when the record belongs to another collection than the session's.** Specify then gives the objects it creates in collection-scoped tables (determinations, preparations, other identifiers) the collection of the session, whatever is sent; the project must use the collection of its records.
- **A dry run (`?dryrun=1`) now writes nothing at all.** It used to skip only the final update: the objects a value needs and Specify does not hold yet (a locality, an agent, a geographic name, a collector...) were still created while it ran. It now searches as usual, says which objects it would create, and creates none.
- `/update_project_stats` needs the permission to manage users (it recalculated everything for any signed-in person).
- The software no longer carries any institution's name, logo, server, address or credential: a new installation starts with no logo (the name of the application is shown), English as the master language and generic user levels. Logo, languages, levels and texts are all set in the application. `FreshInstallTest` guards this.
- A page's pencil is a `span`, not a link inside a link, so it stays inside the menu item it belongs to; tables of values wrap long text and use the whole width of a wide screen.
- `Deploy_README.md` is replaced by `docs/installation.md`, and the README is shorter. The guides now say what a clean-install rehearsal showed: PHP 8.2 or 8.3 (the locked libraries do not support 8.4 yet), accounts come from Google/GitHub, from an administrator, or from self-registration if it is switched on; the people who do not register themselves set a password by e-mail, so `MAIL_FROM_ADDRESS` and the mail settings are required, and `.env.example` has a placeholder sender.
- The connection to Specify is read from `config/services.php` instead of calling `env()` all over the code, so `php artisan config:cache` and `optimize` no longer break it. Without `API_URL` the field and project forms no longer try to reach Specify (and no longer log an error on every load).
- The Specify collection the platform logs in to was fixed in the code; it is now `API_COLLECTION` in `.env`, and each project may override it.
- The leftover weekly e-mail text (from another project) was removed from `UsersController::weekly_report()`; the weekly figures it stores are unchanged.

### Upgrade notes
1. **Back up the database**, then `git pull`, `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan db:seed --class=BaseDataSeeder --force`, `php artisan optimize:clear` (run `artisan` as the web server user).
2. **Logo and loading animation.** The two files that used to be shipped in `public/branding` were removed from the repository, and `public/branding/*` is now ignored (it holds what each installation uploads). `git pull` deletes tracked files that disappear: **copy `public/branding/*` somewhere before pulling and put the files back afterwards**, or upload the logo again in *Settings*. An installation that never changed it points at those file names.
3. **Secrets.** Earlier versions of `.env.example`, `.env.testing` and the deployment guide contained an example application key and passwords. Treat any key or password that was ever used from those as public: set a new `APP_KEY` (everybody is signed out, which is harmless) and new database and API passwords.
4. `.env.testing` is no longer in git: copy `.env.testing.example` (developers only).
5. **If you sync with Specify, add `API_COLLECTION=<collection id>` to `.env`** (the collection the sync user logs in to; see `docs/configuration.md`), then `php artisan optimize:clear`. Earlier versions had that number written in the code; without the line the login to Specify is attempted without a collection.
6. **Add the cron line** that drives the scheduler, in the crontab of the web server user: `* * * * * cd /path/to/the/application && php artisan schedule:run >> /dev/null 2>&1` (`docs/installation.md`, "The scheduler"). Then run `php artisan platform:weekly-report` once, so that the home page has last week's ranking straight away.
7. Rebuild the assets (`npm run build`) only if `resources/sass`, `resources/js` or `package.json` changed since your last build: in this version only a comment in `resources/sass/_variables.scss` did, so none is needed.

## [Earlier changes] — 2026-06-04

### Added

**Specify 7 API ingestion**
- Schema-aware field mapping with `pick`, `write`, and `node` action types
- Automatic date precision detection: fields with `format: date` in the Specify openapi schema are detected automatically; the platform deduces `{field}precision` (1=full, 2=year-month, 3=year only) and pads partial dates to valid `YYYY-MM-DD` format before submission
- Multi-part field concatenation: admin can enable "Concatenate all parts" per field, joining all parts with the field separator and mapping them as a single value
- Required field defaults: admin can configure per-field defaults for Specify table fields that are required but not covered by the mapping (e.g. `localitynamealias.source`); stored in `fields.required_defaults` (JSON)
- n.a. substitution: admin can configure a default ingestion value per field to substitute when the validated value is `n.a.`; stored in `fields.na_defaults`; if not configured, `n.a.` records are skipped with status=2
- Reverse proxy support: `TrustProxies` middleware now reads `TRUST_PROXIES` from `.env`; supports `*` or comma-separated IP list; no code changes needed for different deployment environments
- New AJAX endpoint `GET /api_get-table-required-fields/{table}` for detecting required fields of a Specify table from the datamodel
- New AJAX endpoint `GET /api_get-table-ranks/{table}` for fetching tree rank definitions per discipline
- Validation dashboard: added search/filter by ID, specimen ID, field, status, and value

**Project management**
- When removing a field from a project, admin is prompted to soft-delete (status=0) the associated validated values for that project's specimens
- When deleting a project, admin is prompted to soft-delete all validated values for that project's specimens and active fields
- Project deletion modal moved to `projects/delete_modal.blade.php` and included globally in `layouts/app.blade.php` — available from all admin project views

**Field configuration**
- Fields now support `na_defaults` (text): ingestion default for `n.a.` values, in the same separator-delimited format as the field value
- Fields now support `concatenate_parts` (boolean): when enabled, all parts are joined and mapped as a single value
- Multi-part field display: when `explode_save_direction` is `reverse`, labels and values are now inverted correctly at display time, fixing incorrect display of partial values (e.g. incomplete dates)
- Validated value alert in transcription view now shows field labels alongside values for reverse-direction multi-part fields

### Changed

- `fields.api_table_field` column removed (was unused; replaced by `fields_mapping` table)
- `fields.required_defaults` moved from `fields_mapping` to `fields` table for cleaner separation between field-level configuration and mapping-level configuration
- All code comments and output strings standardised to English
- `Fields` model: added `$fillable` and `$casts` for `required_defaults`, `na_defaults`, `concatenate_parts`
- `Fields_mapping` model: removed `required_defaults` from `$fillable`

### Fixed

- Partial date values (e.g. `1998-10`) no longer displayed in wrong field slots when `explode_save_direction` is `reverse`
- Date values with `00` day/month segments (e.g. `1815-08-00`) now treated as absent, avoiding Specify validation errors
- `api_field_edit` transaction no longer silently rolled back due to mass assignment error on `concatenate_parts`
- Project field soft-delete now correctly uses `specimens.specimen_id` (external Specify ID) rather than `specimens.id` (internal Laravel ID)

### Migrations added

| Migration | Description |
|-----------|-------------|
| `2026_04_05_175512_create_social_accounts_table` | Social OAuth accounts table |
| `2026_04_05_175513_add_error_to_validated_fields` | Error column for ingestion feedback |
| `2026_04_05_175514_add_rank_id_to_fields_mapping` | Tree rank ID per mapping node |
| `2026_06_01_174501_add_required_defaults_to_fields_mapping` | (superseded — kept for existing installs) |
| `2026_06_01_192300_update_fields_table` | Drop `api_table_field`; add `required_defaults` JSON |
| `2026_06_01_192301_remove_required_defaults_from_fields_mapping` | Moves required_defaults to fields table |
| `2026_06_02_192302_add_na_defaults_to_fields_table` | n.a. substitution default per field |
| `2026_06_03_172334_add_concatenate_parts_to_fields` | Concatenation flag per field |

---

## [Initial release] — 2026-03-04

- Laravel-based transcription platform with collaborative field validation
- Project, specimen, field, and user management
- Multi-language dialog system
- Social login (Google, GitHub) via Laravel Socialite
- Email notification system
- Basic Specify 7 API connectivity
