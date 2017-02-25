<?php

require 'vendor/autoload.php';
require_once '20i_client.php';

use ChrisWhite\B2\Client as BackBlazeClient;
use ChrisWhite\B2\Bucket;

/**
 * Class TwentyIBackupManager
 */
class TwentyIBackupManager
{
    /**
     * @var \TwentyI\Stack\MyREST
     */
    private $hostClient;

    /**
     * @var \ChrisWhite\B2\Client
     */
    private $backblazeClient;
    
    /**
     * @var array
     */
    private $sites;

    // 20i account details
    const API_BASE     = 'https://api.20i.com';
    const BEARER_TOKEN = '';
    const BACKUP_DIR   = __DIR__ . '/backups';

    // backblaze account details
    const ACCOUNT_ID   = '';
    const APP_KEY      = '';
    const BUCKET_NAME  = '';

    // skip our dedicated backup account, dont want to backup backups!
    const BACKUP_ACCOUNT_DOMAIN = '';

    // backup config
    const DELETE_BACKUPS_OLDER_THAN = '1 month';

    /**
     * @var array
     */
    private $whatToBackup = [
        'files'     => true,
        'databases' => true,
    ];

    /**
     * TwentyIBackupManager constructor.
     */
    public function __construct()
    {
        $this->hostClient      = new \TwentyI\Stack\MyREST(self::BEARER_TOKEN);
        $this->backblazeClient = new BackBlazeClient(self::ACCOUNT_ID, self::APP_KEY);
        $this->sites           = $this->getSites();
    }

    /**
     * Retrieve account sites
     *
     * @return array
     */
    protected function getSites()
    {
    	if (is_null($this->sites)) {
    		$this->sites = $this->hostClient->getWithFields($this->endpoint('package'));
    	}

        return $this->sites;
    }

    /**
     * Get API endpoint by path
     *
     * @param $path
     *
     * @return string
     */
    protected function endpoint($path)
    {
        return sprintf("%s/%s", self::API_BASE, $path);
    }

    /**
     * Start backup
     */
    protected function startBackup()
    {
    	$logPrefix = 'BACKUP';

        $sites = $this->getSites();

        if (empty($sites)) {
            echo "[$logPrefix] ❌  No sites found to backup, sleeping\n";
            exit;
        }

        foreach ($sites as $site) {

            if ($site->name === self::BACKUP_ACCOUNT_DOMAIN) {
                continue;
            }

            $result = $this->hostClient->postWithFields($this->endpoint("package/" . $site->id . "/web/websiteBackup"), $this->whatToBackup);

            if (isset($result->result)) {
                echo "[$logPrefix] ⚡  Backup scheduled for {$site->name}\n";
            }
        }
      
        return true;
    }

    /**
     * Download backups
     */
    protected function downloadBackups()
    {
        $logPrefix = 'DOWNLOAD';

        // make sure we can download to our folder
        if (!is_dir(self::BACKUP_DIR)) {
            echo "[$logPrefix] ❌  Backup directory not created, making...\n";
            mkdir(self::BACKUP_DIR);
        }

        $sites = $this->getSites();

        if (empty($sites)) {
            echo "[$logPrefix] ❌  No sites found to download backups, sleeping\n";
            exit;
        }

        echo 'Found ' . count($sites) . ' sites under this account, checking if we have backups...' . "\n";
        echo "---------------------------------------------------------------------------------------\n";
      
        $processed = [];

        foreach ($sites as $site) {

            if ($site->name === self::BACKUP_ACCOUNT_DOMAIN) {
                continue;
            } else if (in_array($site->id, $processed)) {
                continue;
            }
          
            $processed[] = $site->id;

            $backup = $this->hostClient->getWithFields($this->endpoint('package/' . $site->id . '/web/websiteBackup'));

            if (isset($backup, $backup->download_link)) {
                $backupDate = date("d-m-Y-H-i-s", strtotime($backup->created_at));
                $filename = $site->name . '_' . $backupDate . '.zip';
                $filepath = self::BACKUP_DIR . '/' . $filename;
                if (!is_file($filepath)) {
                    echo "[$logPrefix] ☁️️  Found a remote backup for " . $site->name .  " created at " . $backupDate . ", downloading\n";
                    file_put_contents($filepath, fopen($backup->download_link, 'r'));
                } else {
                    echo "[$logPrefix] 📁  Found a local backup for {$site->name}, skipping download\n";
                }
            }
        }
      
        return true;
    }

    /**
     * Transfer backups to backblaze
     *
     * @return $this
     */
    protected function transferBackups()
    {
        $logPrefix = 'TRANSFER';

        if (!is_dir(self::BACKUP_DIR)) {
            echo "[$logPrefix] ❌  No backups found to transfer, sleeping\n";
            exit;
        }

        $backups = array_diff(scandir(self::BACKUP_DIR), array('..', '.'));
      
        $processed = [];
      
        foreach ($backups as $backup) {

            if (in_array($backup, $processed)) {
                continue;
            }
          
            $processed[] = $backup;

            echo "[$logPrefix] ⚡  Upload initiated for backup $backup \n";

            $localfile  = self::BACKUP_DIR . '/' . $backup;
            $remotefile = date('d-m-Y') . '/' . $backup;
          
            $fileHandle = fopen($localfile, 'r');
          
            if ($fileHandle) {
                $upload = $this->backblazeClient->upload([
                    'BucketName' => self::BUCKET_NAME,
                    'FileName'   => $remotefile,
                    'Body'       => fopen($localfile, 'r'),
                ]);
              
              	if ($upload) {
                    echo "[$logPrefix] ✅  Uploaded backup $backup (" . $this->formatBytes($upload->getSize()) . ") to backblaze folder '" . date('d-m-Y') . "', deleting local copy\n";
                	unlink($localfile); 
                }
            }
        }
      
      	return true;
    }

    /**
     * Convert bytes to correct type
     *
     * @param $bytes
     *
     * @return string
     */
    protected function formatBytes($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } else if ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } else if ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' kB';
        } else if ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } else if ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    /**
     * Delete backups older than const DELETE_BACKUPS_OLDER_THAN
     */
    protected function deleteOldBackups()
    {
        $logPrefix = 'CLEANUP';

        $backupFolders = $this->backblazeClient->listFiles([
            'BucketName' => self::BUCKET_NAME,
        ]);

        if (empty($backupFolders)) {
            echo "[$logPrefix] ❌  No old backups found to delete, sleeping\n";
            exit;
        }

        foreach ($backupFolders as $file) {
            if (!is_null($file->getName())) {
                $fileName = explode('/', $file->getName());
                if (isset($fileName[0], $fileName[1])) {
                    $folderName = $fileName[0];
                    if (strtotime($folderName) < strtotime('-' . self::DELETE_BACKUPS_OLDER_THAN)) {
                        $status = $this->backblazeClient->deleteFile([
                            'BucketName' => self::BUCKET_NAME,
                            'FileName'   => $file->getName(),
                     	]);

                        if ($status) {
                            echo "[$logPrefix] 🗑️  Backup {$fileName[1]} is older than " . self::DELETE_BACKUPS_OLDER_THAN . ", deleted from backblaze\n";
                        }
                    }
                }
            }
        }
      
        return true;
    }

    /**
     * Boot
     */
    public function boot()
    {
        $args = getopt("a:");

        switch ($args['a']) {
            case 'backup':
                $this->startBackup();
                break;
            case 'download':
                $this->downloadBackups();
            	$this->transferBackups();
            	break;
            case 'cleanup':
                $this->deleteOldBackups();
                break;
            default:
                echo 'Invalid action requested: ' . $args['a'] . "\n";
                break;
        }
    }
}

echo "---------------------------------------------------------------------------------------\n";
echo '20i CLI Backup Manager' . "\n";
echo "---------------------------------------------------------------------------------------\n";

$manager = new TwentyIBackupManager;
$manager->boot();
