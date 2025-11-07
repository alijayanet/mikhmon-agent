# ⚡ Quick Fix: Data "N/A" di GenieACS

## 🎯 Masalah
Banyak data muncul "N/A" seperti:
- ❌ WiFi SSID: N/A
- ❌ WiFi Password: N/A  
- ❌ RX Power: N/A
- ❌ Temperature: N/A
- ❌ PPPoE Username: N/A

## ✅ Solusi Cepat (5 Menit)

### Step 1: Buka GenieACS Admin UI
```
http://localhost:3000
```
atau
```
http://YOUR_SERVER_IP:3000
```

### Step 2: Login
Gunakan credentials GenieACS Anda

### Step 3: Setup Virtual Parameters

Klik menu **"Virtual Parameters"** → Klik **"Add"**

Copy-paste script berikut **satu per satu**:

#### 1️⃣ PPPoE Username
**Name:** `pppoeUsername`
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

#### 2️⃣ WiFi SSID
**Name:** `SSID_ALL`
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

#### 3️⃣ WiFi Password
**Name:** `WlanPassword`
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

#### 4️⃣ RX Power
**Name:** `RXPower`
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

#### 5️⃣ Temperature
**Name:** `gettemp`
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

#### 6️⃣ PPPoE IP
**Name:** `pppoeIP`
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

#### 7️⃣ PON Mode
**Name:** `getponmode`
```javascript
let mode = "Unknown";
let eponPath = "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig";
let gponPath = "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig";
if (declare(eponPath, {value: Date.now()}).value[0]) mode = "EPON";
if (declare(gponPath, {value: Date.now()}).value[0]) mode = "GPON";
return mode;
```

#### 8️⃣ Serial Number
**Name:** `getSerialNumber`
```javascript
let serial = declare("InternetGatewayDevice.DeviceInfo.SerialNumber", {value: Date.now()}).value[0];
return serial || "";
```

#### 9️⃣ Device Uptime
**Name:** `getdeviceuptime`
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

#### 🔟 Active Devices
**Name:** `activedevices`
```javascript
let count = 0;
let hosts = declare("InternetGatewayDevice.LANDevice.1.Hosts.Host.*", {value: Date.now()});
for (let host of hosts) {
  let ip = host.value[0];
  let mac = declare(host.path + ".MACAddress", {value: Date.now()}).value[0];
  if (ip && mac && ip !== "0.0.0.0") count++;
}
return count;
```

### Step 4: Test

1. **Pilih 1 device** di GenieACS UI
2. Klik device tersebut
3. Klik tab **"VirtualParameters"**
4. Pastikan ada data seperti:
   - `pppoeUsername: "santo"`
   - `SSID_ALL: "Dirgahayu ke 80"`
   - `RXPower: "-20.17"`
   - dll.

### Step 5: Refresh Mikhmon

1. Buka menu GenieACS di Mikhmon
2. Klik tombol **🔄 Refresh** pada 1-2 device
3. Tunggu 5-10 detik
4. **Reload page**
5. Data seharusnya sudah terisi! ✅

## 🎯 Hasil Yang Diharapkan

Setelah setup, data akan terisi:
- ✅ WiFi SSID: "Dirgahayu ke 80"
- ✅ WiFi Password: "********" (hidden)
- ✅ RX Power: "-20.17 dBm"
- ✅ Temperature: "47.0°C"
- ✅ PPPoE Username: "santo"
- ✅ PPPoE IP: "192.168.10.37"
- ✅ PON Mode: "EPON"
- ✅ Serial: "GGCL25574599"
- ✅ Active Devices: "1"

## ⚠️ Troubleshooting

### Data masih "N/A" setelah setup?

**Coba ini:**

1. **Force Refresh Device**
   - Klik tombol 🔄 Refresh di Mikhmon
   - Tunggu 10 detik
   - Reload page

2. **Check GenieACS Logs**
   ```bash
   # Jika pakai Docker
   docker logs genieacs-cwmp
   
   # Jika install manual
   journalctl -u genieacs-cwmp -f
   ```

3. **Test Virtual Parameter**
   - Buka GenieACS UI
   - Klik Virtual Parameters
   - Klik salah satu parameter
   - Klik tombol "Test"
   - Pilih device
   - Lihat hasilnya

4. **Check Parameter Path**
   - Buka GenieACS UI
   - Klik salah satu device
   - Klik tab "All Parameters"
   - Cari parameter yang Anda butuhkan
   - Copy path yang benar
   - Update Virtual Parameter script

### Device tidak inform?

**Pastikan:**
- ✅ Device online
- ✅ TR-069 enabled di device
- ✅ ACS URL benar: `http://YOUR_SERVER_IP:7547`
- ✅ GenieACS service running

## 📝 Catatan Penting

1. **Virtual Parameters WAJIB di-setup** untuk data lengkap
2. **Tunggu device inform** (5-30 menit) atau force refresh
3. **Script di atas untuk vendor umum** (Huawei, ZTE, Fiberhome)
4. **Adjust path jika perlu** sesuai device Anda
5. **Test dengan 1-2 device** dulu sebelum apply ke semua

## 🎉 Selesai!

Setelah setup Virtual Parameters, semua data akan otomatis terisi setiap kali device inform ke GenieACS. Tidak perlu refresh manual lagi!

---

**Butuh bantuan?** Lihat dokumentasi lengkap di `FIX_NA_DATA.md`
