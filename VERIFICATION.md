# Finalization review — 2026-09-16

Base: d7a015c0da9715250af72b1ada8fd44f82c871dc (master).

## Changes

- Preserve the latest dashboard, analytics, audit log, product image upload and sales interface.
- Route historical PHP entry points to the current authenticated management pages. The old demo storefront and checkout are retired; their source remains in Git history.
- Persist product photos in a named Docker volume. Document copying photos before upgrading an older container.
- Require database passwords in a private .env rather than publishing fixed passwords.
- Restrict default access to localhost and block database dumps, internal PHP libraries and dot paths in Apache.
- Validate sales and product numeric inputs; preserve decimal prices.
- Save product and stock movement changes together, reject stale stock edits, retain old images until commit, and remove new uploads after failed saves.
- Restrict supplier deletion in the database to protect linked products.
- Serialize first setup and commit its owner account and settings together.
- Export JSON from a consistent database snapshot; document complete SQL/photo backup and recovery.

## Verification performed

- Parsed all 57 application PHP files with php-parser in PHP 8.3 mode: passed.
- Parsed Docker Compose and GitHub Actions YAML: passed.
- Checked persistent image volume and localhost binding: passed.
- Compiled Python integration test syntax: passed.
- git diff --check: passed.

## Required before release

The full Docker/PHP/MariaDB integration suite has NOT run. This environment has neither PHP nor Docker, and the package installation attempt failed because the container cannot switch system users. The prepared GitHub Actions workflow exercises setup, login, primary pages, products, sales, invalid inputs, stale stock forms, JSON backup, CSRF and protected files against a disposable database.

Publication to herode-star/Gestion-de-stock was explicitly authorized on 2026-09-16. The changes are available in pull request #1. The Stock checks workflow is the authoritative full integration result; verify its latest run before release. The suite also checks that failed photo replacement preserves the old image and that uploaded photos survive container recreation.

No live database was accessed or modified. Existing installations must keep their current database credentials and copy their uploaded photos before upgrading. The seeded historical demonstration products remain. The JSON export excludes accounts and photo bytes, and is not a complete restore archive. Advanced AI needs the user's own API key and has not been exercised.
