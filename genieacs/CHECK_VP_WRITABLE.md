# Check Virtual Parameter Writable

## Problem
SSID dan Password tidak berubah di ONU setelah save.

## Penyebab
Virtual Parameter mungkin **read-only** atau format task salah.

## Solusi

### 1. Cek Virtual Parameter di GenieACS

Login ke GenieACS UI:
```
http://192.168.8.89:7557
```

**Admin → Virtual Parameters**

Cek Virtual Parameter `SSID` dan `WlanPassword`:

#### Virtual Parameter SSID harus seperti ini:

```javascript
// WLAN SSID - Writable Virtual Parameter
let m = "";

if (args[1].value) {
  // SET mode - update SSID on device
  m = args[1].value[0];
  declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", null, {value: m});
}
else {
  // GET mode - read SSID from device
  let v = declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", {value: Date.now()});
  
  if (v.size) {
    m = v.value[0];
  }
}

return {writable: true, value: [m, "xsd:string"]};
```

**PENTING:** Harus ada `return {writable: true, value: [m, "xsd:string"]};`

#### Virtual Parameter WlanPassword harus seperti ini:

```javascript
// WLAN Password - Writable Virtual Parameter
let m = "";

if (args[1].value) {
  // SET mode - update password on device
  m = args[1].value[0];
  declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", null, {value: m});
  declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase", null, {value: m});
}
else {
  // GET mode - read password from device
  let v1 = declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase", {value: Date.now()});
  let v2 = declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase", {value: Date.now()});

  if (v1.size) {
    m = v1.value[0];
  }
  else if (v2.size) {
    m = v2.value[0];
  }
}

return {writable: true, value: [m, "xsd:string"]};
```

**PENTING:** Harus ada `return {writable: true, value: [m, "xsd:string"]};`

### 2. Test Manual di GenieACS UI

1. Buka device di GenieACS UI
2. Klik tab **Summary**
3. Scroll ke **Virtual Parameters**
4. Klik **Edit** icon di samping `SSID`
5. Ubah value
6. Klik **Save**
7. Tunggu beberapa detik
8. Cek apakah SSID berubah di ONU

Jika berubah di GenieACS UI tapi tidak dari MikhMon, berarti masalah di format task.

### 3. Cek Task di GenieACS

**Admin → Tasks**

Lihat task yang dibuat saat save WiFi dari MikhMon:
- Status harus **done** atau **completed**
- Jika **fault**, lihat error message
- Jika **pending**, device belum respond

### 4. Alternative: Direct Parameter Path

Jika Virtual Parameter tidak bekerja, gunakan direct path:

Edit `save_wifi.php`:

```php
// Direct path instead of Virtual Parameter
if (!empty($ssid)) {
    $params_to_set[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $ssid, 'xsd:string'];
}

if (!empty($password)) {
    // Try both paths
    $params_to_set[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $password, 'xsd:string'];
    $params_to_set[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', $password, 'xsd:string'];
}
```

### 5. Cek Connection Request

Device harus **online** dan **bisa menerima connection request** dari GenieACS.

**Admin → Devices → [Device] → Connection Request**

Klik **Send** untuk test connection request.

Jika gagal:
- Cek firewall di ONU
- Cek IP TR069 accessible dari GenieACS server
- Cek port 7547 (default TR069 port) terbuka

### 6. Debug Task

Tambahkan logging di `save_wifi.php`:

```php
// Log task before sending
error_log("GenieACS Save WiFi Task: " . json_encode($task));
error_log("GenieACS Save WiFi Result: " . json_encode($result));
```

Cek log di:
- Windows: `C:\xampp3\apache\logs\error.log`
- Linux: `/var/log/apache2/error.log`

## Verification

Setelah fix:
1. Edit WiFi dari MikhMon
2. Tunggu 10-15 detik
3. Cek SSID di ONU (via web UI atau WiFi scanner)
4. Cek task status di GenieACS UI
5. SSID seharusnya berubah

## Common Issues

### Issue 1: Virtual Parameter Read-Only
**Symptom:** Task created tapi SSID tidak berubah
**Fix:** Pastikan VP return `{writable: true, ...}`

### Issue 2: Connection Request Failed
**Symptom:** Task stuck di pending
**Fix:** Cek network connectivity, firewall, TR069 port

### Issue 3: Wrong Parameter Path
**Symptom:** Task fault dengan error "Invalid parameter"
**Fix:** Gunakan direct path atau cek manufacturer-specific path

### Issue 4: Device Rejects Change
**Symptom:** Task done tapi value tidak berubah
**Fix:** Cek device logs, mungkin ada restriction di ONU firmware
