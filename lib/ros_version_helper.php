<?php
/**
 * RouterOS Version Detection Helper
 * Deteksi versi RouterOS dan generate script yang kompatibel
 * 
 * Copyright (C) 2025 MikhMon Enhancement
 * Compatible with RouterOS v6 and v7
 */

/**
 * Deteksi versi RouterOS
 * @param RouterosAPI $API - Instance API MikroTik
 * @return int - Major version (6 atau 7)
 */
function getRouterOSVersion($API) {
    try {
        $resource = $API->comm("/system/resource/print");
        if (isset($resource[0]['version'])) {
            $version = $resource[0]['version'];
            // Extract major version (contoh: "6.49.10" -> 6, "7.12.1" -> 7)
            preg_match('/^(\d+)\./', $version, $matches);
            if (isset($matches[1])) {
                return (int)$matches[1];
            }
        }
    } catch (Exception $e) {
        // Jika gagal, default ke v6 untuk backward compatibility
        error_log("Failed to detect RouterOS version: " . $e->getMessage());
    }
    return 6; // Default fallback
}

/**
 * Generate substring function yang kompatibel dengan versi ROS
 * @param int $rosVersion - Versi RouterOS (6 atau 7)
 * @return string - Nama fungsi yang sesuai
 */
function getSubstringFunction($rosVersion) {
    return ($rosVersion >= 7) ? ':pick' : ':pic';
}

/**
 * Generate On-Login Script yang kompatibel
 * @param array $params - Parameter script (expmode, price, validity, sprice, lock, record, name)
 * @param int $rosVersion - Versi RouterOS
 * @return string - On-login script yang kompatibel
 */
function generateOnLoginScript($params, $rosVersion) {
    $expmode = $params['expmode'];
    $price = $params['price'];
    $validity = $params['validity'];
    $sprice = $params['sprice'];
    $getlock = $params['lock'];
    $name = $params['name'];
    $record = isset($params['record']) ? $params['record'] : '';
    
    // Pilih fungsi substring yang sesuai
    $substr = getSubstringFunction($rosVersion);
    
    // Generate lock script
    if ($getlock == "Enable") {
        $lock = '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]';
    } else {
        $lock = "";
    }
    
    // Generate script untuk parsing tanggal sesuai versi ROS
    if ($rosVersion >= 7) {
        // ROS v7: format YYYY-MM-DD
        $dateParseScript = ':local date [ /system clock get date ];:local year [ :pick $date 0 4 ];:local month [ :pick $date 5 7 ];';
    } else {
        // ROS v6: format mmm/DD/YYYY
        $dateParseScript = ':local date [ /system clock get date ];:local year [ :pick $date 7 11 ];:local month [ :pick $date 0 3 ];';
    }
    
    $onlogin = ':put (",",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $getlock . ',"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode ['.$substr.' $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ '.$dateParseScript.' /sys sch add name="$user" disable=no start-date=$date interval="' . $validity . '"; :delay 5s; :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run]; :local getxp [len $exp]; :if ($getxp = 15) do={ :local d ['.$substr.' $exp 0 6]; :local t ['.$substr.' $exp 7 16]; :local s ("/"); :local exp ("$d$s$year $t"); /ip hotspot user set comment="$exp" [find where name="$user"];}; :if ($getxp = 8) do={ /ip hotspot user set comment="$date $exp" [find where name="$user"];}; :if ($getxp > 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"];};:delay 5s; /sys sch remove [find where name="$user"]';
    
    if ($expmode == "rem") {
        $onlogin = $onlogin . $lock . "}}";
    } elseif ($expmode == "ntf") {
        $onlogin = $onlogin . $lock . "}}";
    } elseif ($expmode == "remc") {
        $onlogin = $onlogin . $record . $lock . "}}";
    } elseif ($expmode == "ntfc") {
        $onlogin = $onlogin . $record . $lock . "}}";
    }
    
    return $onlogin;
}


/**
 * Generate Background Service Script yang kompatibel
 * @param string $name - Nama profile
 * @param string $mode - Mode expired (remove/set limit-uptime)
 * @param int $rosVersion - Versi RouterOS
 * @return string - Background service script yang kompatibel
 */
function generateBgServiceScript($name, $mode, $rosVersion) {
    // Pilih fungsi substring yang sesuai
    $substr = getSubstringFunction($rosVersion);
    
    // Generate dateint function sesuai format tanggal ROS
    if ($rosVersion >= 7) {
        // ROS v7: format YYYY-MM-DD
        // Contoh: "2025-12-25" → parse jadi integer 20251225
        $dateintFunction = ':local dateint do={
            :local year [ :pick $d 0 4 ];
            :local month [ :pick $d 5 7 ];
            :local days [ :pick $d 8 10 ];
            :return [:tonum ("$year$month$days")];
        };';
        
        // Di ROS v7, comment format: "2025-12-25 12:30:45"
        // Waktu ada di posisi 11-19 (karena tanggal 10 char + 1 spasi)
        $timePosition = '11 19';
        
        // Check format tanggal di comment: "2025-12-25 ..." → cek posisi 4 dan 7 untuk "-"
        $dateFormatCheck = '['.$substr.' $comment 4] = "-" and ['.$substr.' $comment 7] = "-"';
        
    } else {
        // ROS v6: format mmm/DD/YYYY
        // Contoh: "dec/25/2025" → parse jadi integer 20251225
        $dateintFunction = ':local dateint do={
            :local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );
            :local days [ :pick $d 4 6 ];
            :local month [ :pick $d 0 3 ];
            :local year [ :pick $d 7 11 ];
            :local monthint ([ :find $montharray $month]);
            :local month ($monthint + 1);
            :if ( [len $month] = 1) do={
                :local zero ("0");
                :return [:tonum ("$year$zero$month$days")];
            } else={
                :return [:tonum ("$year$month$days")];
            }
        };';
        
        // Di ROS v6, comment format: "dec/25/2025 12:30:45"
        // Waktu ada di posisi 12-20 (karena tanggal 11 char + 1 spasi)
        $timePosition = '12 20';
        
        // Check format tanggal di comment: "dec/25/2025 ..." → cek posisi 3 dan 6 untuk "/"
        $dateFormatCheck = '['.$substr.' $comment 3] = "/" and ['.$substr.' $comment 6] = "/"';
    }
    
    $bgservice = $dateintFunction.' :local timeint do={ :local hours [ :pick $t 0 2 ]; :local minutes [ :pick $t 3 5 ]; :return ($hours * 60 + $minutes) ; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date] ; :local curtime [$timeint t=$time] ; :foreach i in [ /ip hotspot user find where profile="'.$name.'" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime ['.$substr.' $comment '.$timePosition.']; :if ('.$dateFormatCheck.') do={:local expd [$dateint d=$comment] ; :local expt [$timeint t=$gettime] ; :if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user '.$mode.' $i ]; [ /ip hotspot active remove [find where user=$name] ];}}}';
    
    return $bgservice;
}


/**
 * Cache versi RouterOS dalam session
 * @param string $session - Session ID
 * @param RouterosAPI $API - Instance API MikroTik
 * @return int - Major version
 */
function getCachedRouterOSVersion($session, $API) {
    $sessionKey = $session . '_ros_version';
    
    // Cek apakah sudah ada di session
    if (isset($_SESSION[$sessionKey])) {
        return $_SESSION[$sessionKey];
    }
    
    // Deteksi dan simpan ke session
    $version = getRouterOSVersion($API);
    $_SESSION[$sessionKey] = $version;
    
    return $version;
}

/**
 * Clear cache versi RouterOS (untuk re-detection)
 * @param string $session - Session ID
 */
function clearRouterOSVersionCache($session) {
    $sessionKey = $session . '_ros_version';
    if (isset($_SESSION[$sessionKey])) {
        unset($_SESSION[$sessionKey]);
    }
}

/**
 * Get RouterOS version info untuk display
 * @param RouterosAPI $API - Instance API MikroTik
 * @return array - Array dengan info version
 */
function getRouterOSVersionInfo($API) {
    try {
        $resource = $API->comm("/system/resource/print");
        if (isset($resource[0])) {
            return array(
                'version' => $resource[0]['version'] ?? 'Unknown',
                'board-name' => $resource[0]['board-name'] ?? 'Unknown',
                'platform' => $resource[0]['platform'] ?? 'Unknown',
                'architecture-name' => $resource[0]['architecture-name'] ?? 'Unknown'
            );
        }
    } catch (Exception $e) {
        error_log("Failed to get RouterOS version info: " . $e->getMessage());
    }
    return array(
        'version' => 'Unknown',
        'board-name' => 'Unknown',
        'platform' => 'Unknown',
        'architecture-name' => 'Unknown'
    );
}
?>
