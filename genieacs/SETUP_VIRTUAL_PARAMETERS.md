# Setup Virtual Parameters di GenieACS

## Masalah
SSID, WiFi Password, dan IP PPPoE tidak muncul karena Virtual Parameters belum dikonfigurasi di GenieACS server.

## Data yang Sudah Tersedia
✅ PPPoE Username (VirtualParameters.pppoeUsername)
✅ RX Power (VirtualParameters.RXPower)  
✅ Serial Number (VirtualParameters.getSerialNumber)
✅ PON Mode (VirtualParameters.getponmode)
✅ Active Clients (TotalAssociations)

## Virtual Parameters yang Perlu Ditambahkan

### 1. SSID
**Path di device:** `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID`

**Virtual Parameter Script:**
```javascript
// Name: SSID
let ssid = declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID", {value: 1}).value;
return ssid ? ssid[0] : "";
```

### 2. WiFi Password
**Path di device:** `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey`

**Virtual Parameter Script:**
```javascript
// Name: wifiPassword
let pass = declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey", {value: 1}).value;
return pass ? pass[0] : "";
```

### 3. PPPoE IP Address
**Path di device:** Tergantung device model, biasanya:
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress`
- Atau dari status PPPoE connection

**Virtual Parameter Script:**
```javascript
// Name: pppoeIP
let ip = declare("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress", {value: 1}).value;
return ip ? ip[0] : "";
```

## Cara Menambahkan Virtual Parameters

### Via GenieACS UI:

1. **Akses GenieACS UI:**
   ```
   http://192.168.8.89:7557
   ```

2. **Buka Admin → Virtual Parameters**

3. **Klik "Add Virtual Parameter"**

4. **Isi form:**
   - **Name:** `SSID`
   - **Script:** (copy script di atas)
   - Klik **Save**

5. **Ulangi untuk `wifiPassword` dan `pppoeIP`**

### Via API (curl):

```bash
# Add SSID Virtual Parameter
curl -i 'http://192.168.8.89:7557/provisions/SSID' \
-X PUT \
--data '{
  "script": "let ssid = declare(\"InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID\", {value: 1}).value; return ssid ? ssid[0] : \"\";"
}'

# Add WiFi Password Virtual Parameter  
curl -i 'http://192.168.8.89:7557/provisions/wifiPassword' \
-X PUT \
--data '{
  "script": "let pass = declare(\"InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey\", {value: 1}).value; return pass ? pass[0] : \"\";"
}'

# Add PPPoE IP Virtual Parameter
curl -i 'http://192.168.8.89:7557/provisions/pppoeIP' \
-X PUT \
--data '{
  "script": "let ip = declare(\"InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress\", {value: 1}).value; return ip ? ip[0] : \"\";"
}'
```

## Setelah Menambahkan Virtual Parameters

1. **Refresh device** di MikhMon (klik tombol Refresh)
2. **Atau tunggu device inform** berikutnya
3. **Data SSID, Password, dan IP** akan muncul

## Catatan

- Virtual Parameters harus di-declare agar GenieACS fetch data dari device
- Setelah ditambahkan, perlu trigger refresh atau tunggu inform berikutnya
- Path parameter bisa berbeda tergantung model device (ZTE, Huawei, dll)
- Gunakan GenieACS UI untuk explore device data model dan temukan path yang tepat

## Troubleshooting

**Jika masih tidak muncul setelah setup:**

1. Cek di GenieACS UI apakah Virtual Parameter sudah terdaftar
2. Klik device → Cek apakah SSID muncul di device parameters
3. Trigger refresh manual dari GenieACS UI
4. Cek GenieACS logs untuk error
5. Verify path parameter sesuai dengan device model Anda
