<?php
namespace Koyabu\GoogleDriveApi;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Http\MediaFileUpload;
use Exception;

class GoogleDriveClient
{
    protected Client $client;
    protected Drive $service;

    protected string $accessToken = '';
    protected string $refreshToken = '';
    protected string $redirectUri = '';
    protected array $scopes = [
        Drive::DRIVE_FILE,
        Drive::DRIVE_METADATA,
        Drive::DRIVE
    ];

    protected string $homeFolderId = 'root';
    public string $lastError = '';

    public function __construct(array $options = [])
    {
        try {
            $this->client = new Client();
            $this->client->setClientId($options['client_id'] ?? '');
            $this->client->setClientSecret($options['client_secret'] ?? '');
            $this->client->setRedirectUri($options['redirect_uri'] ?? '');
            $this->client->setScopes($options['scopes'] ?? $this->scopes);
            $this->client->setAccessType('offline');

            if (!empty($options['access_token'])) {
                $this->client->setAccessToken($options['access_token']);
            }

            if (!empty($options['refresh_token'])) {
                $this->client->fetchAccessTokenWithRefreshToken($options['refresh_token']);
            }

            $this->service = new Drive($this->client);
            $this->homeFolderId = $options['home_folder_id'] ?? 'root';

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
        }
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function fetchAccessToken(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            $this->lastError = $token['error_description'] ?? $token['error'];
        }
        return $token;
    }

    public function setAccessToken(string $token)
    {
        $this->client->setAccessToken($token);
    }

    public function setRefreshToken(string $token)
    {
        $this->client->refreshToken($token);
    }

    protected function ensureAccessTokenAvailable(): bool
    {
        if (!$this->client->getAccessToken() || $this->client->isAccessTokenExpired()) {
            $this->lastError = "Access token is missing or expired.";
            return false;
        }
        return true;
    }

    // Create Folder
    public function createFolder(string $name, string $parentId = null)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        $fileMetadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId ?? $this->homeFolderId]
        ]);

        return $this->service->files->create($fileMetadata, ['fields' => 'id, name, parents']);
    }

    // Upload File
    public function uploadFile(string $localPath, string $remoteName = null, string $parentId = null)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        $file = new DriveFile([
            'name' => $remoteName ?? basename($localPath),
            'parents' => [$parentId ?? $this->homeFolderId]
        ]);

        return $this->service->files->create(
            $file,
            [
                'data' => file_get_contents($localPath),
                'mimeType' => mime_content_type($localPath),
                'uploadType' => 'multipart',
                'fields' => 'id, name, parents'
            ]
        );
    }

    // Upload Large File (Resumable)
    public function uploadLargeFile(string $localPath, string $remoteName = null, string $parentId = null)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        $file = new DriveFile([
            'name' => $remoteName ?? basename($localPath),
            'parents' => [$parentId ?? $this->homeFolderId]
        ]);

        $chunkSize = 10 * 1024 * 1024; // 10 MB
        $client = $this->client;
        $client->setDefer(true);
        $request = $this->service->files->create($file, ['fields' => 'id']);
        $media = new MediaFileUpload(
            $client,
            $request,
            mime_content_type($localPath),
            null,
            true,
            $chunkSize
        );

        $handle = fopen($localPath, "rb");
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $media->nextChunk($chunk);
        }
        fclose($handle);
        $client->setDefer(false);

        return $media->getFile();
    }

    // Download File
    public function downloadFile(string $fileId, string $destination)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        $content = $this->service->files->get($fileId, ['alt' => 'media']);
        file_put_contents($destination, $content->getBody()->getContents());

        return $destination;
    }

    // Remove File/Folder
    public function delete(string $fileId)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        try {
            // Move to Trash (default)
            $this->service->files->delete($fileId);

            return [
                'success' => true,
                'message' => "File or folder deleted: {$fileId}"
            ];

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }


    // Create Share Link (Permission)
    public function createShareLink(string $fileId, string $role = 'reader', string $type = 'anyone')
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        $permission = new Permission([
            'type' => $type, // anyone, user, domain
            'role' => $role  // reader, writer
        ]);

        return $this->service->permissions->create($fileId, $permission, ['sendNotificationEmail' => false]);
    }

    // Remove Permission / Share Link
    public function removeShareLink(string $fileId, string $permissionId)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        return $this->service->permissions->delete($fileId, $permissionId);
    }

    /**
     * List contents of a folder
     * 
     * @param string|null $folderId Folder ID (default: home/root)
     * @param bool $includeFolders Include folders in result
     * @return array|false
     */
    public function listFolder(string $folderId = null, bool $includeFolders = true)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        $folderId = $folderId ?? $this->homeFolderId;
        $q = sprintf("'%s' in parents", $folderId);

        if (!$includeFolders) {
            $q .= " and mimeType != 'application/vnd.google-apps.folder'";
        }

        $files = [];
        $pageToken = null;

        do {
            $response = $this->service->files->listFiles([
                'q' => $q,
                'spaces' => 'drive',
                'fields' => 'nextPageToken, files(id, name, mimeType)',
                'pageToken' => $pageToken
            ]);

            $files = array_merge($files, $response->getFiles());
            $pageToken = $response->getNextPageToken();
        } while ($pageToken != null);

        return $files;
    }

    /**
     * Get file metadata/info
     *
     * @param string $fileId
     * @return array|false
     */
    public function fileInfo(string $fileId)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, mimeType, size, parents, createdTime, modifiedTime, webViewLink, webContentLink'
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'parents' => $file->getParents(),
                'createdTime' => $file->getCreatedTime(),
                'modifiedTime' => $file->getModifiedTime(),
                'webViewLink' => $file->getWebViewLink(),
                'webContentLink' => $file->getWebContentLink()
            ];
        } catch (\Google\Service\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get raw file content (binary)
     *
     * @param string $fileId
     * @return string|false  Binary content, bisa langsung di-render <img src="data:...">
     */
    public function fileInfoRaw(string $fileId)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        try {
            $response = $this->service->files->get($fileId, ['alt' => 'media']);
            return $response->getBody()->getContents();
        } catch (\Google\Service\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get file URL ready for web embedding
     *
     * @param string $fileId  File ID di Google Drive
     * @param string $type    'view' untuk preview (webViewLink), 'content' untuk download (webContentLink)
     * @return string|false   URL file atau false jika error
     */
    public function getFileUrl(string $fileId, string $type = 'content')
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'webViewLink, webContentLink'
            ]);

            if ($type === 'content') {
                return $file->getWebContentLink(); // langsung download / raw
            }

            return $file->getWebViewLink(); // preview / view
        } catch (\Google\Service\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function listSharedWithMe(int $pageSize = 100)
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        try {
            $results = $this->service->files->listFiles([
                'q'      => 'sharedWithMe = true',
                'fields' => 'files(id, name, mimeType, owners, sharedWithMeTime, permissions, iconLink, thumbnailLink)',
                'pageSize' => $pageSize
            ]);

            $files = $results->getFiles();

            $clean = [];

            foreach ($files as $f) {
                $clean[] = [
                    'id'                => $f->id,
                    'name'              => $f->name,
                    'mimeType'          => $f->mimeType,
                    'owner'             => isset($f->owners[0]['emailAddress']) ? $f->owners[0]['emailAddress'] : null,
                    'sharedWithMeTime'  => $f->sharedWithMeTime,
                    'iconLink'          => $f->iconLink,
                    'thumbnailLink'     => $f->thumbnailLink,
                    'permissions'       => $f->permissions,
                    'parents'           => $f->parents,
                    'webContentLink'    => $f->webContentLink,
                    'webViewLink'       => $f->webViewLink,
                    'size'              => $f->size
                ];
            }

            return $clean;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

        /**
     * Get Google Drive storage quota
     *
     * @return array|false
     * [
     *   'limit' => int|null,
     *   'used'  => int,
     *   'free'  => int|null,
     *   'usageInDrive' => int,
     *   'usageInDriveTrash' => int
     * ]
     */
    public function getStorageQuota()
    {
        if (!$this->ensureAccessTokenAvailable()) return false;

        try {
            $about = $this->service->about->get([
                'fields' => 'storageQuota'
            ]);

            $quota = $about->getStorageQuota();

            $limit = $quota->getLimit(); // bisa null (unlimited)
            $used  = $quota->getUsage();
            $usageInDrive = $quota->getUsageInDrive();
            $usageInDriveTrash = $quota->getUsageInDriveTrash();

            return [
                'limit' => $limit ? (int)$limit : null,
                'used'  => (int)$used,
                'free'  => $limit ? ((int)$limit - (int)$used) : null,
                'usageInDrive' => (int)$usageInDrive,
                'usageInDriveTrash' => (int)$usageInDriveTrash
            ];

        } catch (\Google\Service\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }


}
?>