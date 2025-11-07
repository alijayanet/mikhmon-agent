# Setup SSID Virtual Parameter di GenieACS

## Problem
SSID tidak tersimpan di GenieACS database karena data WLAN tidak di-fetch secara default.

## Solution
Buat Virtual Parameter di GenieACS untuk extract SSID dari device.

## Steps

### 1. Login ke GenieACS UI
```
http://192.168.8.89:7557
```

### 2. Buka Admin → Virtual Parameters
Klik menu **Admin** → **Virtual Parameters**

### 3. Add New Virtual Parameter - SSID (WRITABLE)

**Name:** `SSID`

**Script:** (Writable - bisa di-edit dari MikhMon)
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

### 4. Add New Virtual Parameter - SSID_ALL (All SSIDs)

**Name:** `SSID_ALL`

**Script:**
```javascript
// Get all SSIDs from device
let ssids = [];

for (let i = 1; i <= 4; i++) {
  try {
    let path = "InternetGatewayDevice.LANDevice.1.WLANConfiguration." + i + ".SSID";
    let value = declare(path, {value: Date.now()}).value;
    if (value && value[0]) {
      ssids.push(value[0]);
    }
  } catch (e) {
    // SSID not available
  }
}

return ssids.join(", ");
```

### 5. Add New Virtual Parameter - WlanPassword (WRITABLE)

**Name:** `WlanPassword`

**Script:** (Writable - bisa di-edit dari MikhMon)
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

### 6. Save & Apply

Klik **Save** untuk setiap Virtual Parameter.

### 7. Test Virtual Parameter

1. Buka device di GenieACS UI
2. Klik tab **Summary**
3. Scroll ke bawah, cari **Virtual Parameters**
4. Seharusnya ada `SSID`, `SSID_ALL`, `WlanPassword`
5. Jika masih kosong, klik **Refresh** atau **Connection Request**

### 8. Update MikhMon Code

Setelah Virtual Parameter dibuat, update `api_devices.php`:

```php
// SSID - Use Virtual Parameter
$ssid = get_value($device, array(
    'VirtualParameters.SSID_ALL',
    'VirtualParameters.SSID'
));

// WiFi Password - Use Virtual Parameter
$wifi_password = get_value($device, array(
    'VirtualParameters.WlanPassword'
));
```

## Verification

Setelah setup:
1. Refresh device di MikhMon
2. Wait 10 seconds
3. Reload device list
4. SSID dan Password seharusnya muncul

## Alternative: Preset Configuration

Jika Virtual Parameter tidak bekerja, tambahkan **Preset** di GenieACS untuk auto-fetch WLAN data saat device connect.

**Admin → Presets → Add New**

**Name:** `Fetch WLAN Data`

**Precondition:**
```javascript
true
```

**Configurations:**
```javascript
// Refresh WLAN Configuration
declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.*", {value: Date.now()});
```

**Weight:** `0`

**Apply preset** ke semua devices.

## Notes

- Virtual Parameters akan di-evaluate setiap kali device melakukan Inform
- Jika SSID masih N/A, device belum melakukan Inform atau WLAN path tidak ada
- Check GenieACS logs untuk error messages
