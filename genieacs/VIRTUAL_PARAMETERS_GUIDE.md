# 📋 Virtual Parameters Guide

## 🎯 Apa itu Virtual Parameters?

Virtual Parameters adalah custom parameters yang dibuat di GenieACS untuk:
- Mengambil data dari multiple paths dengan logic
- Melakukan kalkulasi/transformasi data
- Menggabungkan data dari beberapa parameter

## ✅ Virtual Parameters Yang DIPERLUKAN

Virtual Parameters ini **WAJIB** di-setup karena data tidak bisa diambil langsung:

### 1. PPPoE Username
**Name:** `pppoeUsername`  
**Alasan:** Perlu loop untuk cek multiple WAN connections (1-8)

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

### 2. PPPoE IP
**Name:** `pppoeIP`  
**Alasan:** Perlu loop untuk cek multiple WAN connections

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

### 3. RX Power
**Name:** `RXPower`  
**Alasan:** Perlu konversi nilai (divide by 100, minus 40)

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

### 4. Temperature
**Name:** `gettemp`  
**Alasan:** Perlu konversi nilai (divide by 256)

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

### 5. PON Mode
**Name:** `getponmode`  
**Alasan:** Perlu detect EPON vs GPON

```javascript
let mode = "Unknown";
let eponPath = "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig";
let gponPath = "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig";
if (declare(eponPath, {value: Date.now()}).value[0]) mode = "EPON";
if (declare(gponPath, {value: Date.now()}).value[0]) mode = "GPON";
return mode;
```

### 6. Device Uptime (Formatted)
**Name:** `getdeviceuptime`  
**Alasan:** Perlu format dari seconds ke "Xd Xh Xm"

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

### 7. Active Devices Count
**Name:** `activedevices`  
**Alasan:** Perlu count dari array hosts

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

### 8. Serial Number
**Name:** `getSerialNumber`  
**Alasan:** Fallback jika standard path tidak ada

```javascript
let serial = declare("InternetGatewayDevice.DeviceInfo.SerialNumber", {value: Date.now()}).value[0];
return serial || "";
```

## ❌ Virtual Parameters Yang TIDAK DIPERLUKAN

Virtual Parameters ini **TIDAK PERLU** di-setup karena data bisa diambil langsung dari standard TR-069 path:

### ❌ SSID
**TIDAK PERLU!** Gunakan path standard:
```
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID
```

### ❌ WiFi Password
**TIDAK PERLU!** Gunakan path standard:
```
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase
```

### ❌ MAC Address
**TIDAK PERLU!** Sudah ada di `_deviceId` atau bisa diambil dari standard path.

## 📊 Summary

| Parameter | Virtual Parameter? | Alasan |
|-----------|-------------------|--------|
| **PPPoE Username** | ✅ YES | Perlu loop WAN connections |
| **PPPoE IP** | ✅ YES | Perlu loop WAN connections |
| **RX Power** | ✅ YES | Perlu konversi nilai |
| **Temperature** | ✅ YES | Perlu konversi nilai |
| **PON Mode** | ✅ YES | Perlu detect EPON/GPON |
| **Device Uptime** | ✅ YES | Perlu format output |
| **Active Devices** | ✅ YES | Perlu count array |
| **Serial Number** | ✅ YES | Fallback path |
| **SSID** | ❌ NO | Ada di standard path |
| **WiFi Password** | ❌ NO | Ada di standard path |
| **MAC Address** | ❌ NO | Ada di _deviceId |

## 🎯 Prioritas Setup

### Minimal (Wajib):
1. ✅ `pppoeUsername` - PPPoE Username
2. ✅ `pppoeIP` - PPPoE IP
3. ✅ `RXPower` - Optical RX Power
4. ✅ `gettemp` - Temperature
5. ✅ `getponmode` - PON Mode

### Recommended (Sangat Berguna):
6. ✅ `getdeviceuptime` - Formatted Uptime
7. ✅ `activedevices` - Connected Devices Count
8. ✅ `getSerialNumber` - Serial Number

### Optional (Tidak Wajib):
- ❌ SSID - Tidak perlu, pakai standard path
- ❌ WlanPassword - Tidak perlu, pakai standard path
- ❌ SSID_ALL - Tidak perlu, pakai standard path

## 🔧 Cara Setup

1. **Buka GenieACS Admin UI:**
   ```
   http://localhost:3000
   ```

2. **Klik menu "Virtual Parameters"**

3. **Klik "Add" untuk setiap parameter**

4. **Copy-paste script dari list di atas**

5. **Klik "Save"**

6. **Tunggu device inform atau force refresh**

## ✅ Verifikasi

Setelah setup, test dengan:
```
http://localhost/mikhmon-agent/genieacs/debug_device_data.php
```

**Expected Result:**
```
✅ VirtualParameters EXISTS!
✅ pppoeUsername: "santo"
✅ RXPower: "-20.17"
✅ gettemp: "47"
✅ pppoeIP: "192.168.10.37"
✅ getponmode: "EPON"
✅ getdeviceuptime: "9d 8h 26m"
✅ activedevices: "1"
```

## 📝 Notes

1. **Virtual Parameters hanya untuk data yang perlu processing**
2. **Data yang bisa diambil langsung, gunakan standard path**
3. **Lebih sedikit Virtual Parameters = lebih cepat & reliable**
4. **Fast Parser sudah handle standard paths dengan baik**

---

**Created:** 2025-11-05  
**Purpose:** Guide untuk setup Virtual Parameters yang benar  
**Status:** Ready to Use
