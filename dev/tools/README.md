# EasyMed Tools Directory

This directory contains maintenance and migration tools for the EasyMed project.

## Available Tools

### `inspect_and_migrate_sqlite.php`
**Purpose**: Inspects the SQLite database schema and adds missing columns if needed.

**Usage**:
```bash
php tools/inspect_and_migrate_sqlite.php
```

**What it does**:
- Connects to `database/easymed.sqlite`
- Checks for required columns in the `users` table: `phone`, `date_of_birth`, `gender`
- Adds missing columns using safe `ALTER TABLE` statements
- Reports the final schema

**Output**: 
- Lists current columns before changes
- Shows which columns were added (if any)
- Displays final updated schema

**Safety**: 
- Uses `ALTER TABLE ADD COLUMN` (non-destructive)
- Creates nullable TEXT columns
- Idempotent (safe to run multiple times)

## Database Backup

Before running any migration tools, ensure you have a backup:

```bash
# Create backup
Copy-Item "database/easymed.sqlite" "database/easymed.sqlite.backup"

# Restore if needed
Copy-Item "database/easymed.sqlite.backup" "database/easymed.sqlite"
```

## Common Issues Resolved

### "no such column: phone"
- **Solution**: Run `inspect_and_migrate_sqlite.php` to add the missing `phone` column

### "no such column: date_of_birth"  
- **Solution**: Run `inspect_and_migrate_sqlite.php` to add the missing `date_of_birth` column

### "no such column: gender"
- **Solution**: Run `inspect_and_migrate_sqlite.php` to add the missing `gender` column

## Adding New Columns

To add a new required column to the migration script:

1. Edit `inspect_and_migrate_sqlite.php`
2. Add the column to the `$needed` array:
   ```php
   $needed = [
       'phone' => "ALTER TABLE users ADD COLUMN phone TEXT",
       'date_of_birth' => "ALTER TABLE users ADD COLUMN date_of_birth TEXT",
       'gender' => "ALTER TABLE users ADD COLUMN gender TEXT",
       'new_column' => "ALTER TABLE users ADD COLUMN new_column TEXT"
   ];
   ```
3. Run the script to apply the migration

## Project Cleanup

The project has been cleaned up with test and debug files moved to `_cleanup_archive/`. 

- **Archive location**: `_cleanup_archive/`
- **Documentation**: `docs/`
- **Core application**: Root directory and subdirectories

The archive directory can be safely deleted if the old files are not needed.
