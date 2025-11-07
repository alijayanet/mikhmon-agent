# 🔧 Cara Mengatasi Data "N/A" di GenieACS

## 🎯 Penyebab Masalah

Data muncul "N/A" karena **GenieACS belum fetch data dari device**. GenieACS menggunakan sistem **lazy loading** - data detail tidak otomatis diambil saat device inform.

## ✅ 3 Solusi

### Solusi 1: Setup Virtual Parameters (RECOMMENDED) ⭐

Virtual Parameters adalah script di GenieACS yang otomatis fetch data saat device inform.

**Cara Setup:**

1. Buka GenieACS Admin UI: `http://localhost:3000`
2. Login dengan credentials Anda
3. Klik menu **"Virtual Parameters"**
4. Klik **"Add"** untuk setiap Virtual Parameter berikut:

#### Virtual Parameters Yang Harus Dibuat:

**1. pppoeUsername**
```javascript
let username = "";
for (let i = 1; i <= 8; i++) {
  let path = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice." + i + ".WANPPPConnection.1.Username";
  let value = declare(path, {value: Date.now()}).value[0];
  if (value && value !== "") {
    username = value;
    break;
  }
}
return username;
```

**2. SSID_ALL**
```javascript
let ssids = [];
for (let i = 1; i <= 4; i++) {
  let path = "InternetGatewayDevice.LANDevice.1.WLANConfiguration." + i + ".SSID";
  let value = declare(path, {value: Date.now()}).value[0];
  if (value && value !== "") {
    ssids.push(value);
  }
}
return ssids.join(", ");
```

**3. WlanPassword**
```javascript
let password = "";
let paths = [
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase"
];
for (let path of paths) {
  let value = declare(path, {value: Date.now()}).value[0];
  if (value && value !== "") {
    password = value;
    break;
  }
}
return password;
```

**4. RXPower**
```javascript
let rxPower = null;
let paths = [
  "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.RXPower",
  "Device.Optical.Interface.1.RxPower"
];
for (let path of paths) {
  let value = declare(path, {value: Date.now()}).value[0];
  if (value !== null) {
    rxPower = value;
    break;
  }
}
if (rxPower !== null && rxPower > 100) {
  rxPower = (rxPower / 100) - 40;
}
return rxPower;
```

**5. gettemp**
```javascript
let temp = null;
let paths = [
  "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.TransceiverTemperature",
  "InternetGatewayDevice.DeviceInfo.Temperature"
];
for (let path of paths) {
  let value = declare(path, {value: Date.now()}).value[0];
  if (value !== null) {
    temp = value;
    break;
  }
}
if (temp !== null && temp > 1000) {
  temp = temp / 256;
}
return temp;
```

**6. pppoeIP**
```javascript
let ip = "";
for (let i = 1; i <= 8; i++) {
  let path = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice." + i + ".WANPPPConnection.1.ExternalIPAddress";
  let value = declare(path, {value: Date.now()}).value[0];
  if (value && value !== "" && value !== "0.0.0.0") {
    ip = value;
    break;
  }
}
return ip;
```

**7. getponmode**
```javascript
let mode = "Unknown";
let eponPath = "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig";
let gponPath = "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig";
if (declare(eponPath, {value: Date.now()}).value[0]) mode = "EPON";
if (declare(gponPath, {value: Date.now()}).value[0]) mode = "GPON";
return mode;
```

**8. getSerialNumber**
```javascript
let serial = declare("InternetGatewayDevice.DeviceInfo.SerialNumber", {value: Date.now()}).value[0];
return serial || "";
```

**9. getdeviceuptime**
```javascript
let uptime = declare("InternetGatewayDevice.DeviceInfo.UpTime", {value: Date.now()}).value[0];
if (!uptime) uptime = declare("Device.DeviceInfo.UpTime", {value: Date.now()}).value[0];
if (uptime) {
  let s = parseInt(uptime);
  let d = Math.floor(s / 86400);
  let h = Math.floor((s % 86400) / 3600);
  let m = Math.floor((s % 3600) / 60);
  let parts = [];
  if (d > 0) parts.push(d + "d");
  if (h > 0) parts.push(h + "h");
  if (m > 0) parts.push(m + "m");
  return parts.join(" ");
}
return "0m";
```

**10. activedevices**
```javascript
let count = 0;
let hosts = declare("InternetGatewayDevice.LANDevice.1.Hosts.Host.*", {value: Date.now()});
for (let host of hosts) {
  let ip = host.value[0];
  let mac = declare(host.path + ".MACAddress", {value: Date.now()}).value[0];
  if (ip && mac) count++;
}
return count;
```

### Solusi 2: Manual Refresh Per Device

Jika Virtual Parameters belum di-setup, Anda bisa refresh manual setiap device:

**Cara:**
1. Buka menu GenieACS di Mikhmon
2. Klik tombol **🔄 Refresh** pada device yang datanya "N/A"
3. Tunggu 5-10 detik
4. Reload page

**Catatan:** Ini hanya temporary, data akan hilang lagi jika device reboot atau inform ulang.

### Solusi 3: Setup Preset di GenieACS (Advanced)

Preset adalah task yang otomatis dijalankan saat device inform.

**Cara Setup:**

1. Buka GenieACS Admin UI: `http://localhost:3000`
2. Klik menu **"Presets"**
3. Klik **"Add"**
4. Buat preset dengan konfigurasi:

**Name:** `Auto Refresh All Parameters`

**Precondition:**
```javascript
true
```

**Configurations:**
```javascript
// Refresh all important parameters
let params = [
  "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.*.WANPPPConnection.1.Username",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.SSID",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.KeyPassphrase",
  "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.RXPower",
  "InternetGatewayDevice.DeviceInfo.Temperature",
  "InternetGatewayDevice.DeviceInfo.UpTime"
];

for (let param of params) {
  declare(param, {value: Date.now()});
}
```

**Weight:** `0`

**Schedule:** Biarkan kosong (akan run setiap inform)

## 🧪 Testing

Setelah setup Virtual Parameters:

1. **Tunggu device inform** (biasanya 5-30 menit)
2. Atau **force refresh** dengan klik tombol Refresh di UI
3. **Reload page** di Mikhmon
4. Data seharusnya sudah terisi

## 📊 Verifikasi

Cek apakah Virtual Parameters sudah bekerja:

1. Buka GenieACS Admin UI
2. Klik salah satu device
3. Scroll ke section **"VirtualParameters"**
4. Pastikan ada data seperti:
   - `VirtualParameters.pppoeUsername: "santo"`
   - `VirtualParameters.SSID_ALL: "Dirgahayu ke 80"`
   - `VirtualParameters.RXPower: "-20.17"`
   - dll.

## ⚠️ Troubleshooting

### Data masih "N/A" setelah setup Virtual Parameters

**Penyebab:**
- Device belum inform ulang
- Virtual Parameters script ada error
- Parameter path tidak sesuai dengan device Anda

**Solusi:**
1. Check GenieACS logs: `docker logs genieacs-cwmp` (jika pakai Docker)
2. Test Virtual Parameter di GenieACS UI (ada tombol Test)
3. Cek parameter path yang benar untuk device Anda

### Cara cek parameter path yang benar

1. Buka GenieACS Admin UI
2. Klik salah satu device
3. Klik tab **"All Parameters"**
4. Cari parameter yang Anda butuhkan (misal: SSID, Username, dll)
5. Copy path-nya
6. Update Virtual Parameter script dengan path yang benar

### Device tidak inform

**Penyebab:**
- Device offline
- TR-069 tidak enabled di device
- ACS URL salah di device

**Solusi:**
1. Pastikan device online
2. Login ke device web UI
3. Cek TR-069 settings
4. Pastikan ACS URL benar: `http://YOUR_SERVER_IP:7547`

## 📝 Catatan Penting

### Tentang Virtual Parameters

- Virtual Parameters **HARUS** di-setup di GenieACS server
- Setiap vendor ONU punya parameter path yang berbeda
- Script di atas adalah untuk **vendor umum** (Huawei, ZTE, Fiberhome)
- Anda mungkin perlu **adjust path** sesuai device Anda

### Tentang Performance

- Virtual Parameters akan **sedikit memperlambat** inform process
- Tapi ini **normal** dan **worth it** untuk mendapat data lengkap
- Jika punya 1000+ devices, pertimbangkan untuk:
  - Hanya fetch parameter yang penting
  - Gunakan conditional script (hanya fetch jika data belum ada)

### Tentang Security

- **WiFi Password** akan tersimpan di GenieACS database
- Pastikan GenieACS server Anda **secure**
- Gunakan HTTPS untuk akses GenieACS UI
- Restrict access dengan firewall

## 🎯 Rekomendasi

Untuk hasil terbaik:

1. ✅ **Setup Virtual Parameters** (Solusi 1) - WAJIB
2. ✅ **Setup Preset** (Solusi 3) - Optional tapi recommended
3. ✅ **Test dengan 1-2 device** dulu sebelum apply ke semua
4. ✅ **Monitor GenieACS logs** untuk detect error
5. ✅ **Backup Virtual Parameters script** Anda

## 📚 Referensi

- GenieACS Documentation: https://docs.genieacs.com/
- Virtual Parameters Guide: https://docs.genieacs.com/en/latest/virtual-parameters.html
- TR-069 Parameter List: https://www.broadband-forum.org/

## 🆘 Butuh Bantuan?

Jika masih ada masalah:

1. Check GenieACS logs
2. Test Virtual Parameter script di GenieACS UI
3. Verify parameter path untuk device Anda
4. Pastikan device sudah inform ke GenieACS

---

**Dibuat:** 2025-11-05  
**Update Terakhir:** 2025-11-05  
**Status:** Ready to Use
