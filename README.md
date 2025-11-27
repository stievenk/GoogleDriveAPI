# GoogleDriveClient PHP Library

Library PHP sederhana untuk berinteraksi dengan **Google Drive API v3**, mendukung:

- OAuth2 Authorization Code Flow
- Refresh Access Token otomatis
- Membuat folder
- Upload file kecil (multipart)
- Upload file besar (resumable / chunked)
- Download file
- Mendapatkan metadata file lengkap
- Membuat dan menghapus share link (permissions)
- Mendapatkan URL file siap pakai di `<img>` atau `<video>`
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
$drive->uploadFile(__DIR__.'/file.txt', 'file.txt', $folderId); // folderId optional
```

### 6. Upload File Besar (>10 MB)

```php
$drive->uploadLargeFile(__DIR__.'/video.mp4', 'video.mp4', $folderId); // folderId optional
```

### 7. Download File

```php
$drive->downloadFile($fileId, __DIR__.'/downloaded_video.mp4');
```

### 8. Get File Metadata

```php
$info = $drive->fileInfo($fileId);
print_r($info);
```

- Termasuk `webViewLink` dan `webContentLink` siap pakai untuk `<img>` atau `<video>`

### 9. Get Public URL untuk Web Embedding

```php
$publicUrl = $drive->getFileUrl($fileId, 'content'); // 'view' atau 'content'
```

- `'view'` → webViewLink (preview)
- `'content'` → webContentLink (raw download, bisa langsung di `<img>` atau `<video>`)

Contoh penggunaan di `<img>` atau `<video>`:

```php
echo '<img src="' . $publicUrl . '" alt="Image">';
```

```php
echo '<video controls><source src="' . $publicUrl . '" type="video/mp4"></video>';
```

### 10. Create / Remove Share Link (Permissions)

```php
$permission = $drive->createShareLink($fileId, 'reader', 'anyone');
$drive->removeShareLink($fileId, $permissionId);
```

---

## 📝 Catatan Penting

- Pastikan timezone dan server clock sinkron.
- Refresh token harus disimpan secara permanen (database/file).
- Access token bisa berubah setelah refresh.
- Untuk file besar, gunakan `webContentLink` langsung di `<video>` tag untuk streaming.
- `fileInfoRaw()` hanya untuk file kecil (<2MB) agar aman untuk memori.
- `getFileUrl()` helper memudahkan embed file di web.

---

## 🧩 Struktur Direktori Disarankan

```
/
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
  "description": "Google Drive API Client",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "Koyabu\\Googledriveapi\\": "src/"
    }
  },
  "authors": [
    {
      "name": "Stieven Kalengkian",
      "email": "stieven.kalengkian@gmail.com"
    }
  ],
  "minimum-stability": "dev",
  "require": {
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
