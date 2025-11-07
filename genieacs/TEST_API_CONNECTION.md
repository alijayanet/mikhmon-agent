# 🧪 Test API Connection & Data

## 🎯 Tujuan

File ini menjelaskan cara test apakah API Mikhmon sudah berhasil mengambil data Virtual Parameters dari GenieACS.

## 📋 Tools Yang Tersedia

### 1. debug_device_data.php (BARU!)
**Fungsi:** Melihat struktur data mentah dari GenieACS API

**Cara Pakai:**
```
http://localhost/mikhmon-agent/genieacs/debug_device_data.php
```

**Output:**
- ✅ Check apakah VirtualParameters ada
- ✅ List semua Virtual Parameters yang tersedia
- ✅ Check nilai setiap Virtual Parameter
- ✅ Check _deviceId data
- ✅ Check InternetGatewayDevice parameters
- ✅ Raw device data structure
- ✅ Recommendations

### 2. test_fast_parser.php
**Fungsi:** Test performance parser

**Cara Pakai:**
```
http://localhost/mikhmon-agent/genieacs/test_fast_parser.php
```

### 3. test_connection.php
**Fungsi:** Test koneksi ke GenieACS server

**Cara Pakai:**
```
http://localhost/mikhmon-agent/genieacs/test_connection.php
```

## 🔍 Troubleshooting Steps

### Step 1: Test API Connection

Buka:
```
http://localhost/mikhmon-agent/genieacs/debug_device_data.php
```

**Check:**
- ✅ Apakah ada error connection?
- ✅ Apakah devices ditemukan?
- ✅ Apakah VirtualParameters ada?

### Step 2: Check Virtual Parameters

Di output debug_device_data.php, lihat section "VirtualParameters Check":

**Jika ✅ VirtualParameters EXISTS:**
- Lanjut ke Step 3

**Jika ❌ VirtualParameters NOT FOUND:**
- Virtual Parameters belum di-setup di GenieACS
- Atau device belum inform setelah setup
- **Solusi:** Setup Virtual Parameters (lihat QUICK_FIX_NA.md)

### Step 3: Check Specific Values

Di output debug_device_data.php, lihat table "Specific Virtual Parameters Values":

**Jika semua ✅ OK:**
- Data sudah ada di GenieACS
- Problem ada di Mikhmon UI
- Lanjut ke Step 4

**Jika ada ❌ N/A:**
- Virtual Parameter script belum bekerja
- **Solusi:** 
  - Test Virtual Parameter di GenieACS UI
  - Check script syntax
  - Klik Refresh di Mikhmon untuk device tersebut

### Step 4: Test di Mikhmon UI

Buka menu GenieACS di Mikhmon:
```
http://localhost/mikhmon-agent/?hotspot=genieacs&session=YOUR_SESSION
```

**Check:**
- ✅ Apakah data tampil?
- ✅ Apakah masih ada "N/A"?

**Jika masih "N/A":**
- Clear browser cache
- Reload page (Ctrl+F5)
- Check console browser (F12) untuk error JavaScript

## 📊 Expected Results

### debug_device_data.php Output

```
Debug: GenieACS Device Data Structure
Total Devices: 116

First Device: 2C3341-G663%2DXPON-GGCL25574599

1. VirtualParameters Check
✅ VirtualParameters EXISTS!

Available Virtual Parameters:
• pppoeUsername: santo
• SSID_ALL: Dirgahayu ke 80
• WlanPassword: ********
• RXPower: -20.17
• gettemp: 47.0
• pppoeIP: 192.168.10.37
• getponmode: EPON
• getSerialNumber: GGCL25574599
• getdeviceuptime: 9d 8h 26m
• activedevices: 1

2. Specific Virtual Parameters Values
Parameter          | Value              | Status
pppoeUsername      | santo              | ✅ OK
SSID_ALL           | Dirgahayu ke 80    | ✅ OK
WlanPassword       | ********           | ✅ OK
RXPower            | -20.17             | ✅ OK
gettemp            | 47.0               | ✅ OK
pppoeIP            | 192.168.10.37      | ✅ OK
getponmode         | EPON               | ✅ OK
getSerialNumber    | GGCL25574599       | ✅ OK
getdeviceuptime    | 9d 8h 26m          | ✅ OK
activedevices      | 1                  | ✅ OK

Recommendations
✅ ALL VIRTUAL PARAMETERS OK!
Semua Virtual Parameters sudah ada dan terisi.
Data seharusnya tampil di Mikhmon.
```

## 🔧 Common Issues & Solutions

### Issue 1: VirtualParameters NOT FOUND

**Penyebab:**
- Virtual Parameters belum di-setup di GenieACS
- Device belum inform setelah setup Virtual Parameters

**Solusi:**
1. Setup Virtual Parameters di GenieACS Admin UI
2. Lihat panduan di `QUICK_FIX_NA.md`
3. Tunggu device inform (5-30 menit)
4. Atau klik Refresh di Mikhmon

### Issue 2: Some Virtual Parameters N/A

**Penyebab:**
- Virtual Parameter script ada error
- Parameter path tidak sesuai dengan device
- Device tidak support parameter tersebut

**Solusi:**
1. Buka GenieACS Admin UI
2. Klik Virtual Parameters
3. Klik parameter yang N/A
4. Klik tombol "Test"
5. Pilih device
6. Lihat error message
7. Fix script sesuai error

### Issue 3: Data OK di debug tapi N/A di Mikhmon

**Penyebab:**
- Browser cache
- JavaScript error
- API endpoint salah

**Solusi:**
1. Clear browser cache (Ctrl+Shift+Del)
2. Reload page (Ctrl+F5)
3. Open browser console (F12)
4. Check untuk error JavaScript
5. Check Network tab untuk API calls

### Issue 4: Connection Error

**Penyebab:**
- GenieACS server tidak running
- Config salah (host/port)
- Firewall blocking

**Solusi:**
1. Check GenieACS service: `systemctl status genieacs-cwmp`
2. Check config di `genieacs/config.php`
3. Test connection: `curl http://localhost:7557/devices/`
4. Check firewall rules

## 📝 Checklist

Sebelum report issue, pastikan sudah:

- [ ] Virtual Parameters sudah di-setup di GenieACS
- [ ] Device sudah inform setelah setup
- [ ] Test dengan `debug_device_data.php` - VirtualParameters EXISTS
- [ ] Test dengan `debug_device_data.php` - All values OK
- [ ] Clear browser cache
- [ ] Reload page Mikhmon
- [ ] Check browser console untuk error
- [ ] Test dengan browser lain (Chrome/Firefox)

## 🎯 Quick Test Commands

### Test GenieACS API directly
```bash
# Get all devices
curl http://localhost:7557/devices/

# Get specific device
curl "http://localhost:7557/devices/?query=%7B%22_id%22%3A%22YOUR_DEVICE_ID%22%7D"
```

### Check GenieACS logs
```bash
# If using Docker
docker logs genieacs-cwmp
docker logs genieacs-nbi

# If using systemd
journalctl -u genieacs-cwmp -f
journalctl -u genieacs-nbi -f
```

### Restart GenieACS
```bash
# If using Docker
docker restart genieacs-cwmp genieacs-nbi genieacs-fs

# If using systemd
systemctl restart genieacs-cwmp genieacs-nbi genieacs-fs
```

## 📚 Related Files

- `debug_device_data.php` - Debug tool (NEW!)
- `test_fast_parser.php` - Performance test
- `QUICK_FIX_NA.md` - Virtual Parameters setup guide
- `FIX_NA_DATA.md` - Complete troubleshooting guide
- `api.php` - API functions (UPDATED - no projection)

## ✅ Success Criteria

Test berhasil jika:

1. ✅ `debug_device_data.php` shows "VirtualParameters EXISTS"
2. ✅ All Virtual Parameters have values (not N/A)
3. ✅ Data tampil di Mikhmon UI
4. ✅ No "N/A" di kolom yang seharusnya ada data
5. ✅ No JavaScript errors di browser console

---

**Created:** 2025-11-05  
**Purpose:** Testing & Troubleshooting Guide  
**Status:** Ready to Use
