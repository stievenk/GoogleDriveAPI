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
}
?>