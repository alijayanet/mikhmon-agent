# GenieACS Integration for MikhMon

Integrasi GenieACS untuk management ONU/CPE devices melalui TR-069 protocol.

## 📋 Fitur

- ✅ Dashboard dengan statistik ONU (Total, Online, Offline)
- ✅ List semua ONU devices yang terhubung
- ✅ Monitor status online/offline real-time
- ✅ Ubah WiFi SSID dan Password (2.4GHz & 5GHz)
- ✅ View device information lengkap (Model, Serial, MAC, Firmware, dll)
- ✅ Refresh device data
- ✅ Reboot ONU devices
- ✅ Search dan filter devices

## 🚀 Setup

### 1. Copy Config File

```bash
cp config.example.php config.php
```

### 2. Edit Konfigurasi

Edit file `config.php` dan sesuaikan dengan server GenieACS Anda:

```php
// GenieACS Server Configuration
define('GENIEACS_HOST', 'localhost');  // IP/hostname GenieACS server
define('GENIEACS_PORT', '7557');       // Port NBI (default 7557)
define('GENIEACS_PROTOCOL', 'http');   // http atau https

// Enable/Disable
define('GENIEACS_ENABLED', true);      // Set true untuk enable
```

### 3. Sesuaikan TR-069 Parameter Paths

Sesuaikan path parameter sesuai dengan model ONU Anda. Default menggunakan `InternetGatewayDevice` (untuk Huawei/ZTE/Fiberhome):

```php
// WiFi 2.4GHz
define('GENIEACS_WIFI_SSID_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
define('GENIEACS_WIFI_PASSWORD_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey');

// WiFi 5GHz
define('GENIEACS_WIFI_SSID_5G_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID');
define('GENIEACS_WIFI_PASSWORD_5G_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.PreSharedKey');
```

**Untuk ONU dengan Device.WiFi model:**
```php
define('GENIEACS_WIFI_SSID_PATH', 'Device.WiFi.SSID.1.SSID');
define('GENIEACS_WIFI_PASSWORD_PATH', 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase');
```

### 4. Akses Menu

Setelah setup, akses menu **GenieACS - ONU Management** di sidebar admin MikhMon.

## 📁 Struktur Folder

```
genieacs/
├── config.example.php      # Contoh konfigurasi
├── config.php              # Konfigurasi aktif (buat dari example)
├── index.php               # Dashboard GenieACS
├── devices.php             # List devices (coming soon)
├── device_detail.php       # Detail device (coming soon)
├── api.php                 # API handler (coming soon)
├── lib/
│   └── GenieACS.class.php  # Library GenieACS API
└── README.md               # Dokumentasi ini
```

## 🔧 Requirements

1. **GenieACS Server** harus sudah running
   - GenieACS NBI (port 7557)
   - GenieACS FS (port 7567)
   - GenieACS UI (port 3000)

2. **ONU Devices** harus sudah configured dengan TR-069:
   - ACS URL: `http://your-genieacs-server:7547`
   - ACS Username/Password (jika diperlukan)
   - Periodic Inform enabled

3. **Network Connectivity**:
   - MikhMon server bisa akses GenieACS server
   - ONU devices bisa akses GenieACS server

## 📖 Cara Menggunakan

### Dashboard
1. Login ke admin MikhMon
2. Klik menu **GenieACS - ONU Management** di sidebar
3. Lihat statistik total devices, online, dan offline

### View Devices
1. Klik **View All Devices** atau **Online Devices Only**
2. Gunakan search box untuk cari device berdasarkan Serial, MAC, atau Model
3. Filter devices berdasarkan status (online/offline)

### Ubah WiFi SSID/Password
1. Pilih device yang ingin diubah
2. Klik tombol **Change WiFi**
3. Masukkan SSID dan Password baru
4. Klik **Apply Changes**
5. Tunggu beberapa saat hingga device apply perubahan

### Refresh Device Data
1. Pilih device
2. Klik tombol **Refresh**
3. Tunggu device mengirim data terbaru ke GenieACS

## ⚙️ Troubleshooting

### GenieACS not enabled
**Problem:** Muncul pesan "GenieACS is not enabled"

**Solution:**
1. Pastikan file `config.php` sudah dibuat (copy dari `config.example.php`)
2. Set `GENIEACS_ENABLED` menjadi `true`
3. Refresh halaman

### No devices found
**Problem:** Tidak ada device yang muncul

**Solution:**
1. Cek GenieACS server sudah running: `curl http://localhost:7557/devices/`
2. Pastikan ONU sudah terhubung ke GenieACS (cek di GenieACS UI)
3. Cek network connectivity antara MikhMon dan GenieACS
4. Cek konfigurasi `GENIEACS_HOST` dan `GENIEACS_PORT` sudah benar

### Cannot change WiFi
**Problem:** Gagal ubah WiFi SSID/Password

**Solution:**
1. Pastikan device dalam status **Online**
2. Cek TR-069 parameter path sudah sesuai dengan model ONU
3. Cek di GenieACS UI apakah task berhasil dijalankan
4. Beberapa ONU memerlukan waktu untuk apply perubahan (tunggu 1-2 menit)

### Wrong parameter paths
**Problem:** Data device tidak muncul atau salah

**Solution:**
1. Cek model ONU Anda (Huawei, ZTE, Fiberhome, dll)
2. Sesuaikan parameter paths di `config.php`
3. Gunakan GenieACS UI untuk explore parameter yang tersedia
4. Update path di config sesuai dengan struktur parameter ONU Anda

## 🔐 Security

1. **Basic Authentication**: Jika GenieACS menggunakan Basic Auth, set username/password di config:
```php
define('GENIEACS_USERNAME', 'admin');
define('GENIEACS_PASSWORD', 'password');
```

2. **HTTPS**: Untuk production, gunakan HTTPS:
```php
define('GENIEACS_PROTOCOL', 'https');
```

3. **Firewall**: Pastikan hanya server yang dipercaya yang bisa akses GenieACS

## 📚 Referensi

- [GenieACS Documentation](https://docs.genieacs.com/)
- [GenieACS API Reference](https://docs.genieacs.com/en/latest/api-reference.html)
- [TR-069 Protocol](https://www.broadband-forum.org/technical/download/TR-069.pdf)

## 📝 Changelog

### Version 1.0.0 (2025-11-05)
- ✅ Initial release
- ✅ Dashboard dengan statistik
- ✅ GenieACS API wrapper class
- ✅ Integration dengan MikhMon admin menu

## 👨‍💻 Support

Untuk pertanyaan atau issue, silakan hubungi administrator sistem.
