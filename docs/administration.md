# Administration guide

How the platform works, in the order you meet it: what a project is made of, how a value becomes valid, and how it leaves the platform. Each section explains the **logic** first (why it is built this way) and then the **steps**.

- [The big picture](#the-big-picture)
- [First steps on a new installation](#first-steps-on-a-new-installation)
- [Fields](#fields)
- [Projects](#projects)
- [Specimens: uploading the list](#specimens-uploading-the-list)
- [How a value becomes valid](#how-a-value-becomes-valid)
- [Validated values, conflicts and export](#validated-values-conflicts-and-export)
- [Sync with Specify](#sync-with-specify)
- [People](#people)
- [The website: menu, pages and footer](#the-website-menu-pages-and-footer)
- [Editing any text](#editing-any-text)

## The big picture

```
 fields ──► project ◄── specimens (list of images)
                │
          transcribers type what they see on each image
                │
   identical answers add up their weight ──► a value is VALID
                │
      export (CSV/JSON)   or   sync to the collection database (Specify 7)
```

- A **field** is one thing to transcribe (locality, collector, date…). Fields are defined once for the whole platform and reused by projects.
- A **project** is a set of specimens to be transcribed with a chosen set of fields.
- A **specimen** is one identifier and the image(s) of its label.
- Several people transcribe the **same** field of the **same** specimen independently. When enough of them agree, the value is considered valid. That agreement, not a single person's work, is the quality control.

## First steps on a new installation

1. Sign in with the administrator created by `platform:install`.
2. **Admin → Platform → Settings**: upload the logo of your institution, and decide the [access for visitors](configuration.md#settings-page).
3. **Admin → Platform → Languages**: add the languages of your transcribers ([how](configuration.md#languages)).
4. **Admin → People → User categories**: look at the levels and adjust the names and numbers ([why they matter](#how-a-value-becomes-valid)).
5. **Admin → Transcription → Fields**: create the fields.
6. **Admin → Transcription → Projects**: create a project, choose its fields, upload its specimens.
7. **Content**: write the pages visitors will read (About, Tutorial…) and the footer ([how](#the-website-menu-pages-and-footer)).
8. **Give people access.** Decide in Settings whether people can [create their own account](configuration.md#creating-accounts). If not (the default), a person gets an account either by signing in with Google or GitHub (if you [configured them](configuration.md#sign-in-with-google-or-github)), or because an administrator created it under **Admin → People → Users → new** (name, e-mail, level, role). In the second case the person then opens the sign-in page, chooses **Forgot your password?** and sets a password from the link they receive, so **outgoing e-mail must work** ([configuration](configuration.md#the-file-env)). Everybody starts at the lowest level unless you set another.

## Fields

**Admin → Transcription → Fields.** A field has a name, a question (what the transcriber sees), an instruction text, an optional help text with an image, and a **type**.

### Field types

| Type | Use it for | Notes |
|---|---|---|
| Text | one line: a name, a number | can offer **suggestions** while typing (see *Autocomplete*) |
| Text area | longer free text: a verbatim label | |
| Select | one choice from a list | the list is a table of **options** |
| Radio | one choice, all visible | same options table |
| Checkboxes | zero or more choices | the options table, and optionally a **separator** (below) |
| Date | a date | partial dates (year only, year and month) are understood and sent with the right precision |

### Options

Select, radio and checkboxes keep their options in a table. Each option has a **value** and a **label per language**. The *value* is what is saved and what is sent to the collection database; the *label* is what the transcriber sees, in their language. This is what makes a list translatable without changing the stored data: "Forest" and "Skov" are two labels of the same value. The editor is a table (drag to reorder), with *import from text* (one option per line, `value ; label` pairs) and *fill with submitted values* to start from what people have already typed. A language with no label for an option falls back to the value.

### Checkboxes and the separator

Without a separator each ticked box is its own line (its own value). With a separator (for example `; `) the ticks of one specimen are **one value** joined in list order: `forest; meadow`. Choose it according to how the collection database stores the value.

### Other field settings

- **Required**: the specimen cannot be submitted without it.
- **Have doubts / Deducted**: let the transcriber mark a value as uncertain, or as deduced rather than read from the label. Both are stored with the value.
- **Multiple** ("add line"): the field can have several values for one specimen (several collectors, several determinations). Each line is a separate value with its own position, and lines are validated one by one.
- **Autocomplete**: suggests, as the person types, the values defined for the field and the ones already submitted. It speeds up typing and, above all, makes spellings identical, which helps values agree.
- **Minimum level**: only people at that level or higher are offered the field. Use it to keep difficult fields for experienced people.
- **Parts (explode)**: a value made of several parts typed in one box (for example a coordinate or a date as `day / month / year`) can be split with a separator, and the parts mapped separately. The *direction* says in which order the parts are saved.
- **No information**: a transcriber can mark a field as "no information" (stored as `n.a.`). In the field's form you can set what is sent to the collection database instead (a default); records with `n.a.` and no default are skipped without error.

Fields have an **order** (drag the rows). It is the order of the form, the same in every project. While the list is shown in A–Z order the dragging is disabled.

Every person can also **hide a field for themselves** on the form (a link under the field), and restore it from the *hidden fields* row; it only affects their own view.

## Projects

**Admin → Transcription → Projects → New.** A project has a **title, abstract and description** in each language (the pencil icons are the same ones as everywhere), a **cover image**, and an *active* switch: an inactive project is hidden from visitors and from transcribers (administrators still see it).

**Choose its fields** (the **fields** button of the project): tick the fields the project asks for; a search box finds a field or one of its sub-fields. The order is the global order of the fields.

### How the form is shown

The *field view*: two ways to transcribe.

- **All fields**: every field of a specimen on one screen. Faster for experienced people, the usual choice.
- **One field at a time**: one field per screen, walking a specimen's fields in order, then moving on. Good for beginners and for phones.

The platform has a default (`FIELD_VIEW_DEFAULT`), each project can set its own, and **each person can switch** on the transcription screen. The most specific choice wins: person, then project, then platform.

### Who sees what others submitted

On the form there can be a panel *Submitted by other users*. It is a help for experienced people, and a risk for independence: if everybody sees the previous answer, answers stop being independent and agreement means less. The project setting says who gets the panel: nobody, only root, administrators, the project's managers (the default), or everybody.

### Project managers

A person with the role *Project manager* can manage the projects they created and the ones they were given (in the project form, **Project managers**). They see the submissions of those projects and can resolve conflicts in them; nothing else.

### Sync identifier

*Advanced.* What does the specimen identifier of this project **mean** in the collection database? By default it is the *catalog number* of a *collection object*. A project may instead match it against any field of any table (for example the `text1` field of a *preparation*, when each object has many preparations and the work is done per preparation). Set the table and field here; the choices come from the collection database's own schema (so the connection must be configured). **The zeros of a catalog number.** Each collection decides how many zeros its catalog numbers start with ("264", "00000264", "000000264"). The platform does not assume a width: for the catalog number it asks the collection database for the number written with any width from 1 to 12 digits, in one request, so a specimen id of `264` finds whichever of them the collection uses. If more than one record answers (the same number in two collections, or written two ways in one), nothing is written: the value fails with a message saying so, and setting the **collection of the project** ([below](#collection-to-sync-into)) tells the platform which one is meant. Other identifier fields are matched exactly as they are. One project has one identifier: do not mix levels in one project. A field's mapping must start at the same table as its project's sync identifier; if it does not, the sync stops with a clear message instead of silently writing nothing.

### Collection to sync into

*Advanced, and only if you sync.* Specify works on one collection at a time, and the platform logs in to one. By default it is the collection in the installation's configuration (`API_COLLECTION`); when the projects of this installation belong to **different collections**, fill in the collection id in the project form and that project's values are sent into it. The Specify user must have the right to edit in that collection. Leave it empty to use the default.

**How Specify treats it** (checked against a real Specify 7): the login always needs a collection, and the session can be switched to another one at any time; Specify then judges **every request by the permissions the user has in the collection the session is logged in to**, not in the collection the record belongs to. In practice: a user who may edit in collection A and logs in to A can also write to a record of collection B, even without any right in B; and logged in to B without the right to edit there, every write is refused (`NoMatchingRuleException`), also for records of A. So give each project **its own collection**, the one its specimens belong to: then Specify's own permissions apply as intended, and a project whose collection the user may not edit fails clearly instead of writing where it should not. There is a second reason: the objects Specify creates in tables scoped to a collection (determinations, preparations, other identifiers, the attributes of a record) are given **the collection of the session**, whatever the platform sends, so a record written through another collection's session gets them in the wrong collection. The manual page says so when that happens.

**Required values of a new object.** Specify refuses to create some objects without a value that nothing in the submission supplies: a preparation needs its **type**, an accession its **division**. When you map a field to such a table, the mapping form lists what that table requires, fields and relations; fill in the default (for a relation, choose it from the list of objects of Specify). It is used only when the transcription does not provide the value, for objects the sync creates, including a new line of a list (a second preparation, a second collector).

**Trees (geography, taxon...)** belong to a discipline, and every discipline of a Specify database has its own, with the same rank numbers. A mapping that names a rank ("country") is therefore looked up and created in the tree of the discipline of the record being written, not in the tree whose id the mapping happens to carry. One mapping serves collections of different disciplines.

## Specimens: uploading the list

**Project → upload.** A spreadsheet (`.xlsx`, `.xls` or `.csv`) with two columns in the first row:

| `specimen_id` | `images` |
|---|---|
| 912345 | https://images.example.org/912345.jpg |
| 912346 | https://images.example.org/912346-a.jpg\|https://images.example.org/912346-b.jpg |

- `specimen_id` is the identifier that will be used in the sync (see *Sync identifier*).
- `images` is the address of the image. For **more than one image** of the same specimen put them in the same cell separated by `|`; the transcriber then sees the first one and a column of thumbnails to switch.
- You can set a **minimum level** for the whole upload (for example, keep the hardest batch for experienced people).

The images are not stored by the platform: they are shown from the address you give, so it must be reachable by the transcribers' browsers.

## How a value becomes valid

This is the rule that matters most, and the one to understand before choosing the user levels.

**Every submission has a weight: the points of the submitter's level.** For each field line of each specimen, the platform groups the submissions with **exactly the same value** and adds their points. When the sum reaches **60**, the value is **valid** and is stored as a validated value. Different values for the same line are different groups that compete.

With the default levels (Basic 10, Beginner 20, Competent 30, Advanced 40, Expert 50, Validator 60):

- one *Validator* alone validates a value;
- two people whose points add to 60 or more who typed the same thing do too (30 + 30, 10 + 50, 20 + 40…);
- three Basic (10 + 10 + 10) are not enough; six are.

The consequences, which are the reason for the design:

- A beginner's answer is worth less, so a value needs more of them to agree, or one experienced person.
- Typing the same thing matters, character by character (suggestions, options and clear instructions help). Two correct answers spelled differently are two competing values.
- People are promoted by the number of their contributions that were **validated**, so being careful is rewarded, and the weight of their vote grows as they do.
- When several groups exist and none reaches 60, nothing is validated: the line waits for more submissions, or for a *Validator* to decide.
- If you change the points of a level, the new weight applies to what is validated from then on. The threshold of 60 is fixed in the software.

## Validated values, conflicts and export

**Admin → Transcription → Validated values** lists every valid value with its project, field, specimen, value, status and date. The **Columns** menu chooses what to show, the filters narrow by project and status, and the lists can be exported to **CSV** or **JSON**.

The **status** of a validated value:

| Status | Meaning |
|---|---|
| Validated, waiting | valid, not yet sent |
| Sent | written to the collection database |
| Error | the last attempt failed; the message is shown; can be retried |
| Conflict | the collection database already holds a **different** value for it |
| Resolved: kept the collection's value | someone decided to keep what the collection has |
| Overwrite approved | someone decided to replace it; it is sent |
| Ignored | set aside on purpose: it will not be sent (use it for values that should never go to the collection) |
| Replaced | an older validated value, replaced by a newer one |

**Conflicts.** A project can be set to keep what the collection database already holds (see [When the collection already has a value](#when-the-collection-already-has-a-value)). Then, when the values differ, the record is marked *Conflict* and nothing is overwritten without a decision; **Admin → Transcription → Conflicts with Specify** shows both values side by side, and the people who may resolve conflicts (administrators, and project managers for their projects) choose to keep the collection's value or to overwrite it. What the collection holds is read live every time, never copied.

**User submissions** (administrators: all; project managers: their projects') lists every individual submission, with the person, level, time spent, doubt and deduced flags, and remarks. It is the place to follow the work of a person or of a project, and it can be exported.

## Sync with Specify

*Only if you have configured [the connection](configuration.md#the-connection-to-specify-7).* Specify is the collection database; the platform writes validated values into its records through its REST API.

**Mapping.** Each field is mapped to the Specify table(s) it fills, in the **API connection** window of the field. A node of the mapping is one of:

- **node**: follows a relation to a related table (a collecting event, its locality…);
- **write**: writes the value into a column;
- **pick**: looks up an existing record by the value (an agent, a taxon), and creates it if it is not found.

The mapping window reads the real schema of your Specify, so it offers only tables and columns that exist. Dates are recognised from the schema and sent with their precision. A field made of several parts can be **concatenated** into one value. Columns that Specify requires but the mapping does not cover can be given **defaults** ("required defaults"), for example a *source* for an alias record.

**Running it.** On the *Validated values* page each row has a button: **Sync** (first attempt), **Retry** after an error, **Force** after you have resolved a conflict. A field with no mapping shows *No mapping* instead. The manual page shows a complete log of what was decided: which record was found, what is new, what is already there. **Add `?dryrun=1`** to the address of a manual sync to see exactly what *would* be sent, without writing anything: do this the first time you map a field.

### When the collection already has a value

A sync can meet a record that already holds a value where the platform is about to write. The project's setting *Sync when Specify already has a value* decides what happens:

- Empty values, and values equal to the validated one, are always written: nothing is lost either way.
- **Overwrite it** (the default): a different value is replaced by the validated one.
- **Keep it and flag a conflict**: that part is left alone and the record is marked *Conflict*, so that a person decides (see *Conflicts* above). Choose this where the collection database has been curated by hand and a transcription must not silently replace it.

Two options refine what "different" means: *ignore upper/lower case* ("Forest" and "forest" count as equal) and *round numbers to this many decimals* before comparing (so `12.3400` and `12.34` are equal; empty means an exact comparison).

**Why a delta.** The platform sends only the differences, merged into the record as Specify returned it, so what it did not touch stays as it was. Lists of dependent records (collectors, determinations) are matched by their order number, so editing line 2 changes line 2 and does not create a duplicate.

### Automatic sync

The manual buttons are always there. In addition a project can send its validated values **by itself**, on a schedule, so that nobody has to press a button every day.

**Two switches.** The master switch is in **Settings → Scheduled tasks → Automatic sync** (root and administrators): off, nothing is sent by itself, whatever the projects say. Each project then has its own **Automatic sync** in its form (people who can run the sync): on or off, and when. Both must be on.

**The schedule** is one of a few shapes, not free text: *every hour*; *every 2, 3, 4, 6, 8 or 12 hours* (counted from midnight); *every day at a time*; *every week on a day and a time*. The times are in the **time zone** chosen in Settings (UTC until you change it). The form shows the next run. When you switch it on or change it, the project waits for the next scheduled moment instead of running at once. If the server was stopped through a scheduled moment, the sync runs once when it is back, not once for every moment missed.

**What it sends.** Exactly what the manual **Sync** would: the values that are validated and waiting (and the ones you approved overwriting), in the project's own collection. It follows the project's rules: a project that keeps existing values flags a *conflict* and leaves it for a person to resolve, one that overwrites overwrites. **A value that fails is marked as an error and is not tried again by itself** (it would fail again every time); fix the cause and use **Retry** on the *Validated values* page. If there is a lot to send, a run stops after about 45 minutes and the rest waits for the next one.

**It needs** the connection to the collection database ([configuration](configuration.md#the-connection-to-specify-7)) and the **cron line** that drives the scheduler ([installation](installation.md#the-scheduler-cron)); Settings tells you whether the scheduler is running. Because nobody watches it, **the first time a project's run fails, the administrators get an e-mail** (and not another one until a run works and fails again).

**Try it first.** From the server, `php artisan platform:sync-due --project=<id>` sends that project's waiting values now, as the scheduler would, and prints the result. For a trial that writes nothing, use the manual page with `?dryrun=1` ([Running it](#sync-with-specify)).

### History of the runs

**Admin → Transcription → Sync history** lists every run, automatic or started from the manual page, with its project, who started it, how many values were sent, are in conflict or failed, how many are still waiting and how long it took. Open a run to read the full log of what was decided for each record, the same text the manual page shows. A *failed* run is one that could not start at all (for example the login to the collection database was refused); the values are still waiting. Individual values that failed are on the *Validated values* page.

## People

**Admin → People → Users** lists everyone, with a search box and sortable columns. **New** creates an account (name, e-mail, level, and for root the role); it has no password yet: the person sets it with *Forgot your password?* from the sign-in page, so e-mail must be configured, unless they use Google or GitHub with the same address. For each person an administrator can change the level and the role (the role, root only), or delete the account (see below).

**Deleting an account.** Administrators can delete anybody's account, and each person can delete their own from their profile. What the person transcribed is **not** deleted (it may already be part of validated data): the account is anonymised — name, e-mail, photo and other personal data are removed and the contributions stay under "deleted user". This keeps the scientific record complete and the person's data gone.

## The website: menu, pages and footer

**Content** in the top bar has three parts.

- **Menu**: the structure of the public menu. Each item is a *page*, an *external link* or a *group* (a drop-down). Items can be nested, ordered by dragging, shown or hidden, and restricted to *members only* (signed-in people). Items can have **publish and archive dates**.
- **Contents**: the pages' texts. A menu item holds one or several contents (a title and a body written with the rich editor, with images and files from the **browse server** button, which lists `public/uploads`). Each content has its own publish/archive dates and *members only* switch, and the order inside the page can be automatic (newest first) or manual.
- **Footer**: blocks of two kinds, *text* (a title and a text) or *links* (a title and a list of menu items), each with a width in a 12-column row, so three blocks of width 4 sit side by side. A block with nothing to show does not appear.

Everything is translatable: the names, titles and texts have a pencil ([editing any text](#editing-any-text)).

## Editing any text

Administrators see a **pencil button** in the top bar: it turns **edit mode** on and off (the choice is remembered in the browser). With it on, a small pencil appears next to every text of the interface and of the website. Click it: a window shows the text in every language; edit, save, and the change is live. Texts that may contain formatting open the rich editor.

Texts without a pencil are rare (browser tooltips and placeholders, and some messages); the options of a field are edited in the field's own form.

**The "?" hints.** Next to the settings where a decision is taken (the field form, the project form, Settings, the user levels, the upload of specimens…) a small **?** opens a short explanation, and **Read more** opens the section of these guides that explains the logic, in a new tab so that what you were typing is kept. The hints are texts like any other: translate or reword them with the pencils.
