# Backup and Restore

## Backup
To safely back up your gallery, run the backup tool via CLI:
`php tools/backup.php`

This will archive your photos and database into a secure zip file in the `backups/` directory.

## Verify Backup
You can verify the integrity of a backup archive to ensure it contains all necessary database and media files and matches the manifest:
`php tools/verify_backup.php backups/mygallery_backup_20260615_xxxxxx.zip`

## Restore
You can automatically restore the gallery from a backup zip file. 
**WARNING: This will overwrite your current database and all media files!**

`php tools/restore.php backups/mygallery_backup_20260615_xxxxxx.zip`

When prompted, type `RESTORE` to confirm.

### Manual Restore
Alternatively, you can manually restore by:
1. Unzipping the backup file.
2. Importing `mygallery_backup/database.sql` into your database.
3. Placing the `storage/` and `public/uploads/` files in their respective directories.

## Runtime Cleanup
During normal operation, the gallery generates temporary session files, error logs, and trash items in the `storage/` directory. These files are not backed up. It is recommended to clear them occasionally:

`php tools/cleanup_runtime.php --apply`
