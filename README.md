# GoogleDriveClient PHP Library

Library PHP sederhana untuk berinteraksi dengan **Google Drive API v3**, mendukung:

- OAuth2 Authorization Code Flow
- Refresh Access Token otomatis
- Membuat folder
- Upload file kecil (multipart)
- Upload file besar (resumable / chunked)
- Download file
- Membuat dan menghapus share link (permissions)
- Penanganan error terpusat
- Kompatibel dan siap dipublish via **Composer**

---

## 📦 Instalasi

Instalasi melalui composer:

```bash
composer require koyabu/googledriveapi
```

---

## 🔧 Konfigurasi Google Drive App

1. Buka https://console.developers.google.com/
2. Buat project baru
3. Aktifkan **Google Drive API**
4. Buat OAuth 2.0 Client ID
5. Atur Redirect URI, contoh:
   ```
   https://example.com/drive/callback
   ```
6. Catat **Client ID** dan **Client Secret**

---

## 🚀 Cara Menggunakan

### 1. Inisialisasi Class

```php
use Koyabu\GoogleDriveApi\GoogleDriveClient;

$drive = new GoogleDriveClient([
    'client_id'     => 'YOUR_CLIENT_ID',
    'client_secret' => 'YOUR_CLIENT_SECRET',
    'redirect_uri'  => 'https://example.com/drive/callback',
]);
```

---

### 2. Mendapatkan Authorization URL

```php
echo $drive->getAuthUrl();
```

User akan login dan mendapatkan **authorization code**.

### 3. Tukar Authorization Code menjadi Token

```php
$token = $drive->fetchAccessToken($_GET['code']);
// Simpan access token & refresh token
```

### 4. Membuat Folder

```php
$result = $drive->createFolder('Backup');
if (!$result) {
    echo $drive->lastError;
}
```

### 5. Upload File Kecil (<10 MB)

```php
$drive->uploadFile(__DIR__.'/file.txt', 'file.txt');
```

### 6. Upload File Besar (>10 MB)

```php
$drive->uploadLargeFile(__DIR__.'/video.mp4', 'video.mp4');
```

### 7. Download File

```php
$drive->downloadFile($fileId, __DIR__.'/downloaded_video.mp4');
```

### 8. Create Share Link / Permission

```php
$permission = $drive->createShareLink($fileId, 'reader', 'anyone');
```

### 9. Remove Share Link / Permission

```php
$drive->removeShareLink($fileId, $permissionId);
```

---

## 📝 Catatan Penting

- Pastikan timezone dan server clock sinkron.
- Refresh token harus disimpan secara permanen (database/file).
- Access token bisa berubah setelah refresh.
- Upload file besar otomatis chunked dan resumable.

---

## 🧩 Struktur Direktori Disarankan

```
/your-library
│── src/
│   └── GoogleDriveClient.php
│── composer.json
│── README.md
│── LICENSE
```

---

## 🧪 composer.json Contoh

```json
{
  "name": "koyabu/googledriveapi",
  "description": "Simple Google Drive API v3 Client for PHP",
  "type": "library",
  "autoload": {
    "psr-4": {
      "Koyabu\\GoogleDriveApi\\": "src/"
    }
  },
  "require": {
    "php": ">=7.4",
    "google/apiclient": "^2.0"
  }
}
```

---

## 🤝 Kontribusi

Pull request, bug report, dan perbaikan sangat diterima.

---

## 📄 Lisensi

MIT License atau sesuai kebutuhan Anda.
