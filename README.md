# 20i reseller hosting - Backblaze B2 backups
20i reseller hosting only take backups for disaster recovery which are not available to resellers, snapshot backups are available however start at £9.99 for 50 sites. Since backblaze is $0.005 per GB and you can backup unlimited sites with this script, it's cheaper and means you have your data available outside of their network.

### Supported features

  - Schedule backups
  - Download remote backups & upload to backblaze b2
  - Clean up old backups

### Usage

1. Create an account within 20i's control panel which you will only use for backups - random domain?
2. Download this script (I've bundled the vendor folder for those not familiar with composer)
3. Copy this script and associated files into public_html
4. Update the configuration details in `backup.php` - you can obtain your API bearer token from your 20i control panel. Set the BACKUP_ACCOUNT_DOMAIN to that of the domain you're using for your backup account to prevent it from being backed up. You don't want to backup your backups in this scenario)
4. Create a "Scheduled Task" for the backup schedule (Set to run every day at 12pm and enable email notifications):
        `/usr/bin/php71 FOLDER_PATH/public_html/manager.php -a backup`
5. Create a "Scheduled Task" for the download and upload to backblaze schedule (Set to run every day at 6am and enable email notifications):
        `/usr/bin/php71 FOLDER_PATH/public_html/manager.php -a download`
6. Create a "Scheduled Task" for the cleanup schedule (Set to run every month and enable email notifications):
        `/usr/bin/php71 FOLDER_PATH/public_html/manager.php -a cleanup`

### Notes
1. The cleanup job only cleans up your backblaze bucket of old backups, once a local backup has been uploaded, it's automatically removed from your file system
2. An offset between cron jobs has been set to allow the backup server time to backup all your sites.
3. It's recommended to set your backblaze bucket to only save one version of a backup (Lifecycle Settings) file so you don't have any duplicate versions in the event of you running this multiple times.
