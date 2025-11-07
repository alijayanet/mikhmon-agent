<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  $getbridge = $API->comm("/interface/bridge/print");
  $getremoteaddress = $API->comm("/ip/pool/print");
  $getprofiles = $API->comm("/ppp/profile/print");

  if (isset($_POST['name'])) {
    $name = (preg_replace('/\s+/', '-', $_POST['name']));
    $localaddress = ($_POST['localaddress']);
    $remoteaddress = ($_POST['remoteaddress']);
    $bridge = ($_POST['bridge']);
    $ratelimit = ($_POST['retelimit']);
    $onlyone = ($_POST['onlyone']);
    $bridgeportpriority = ($_POST['bridgeportpriority']);
    $bridgepathcost = ($_POST['bridgepathcost']);
    $bridgehorizon = ($_POST['bridgehorizon']);
    $incomingfilter = ($_POST['incomingfilter']);
    $outgoingfilter = ($_POST['outgoingfilter']);
    $addresslist = ($_POST['addresslist']);
    $interfacelist = ($_POST['interfacelist']);
    $dnsserver = ($_POST['dnsserver']);
    $winsserver = ($_POST['winsserver']);
    $changetcp = ($_POST['changetcp']);
    $useupnp = ($_POST['useupnp']);
    
    // Auto-Isolir settings
    $enable_isolir = isset($_POST['enable_isolir']) ? true : false;
    $isolir_profile = isset($_POST['isolir_profile']) ? trim($_POST['isolir_profile']) : '';
    $isolir_interval = isset($_POST['isolir_interval']) ? trim($_POST['isolir_interval']) : '';
    
    // Get script from form
    $profile_script = isset($_POST['profile_script']) ? trim($_POST['profile_script']) : '';
    
    // Build comment with isolir settings if enabled
    $comment = '';
    if ($enable_isolir && !empty($isolir_profile) && !empty($isolir_interval)) {
      $comment = 'ISOLIR:' . $isolir_profile . ':' . $isolir_interval;
      
      // Auto-generate script if isolir enabled and script is empty
      if (empty($profile_script)) {
        // Escape profile name for script
        $isolir_profile_escaped = str_replace('\\', '\\\\', $isolir_profile);
        $isolir_profile_escaped = str_replace('"', '\\"', $isolir_profile_escaped);
        
        // Generate script based on user example
        $profile_script = ':local pengguna $"user"; :local date [/system clock get date]; :local time [/system clock get time]; :log info "User PPPoE $pengguna login pada $time tanggal $date"; { :if ([/system scheduler find name="exp-$pengguna"]="") do={ /system scheduler add name="exp-$pengguna" interval=' . $isolir_interval . ' on-event="/ppp secret set profile=\"' . $isolir_profile_escaped . '\" [find name=\\$pengguna]; /ppp active remove [find name=\\$pengguna]; :log warning \"User \\$pengguna expired dan dipindah ke profile ' . $isolir_profile_escaped . '\"; /system scheduler remove [find name=\"exp-\\$pengguna\"]"; :log info "Scheduler auto expiry dibuat untuk user $pengguna (' . $isolir_interval . ')"; } }';
      }
    }

    if ($bridge != '' || $bridge != NULL) {
      $profileParams = array(
        /*"add-mac-cookie" => "yes",*/
        "name" => "$name",
        "local-address" => "$localaddress",
        "remote-address" => "$remoteaddress",
        "bridge" => "$bridge",
        "rate-limit" => "$ratelimit",
        "only-one" => "$onlyone",
        "incoming-filter" => "$incomingfilter",
        "outgoing-filter" => "$outgoingfilter",
        "address-list" => "$addresslist",
        "dns-server" => "$dnsserver",
        "wins-server" => "$winsserver",
        "change-tcp-mss" => "$changetcp",
        "use-upnp" => "$useupnp",
      );
      
      // Add comment if isolir enabled
      if (!empty($comment)) {
        $profileParams["comment"] = $comment;
      }
      
      // Add script (on-up) if provided
      if (!empty($profile_script)) {
        $profileParams["on-up"] = $profile_script;
      }
      
      $API->comm("/ppp/profile/add", $profileParams);
    } else {
      $profileParams = array(
        /*"add-mac-cookie" => "yes",*/
        "name" => "$name",
        "local-address" => "$localaddress",
        "remote-address" => "$remoteaddress",
        // "bridge" => "$bridge",
        "rate-limit" => "$ratelimit",
        "only-one" => "$onlyone",
        "incoming-filter" => "$incomingfilter",
        "outgoing-filter" => "$outgoingfilter",
        "address-list" => "$addresslist",
        "dns-server" => "$dnsserver",
        "wins-server" => "$winsserver",
        "change-tcp-mss" => "$changetcp",
        "use-upnp" => "$useupnp",
      );
      
      // Add comment if isolir enabled
      if (!empty($comment)) {
        $profileParams["comment"] = $comment;
      }
      
      // Add script (on-up) if provided
      if (!empty($profile_script)) {
        $profileParams["on-up"] = $profile_script;
      }
      
      $API->comm("/ppp/profile/add", $profileParams);
    }


    echo "<script>window.location='./?ppp=profiles&session=" . $session . "'</script>";
  }
}
?>
<div class="row">
  <div class="col-12">
    <div class="card box-bordered">
      <div class="card-header">
        <h3><i class="fa fa-plus"></i>Add PPP Profiles <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> Processing... </i></small></h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" method="post" action="">
          <div>
            <a class="btn bg-warning" href="./?ppp=profiles&session=<?= $session; ?>"> <i class="fa fa-close btn-mrg"></i> <?= $_close ?></a>
            <button type="submit" name="save" class="btn bg-primary btn-mrg"><i class="fa fa-save btn-mrg"></i> <?= $_save ?></button>
          </div>
          <table class="table">
            <tr>
              <td class="align-middle"><?= $_name ?></td>
              <td><input class="form-control" type="text" onchange="remSpace();" autocomplete="off" name="name" value="" required="1" autofocus></td>
            </tr>
            <tr>
              <td class="align-middle">Local Address</td>
              <td><input class="form-control" type="text" size="4" required="1" autocomplete="off" name="localaddress"></td>
            </tr>
            <tr>
              <td class="align-middle">Remote Address</td>
               <td>
                  <select class="form-control " name="remoteaddress" required="1">
                    <option value="">==Pilih==</option>
                    <?php $TotalRemote = count($getremoteaddress);
                    for ($i = 0; $i < $TotalRemote; $i++) {
                      echo "<option value='" . $getremoteaddress[$i]['name'] . "'>" . $getremoteaddress[$i]['name'] . "</option>";
                    }
                    ?>
                  </select>
                </td>
            </tr>
            <?php if (count($getbridge) != 0) { ?>
              <tr>
                <td class="align-middle">Bridge</td>
                <td>
                  <select class="form-control " name="bridge">
                    <option value="">==Pilih==</option>
                    <?php $Totalbridge = count($getbridge);
                    for ($i = 0; $i < $Totalbridge; $i++) {
                      echo "<option value='" . $getbridge[$i]['name'] . "'>" . $getbridge[$i]['name'] . "</option>";
                    }
                    ?>
                  </select>
                </td>
              </tr>
            <?php } ?>
            <tr>
              <td class="align-middle">Incoming Filter</td>
              <td>
                <select class="form-control" id="incomingfilter" name="incomingfilter">
                  <option value="">== Pilih ==</option>
                  <option value="input">input</option>
                  <option value="forward">forward</option>
                  <option value="output">output</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle">Outgoing Filter</td>
              <td>
                <select class="form-control" id="outgoingfilter" name="outgoingfilter">
                  <option value="">== Pilih ==</option>
                  <option value="input">input</option>
                  <option value="forward">forward</option>
                  <option value="output">output</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle">Address List</td>
              <td><input class="form-control" type="text" size="4" autocomplete="off" name="addresslist"></td>
            </tr>
            <tr>
              <td class="align-middle">DNS Server</td>
              <td><input class="form-control" type="text" size="4" autocomplete="off" name="dnsserver"></td>
            </tr>
            <tr>
              <td class="align-middle">WINS Server</td>
              <td><input class="form-control" type="text" size="4" autocomplete="off" name="winsserver"></td>
            </tr>
            <tr>
              <td class="align-middle">Change TCP MSS</td>
              <td>
                <select class="form-control" id="changetcp" required="1" name="changetcp">
                  <option value="default">default</option>
                  <option value="no">no</option>
                  <option value="yes">yes</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle">Use UPnP</td>
              <td>
                <select class="form-control" id="useupnp" required="1" name="useupnp">
                  <option value="default">default</option>
                  <option value="no">no</option>
                  <option value="yes">yes</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="align-middle">Rate Limit</td>
              <td><input class="form-control" type="text" size="4" autocomplete="off" required="1" name="retelimit" placeholder="example: rx/tx"></td>
            </tr>
            <tr>
              <td class="align-middle">Only One</td>
              <td>
                <select class="form-control" id="onlyone" required="1" name="onlyone">
                  <option value="default">default</option>
                  <option value="no">no</option>
                  <option value="yes">yes</option>
                </select>
              </td>
            </tr>
            <tr>
              <td colspan="2" style="background-color: #f0f0f0; padding: 10px;">
                <strong><i class="fa fa-shield"></i> Auto Isolir Settings</strong>
              </td>
            </tr>
            <tr>
              <td class="align-middle">Enable Auto Isolir</td>
              <td>
                <label>
                  <input type="checkbox" name="enable_isolir" id="enable_isolir" onchange="toggleIsolirFields()">
                  Aktifkan fitur auto isolir (otomatis ganti profile setelah interval)
                </label>
              </td>
            </tr>
            <tr id="isolir_profile_row" style="display: none;">
              <td class="align-middle">Profile ISOLIR</td>
              <td>
                <select class="form-control" name="isolir_profile" id="isolir_profile">
                  <option value="">== Pilih Profile ISOLIR ==</option>
                  <?php 
                  $TotalProfiles = count($getprofiles);
                  for ($i = 0; $i < $TotalProfiles; $i++) {
                    $profileName = $getprofiles[$i]['name'];
                    echo "<option value='" . htmlspecialchars($profileName) . "'>" . htmlspecialchars($profileName) . "</option>";
                  }
                  ?>
                </select>
                <small class="text-muted">Pilih profile yang akan digunakan saat isolir</small>
              </td>
            </tr>
            <tr id="isolir_interval_row" style="display: none;">
              <td class="align-middle">Interval Scheduler</td>
              <td>
                <input class="form-control" type="text" name="isolir_interval" id="isolir_interval" placeholder="Contoh: 1h, 30m, 2d">
                <small class="text-muted">Format: 1h (1 jam), 30m (30 menit), 2d (2 hari), atau 00:30:00 (jam:menit:detik)</small>
              </td>
            </tr>
            <tr>
              <td colspan="2" style="background-color: #f0f0f0; padding: 10px;">
                <strong><i class="fa fa-code"></i> Script (On-Up)</strong>
              </td>
            </tr>
            <tr>
              <td class="align-middle" style="vertical-align: top;">Script</td>
              <td>
                <textarea class="form-control" name="profile_script" id="profile_script" rows="8" style="font-family: monospace; font-size: 12px;" placeholder="Script akan otomatis di-generate jika Auto Isolir diaktifkan"></textarea>
                <small class="text-muted">Script ini akan dijalankan saat user PPPoE login. Variable yang tersedia: <code>$user</code> (username), <code>$interface</code> (interface name)</small>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
  function remSpace() {
    var upName = document.getElementsByName("name")[0];
    var newUpName = upName.value.replace(/\s/g, "-");
    //alert("<?php if ($currency == in_array($currency, $cekindo['indo'])) {
                echo "Nama Profile tidak boleh berisi spasi";
              } else {
                echo "Profile name can't containing white space!";
              } ?>");
    upName.value = newUpName;
    upName.focus();
  }
  
  function toggleIsolirFields() {
    var enableCheckbox = document.getElementById('enable_isolir');
    var profileRow = document.getElementById('isolir_profile_row');
    var intervalRow = document.getElementById('isolir_interval_row');
    var scriptField = document.getElementById('profile_script');
    
    if (enableCheckbox.checked) {
      profileRow.style.display = '';
      intervalRow.style.display = '';
      document.getElementById('isolir_profile').required = true;
      document.getElementById('isolir_interval').required = true;
      
      // Auto-generate script if field is empty
      if (!scriptField.value || scriptField.value.trim() === '') {
        var isolirProfile = document.getElementById('isolir_profile').value;
        var isolirInterval = document.getElementById('isolir_interval').value;
        
        if (isolirProfile && isolirInterval) {
          var generatedScript = ':local pengguna $"user"; :local date [/system clock get date]; :local time [/system clock get time]; :log info "User PPPoE $pengguna login pada $time tanggal $date"; { :if ([/system scheduler find name="exp-$pengguna"]="") do={ /system scheduler add name="exp-$pengguna" interval=' + isolirInterval + ' on-event="/ppp secret set profile=\\"' + isolirProfile + '\\" [find name=\\$pengguna]; /ppp active remove [find name=\\$pengguna]; :log warning \\"User \\$pengguna expired dan dipindah ke profile ' + isolirProfile + '\\"; /system scheduler remove [find name=\\"exp-\\$pengguna\\"]"; :log info "Scheduler auto expiry dibuat untuk user $pengguna (' + isolirInterval + ')"; } }';
          scriptField.value = generatedScript;
        }
      }
    } else {
      profileRow.style.display = 'none';
      intervalRow.style.display = 'none';
      document.getElementById('isolir_profile').required = false;
      document.getElementById('isolir_interval').required = false;
    }
  }
  
  // Auto-update script when profile or interval changes
  document.addEventListener('DOMContentLoaded', function() {
    var isolirProfile = document.getElementById('isolir_profile');
    var isolirInterval = document.getElementById('isolir_interval');
    var enableIsolir = document.getElementById('enable_isolir');
    
    if (isolirProfile && isolirInterval && enableIsolir) {
      isolirProfile.addEventListener('change', function() {
        if (enableIsolir.checked) {
          toggleIsolirFields();
        }
      });
      
      isolirInterval.addEventListener('input', function() {
        if (enableIsolir.checked) {
          toggleIsolirFields();
        }
      });
    }
  });
</script>