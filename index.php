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
// check url

ob_start("ob_gzhandler");


$url = $_SERVER['REQUEST_URI'];

// load session MikroTik

$session = $_GET['session'];

if (!isset($_SESSION["mikhmon"])) {
  header("Location:./admin.php?id=login");
} elseif (empty($session)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} else {
  $_SESSION["$session"] = $session;
  $setsession = $_SESSION["$session"];

  $_SESSION["connect"] = "";

// time zone
  date_default_timezone_set($_SESSION['timezone']);

// lang
  include('./include/lang.php');
  include('./lang/'.$langid.'.php');

// quick bt
  include('./include/quickbt.php');

// load config
  include('./include/config.php');
  include('./include/readcfg.php');

// theme  
  include('./include/theme.php');
  include('./settings/settheme.php');
  if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
  }

// routeros api
  include_once('./lib/routeros_api.class.php');
  include_once('./lib/formatbytesbites.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, decrypt($passwdhost));

  $getidentity = $API->comm("/system/identity/print");
  $identity = $getidentity[0]['name'];
  

// get variable
  $hotspot = $_GET['hotspot'];
  $hotspotuser = $_GET['hotspot-user'];
  $userbyname = $_GET['hotspot-user'];
  $removeuseractive = $_GET['remove-user-active'];
  $removehost = $_GET['remove-host'];
  $removecookie = $_GET['remove-cookie'];
  $removeipbinding = $_GET['remove-ip-binding'];
  $removehotspotuser = $_GET['remove-hotspot-user'];
  $removehotspotusers = $_GET['remove-hotspot-users'];
  $removeuserprofile = $_GET['remove-user-profile'];
  $resethotspotuser = $_GET['reset-hotspot-user'];
  $removehotspotuserbycomment = $_GET['remove-hotspot-user-by-comment'];
  $removeexpiredhotspotuser = $_GET['remove-hotspot-user-expired'];
  $enablehotspotuser = $_GET['enable-hotspot-user'];
  $disablehotspotuser = $_GET['disable-hotspot-user'];
  $enableipbinding = $_GET['enable-ip-binding'];
  $disableipbinding = $_GET['disable-ip-binding'];
  $userprofile = $_GET['user-profile'];
  $userprofilebyname = $_GET['user-profile'];
  $sys = $_GET['system'];
  $enablesch = $_GET['enable-scheduler'];
  $disablesch = $_GET['disable-scheduler'];
  $removesch = $_GET['remove-scheduler'];
  $macbinding = $_GET['mac'];
  $ipbinding = $_GET['addr'];
  $ppp = $_GET['ppp'];
  $secretbyname = $_GET['secret'];
  $enablesecr = $_GET['enable-pppsecret'];
  $disablesecr = $_GET['disable-pppsecret'];
  $removesecr = $_GET['remove-pppsecret'];
  $removepprofile = $_GET['remove-pprofile'];
  $removepactive = $_GET['remove-pactive'];
  $srv = $_GET['srv'];
  $prof = $_GET['profile'];
  $comm = $_GET['comment'];
  $serveractive = $_GET['server'];
  $report = $_GET['report'];
  $removereport = $_GET['remove-report'];
  $minterface = $_GET['interface'];


  $pagehotspot = array('users','hosts','ipbinding','cookies','log','dhcp-leases');
  $pageppp = array('secrets','profiles','active',);
  $pagereport = array('userlog','selling');

  include_once('./include/headhtml.php');

  include_once('./include/menu.php');

  $disable_sci = '<script>
  document.getElementById("comment").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,.~".indexOf(chr) >= 0)
        return false;
};
</script>';


// logout
  if ($hotspot == "logout") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";

    session_destroy();
    echo "<script>sessionStorage.clear();</script>";
    echo "<script>window.location='./admin.php?id=login'</script>";
  }
// redirect to home
  elseif (substr(explode("=", $url)[0],-9) == "/?session") {

    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";
  }

// redirect to home
  elseif ($hotspot == "dashboard") {
    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";

  }

// hotspot log
  elseif ($hotspot == "log") {
    include_once('./hotspot/log.php');
  }

// hotspot log
  elseif ($report == "userlog") {
    include_once('./report/userlog.php');
  }

// about
  elseif ($hotspot == "about") {
    include_once('./include/about.php');
  }

// agent setup - removed duplicate, using settings/agent_setup.php instead

// bad request
  elseif (substr($url, -1) == "=") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Bad request! redirect to Home......</b>";

    echo "<script>window.location='./'</script>";
  }

// hotspot add users
  elseif ($hotspot == "add-user") {
    $_SESSION['hua'] = "";
    include_once('./hotspot/adduser.php');
  }

// hotspot users
  elseif ($hotspot == "users" && $prof == "all") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by profile
  elseif ($hotspot == "users" && $prof != "") {
    $_SESSION['ubp'] = $prof;
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by comment
  elseif ($hotspot == "users" && $comm != "") {
    $_SESSION['ubc'] = $comm;
    $_SESSION['hua'] = "";
    $_SESSION['ubp'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot by profile
  elseif ($hotspot == "users-by-profile") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "active";
    include_once('./hotspot/userbyprofile.php');
  }
// export hotspot users
  elseif ($hotspot == "export-users") {
    include_once('./hotspot/exportusers.php');
  }

// quick print
  elseif ($hotspot == "quick-print") {
    include_once('./hotspot/quickprint.php');
  }

// quick print
elseif ($hotspot == "list-quick-print") {
  include_once('./hotspot/listquickprint.php');
}  

// add hotspot user
  elseif ($hotspotuser == "add") {
    include_once('./hotspot/adduser.php');
    echo $disable_sci;
  }

// add hotspot user
  elseif ($hotspotuser == "generate") {
    include_once('./hotspot/generateuser.php');
    echo $disable_sci;
  }

// hotspot users filter by name
  elseif (substr($hotspotuser, 0, 1) == "*") {
    $_SESSION['ubn'] = $hotspotuser;
    $_SESSION['hua'] = "";
    include_once('./hotspot/userbyname.php');
  } elseif ($hotspotuser != "") {
    $_SESSION['ubn'] = $hotspotuser;
    include_once('./hotspot/userbyname.php');
  }

// remove hotspot user
  elseif ($removehotspotuser != "" || $removehotspotusers != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuser.php');
  }

// remove hotspot user by comment
  elseif ($removehotspotuserbycomment != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuserbycomment.php');
  }

// remove expired hotspot user
elseif ($removeexpiredhotspotuser != "") {
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

  include_once('./process/removeexpiredhotspotuser.php');
}  

// reset hotspot user
  elseif ($resethotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/resethotspotuser.php');
  }

// enable hotspot user
  elseif ($enablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/enablehotspotuser.php');
  }

// disable hotspot user
  elseif ($disablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/disablehotspotuser.php');
  }

// user profile
  elseif ($hotspot == "user-profiles") {
    include_once('./hotspot/userprofile.php');
  }

// add  user profile
  elseif ($userprofile == "add") {
    include_once('./hotspot/adduserprofile.php');
  }

// User profile by name
  elseif (substr($userprofile, 0, 1) == "*") {
    include_once('./hotspot/userprofilebyname.php');
  } elseif ($userprofile != "") {
    include_once('./hotspot/userprofilebyname.php');
  }


// remove user profile
  elseif ($removeuserprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuserprofile.php');
  }

// hotspot active
  elseif ($hotspot == "active") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "hotspotactive";
    $_SESSION['ubc'] = "";
    include_once('./hotspot/hotspotactive.php');
  }

// dhcp leases
  elseif ($hotspot == "dhcp-leases") {
    include_once('./dhcp/dhcpleases.php');
  }

// traffic monitor
  elseif ($minterface == "traffic-monitor") {
  include_once('./traffic/trafficmonitor.php');
}

// hotspot hosts
  elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta") {
    include_once('./hotspot/hosts.php');
  }

// hotspot bindings
  elseif ($hotspot == "binding") {
    include_once('./hotspot/binding.php');
  }

// template editor
  elseif ($hotspot == "template-editor") {
    include_once('./settings/vouchereditor.php');
  }

// upload logo
  elseif ($hotspot == "uplogo") {
    include_once('./settings/uplogo.php');
  }

// whatsapp settings
  elseif ($hotspot == "whatsapp-settings") {
    include_once('./settings/whatsapp_settings.php');
  }

// whatsapp gateway admin
  elseif ($hotspot == "whatsapp-gateway") {
    include_once('./settings/whatsapp_gateway_admin.php');
  }

// agent management
  elseif ($hotspot == "agent-setup") {
    try {
      include_once('./settings/agent_setup_simple.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Agent Setup</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "<p>Trying to load simple version...</p>";
      echo "</div>";
      // Fallback to simple content
      echo "<div class='row'><div class='col-12'><div class='card'>";
      echo "<div class='card-header'><h3><i class='fa fa-users'></i> Agent System Setup</h3></div>";
      echo "<div class='card-body'>";
      echo "<div class='alert alert-warning'><h4>Setup Required</h4>";
      echo "<p>Agent system needs to be installed. Use the bulletproof installer:</p>";
      echo "<a href='install_database_bulletproof.php?key=mikhmon-install-2024' class='btn btn-success' target='_blank'>";
      echo "<i class='fa fa-download'></i> Install Database</a></div>";
      echo "</div></div></div></div>";
    }
  }
  elseif ($hotspot == "agent-list") {
    try {
      include_once('./agent-admin/agent_list.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Agent List</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "<p><a href='./test_agent_db.php' target='_blank'>Test Database Connection</a></p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "agent-add") {
    try {
      include_once('./agent-admin/agent_add.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Add Agent</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "agent-edit") {
    try {
      include_once('./agent-admin/agent_edit.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Edit Agent</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "agent-prices") {
    try {
      include_once('./agent-admin/agent_prices.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Agent Prices</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "agent-topup") {
    try {
      include_once('./agent-admin/agent_topup.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Agent Topup</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "agent-transactions") {
    try {
      include_once('./agent-admin/agent_transactions.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Agent Transactions</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "voucher-settings") {
    try {
      include_once('./agent-admin/voucher_settings.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Voucher Settings</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "billing-dashboard") {
    $path = './agent-admin/billing_dashboard.php';
    if (file_exists($path)) {
      try {
        include_once($path);
      } catch (Exception $e) {
        echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
        echo "<h3>Error Loading Billing Dashboard</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
      }
    } else {
      echo "<div style='padding:20px;background:#fef3c7;color:#92400e;border-radius:10px;margin:20px;'>";
      echo "<h3>Billing Dashboard belum tersedia</h3>";
      echo "<p>File <code>agent-admin/billing_dashboard.php</code> tidak ditemukan.</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "billing-profiles") {
    $path = './agent-admin/billing_profiles.php';
    if (file_exists($path)) {
      try {
        include_once($path);
      } catch (Exception $e) {
        echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
        echo "<h3>Error Loading Billing Profiles</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
      }
    } else {
      echo "<div style='padding:20px;background:#fef3c7;color:#92400e;border-radius:10px;margin:20px;'>";
      echo "<h3>Billing Profiles belum tersedia</h3>";
      echo "<p>File <code>agent-admin/billing_profiles.php</code> tidak ditemukan.</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "billing-customers") {
    $path = './agent-admin/billing_customers.php';
    if (file_exists($path)) {
      try {
        include_once($path);
      } catch (Exception $e) {
        echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
        echo "<h3>Error Loading Billing Customers</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
      }
    } else {
      echo "<div style='padding:20px;background:#fef3c7;color:#92400e;border-radius:10px;margin:20px;'>";
      echo "<h3>Billing Customers belum tersedia</h3>";
      echo "<p>File <code>agent-admin/billing_customers.php</code> tidak ditemukan.</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "billing-invoices") {
    $path = './agent-admin/billing_invoices.php';
    if (file_exists($path)) {
      try {
        include_once($path);
      } catch (Exception $e) {
        echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
        echo "<h3>Error Loading Billing Invoices</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
      }
    } else {
      echo "<div style='padding:20px;background:#fef3c7;color:#92400e;border-radius:10px;margin:20px;'>";
      echo "<h3>Billing Invoices belum tersedia</h3>";
      echo "<p>File <code>agent-admin/billing_invoices.php</code> tidak ditemukan.</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "billing-settings") {
    $path = './agent-admin/billing_settings.php';
    if (file_exists($path)) {
      try {
        include_once($path);
      } catch (Exception $e) {
        echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
        echo "<h3>Error Loading Billing Settings</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
      }
    } else {
      echo "<div style='padding:20px;background:#fef3c7;color:#92400e;border-radius:10px;margin:20px;'>";
      echo "<h3>Billing Settings belum tersedia</h3>";
      echo "<p>File <code>agent-admin/billing_settings.php</code> tidak ditemukan.</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "billing-portal") {
    $path = './agent-admin/billing_portal.php';
    if (file_exists($path)) {
      try {
        include_once($path);
      } catch (Exception $e) {
        echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
        echo "<h3>Error Loading Billing Portal</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
      }
    } else {
      echo "<div style='padding:20px;background:#fef3c7;color:#92400e;border-radius:10px;margin:20px;'>";
      echo "<h3>Billing Portal belum tersedia</h3>";
      echo "<p>File <code>agent-admin/billing_portal.php</code> tidak ditemukan.</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "whatsapp-agent-settings") {
    try {
      include_once('./agent-admin/whatsapp_agent_settings.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading WhatsApp Agent Settings</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  
  // Public Sales - Pricing Management
  elseif ($hotspot == "pricing") {
    try {
      include_once('./agent/pricing.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Pricing</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  
  // Public Sales - Payment Gateway Config
  elseif ($hotspot == "payment-gateway-config") {
    try {
      include_once('./agent-admin/payment_gateway_config.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Payment Gateway Config</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "digiflazz-transactions") {
    try {
      include_once('./agent-admin/digiflazz_transactions.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Digiflazz Transactions</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "digiflazz-settings") {
    try {
      include_once('./agent-admin/digiflazz_settings.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Digiflazz Settings</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  elseif ($hotspot == "payment-methods") {
    try {
      include_once('./agent-admin/payment_methods.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Payment Methods</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  
  // Public Sales - Transactions List
  elseif ($hotspot == "public-sales") {
    try {
      include_once('./agent-admin/public_sales_list.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading Public Sales</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
  }
  
  // GenieACS - ONU Management
  elseif ($hotspot == "genieacs") {
    // Use NEW file to bypass cache
    echo "<!-- GenieACS routing START (using main.php) -->";
    flush();
    
    try {
      include_once('./genieacs/main.php');
    } catch (Exception $e) {
      echo "<div style='padding:20px;background:#fee;color:#c33;border-radius:10px;margin:20px;'>";
      echo "<h3>Error Loading GenieACS</h3>";
      echo "<p>" . $e->getMessage() . "</p>";
      echo "</div>";
    }
    
    echo "<!-- GenieACS routing END -->";
    flush();
  }

// hotspot Cookies
  elseif ($hotspot == "cookies") {
    include_once('./hotspot/cookies.php');
  }

// remove hotspot Cookies
  elseif ($removecookie != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removecookie.php');
  }

// hotspot Ip Bindings
  elseif ($hotspot == "ipbinding") {
    include_once('./hotspot/ipbinding.php');
  }

// remove enable disable ipbinding
  elseif ($removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pipbinding.php');
  }


// remove user active
  elseif ($removeuseractive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuseractive.php');
  }

// remove host
  elseif ($removehost != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehost.php');
  }


// makebinding
  elseif ($macbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/makebinding.php');
  }

// selling
  elseif ($report == "selling") {
    include_once('./report/selling.php');
  }

// selling
elseif ($report == "resume-report") {
  include_once('./report/resumereport.php');
}

// selling
elseif ($report == "export") {
  include_once('./report/export.php');
}

// selling
  elseif ($removereport != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removereport.php');
  }

// ppp secret
  elseif ($ppp == "secrets") {
    include_once('./ppp/pppsecrets.php');
  }

// ppp addsecret
  elseif ($ppp == "addsecret") {
    include_once('./ppp/addsecret.php');
  }

// ppp secretbyname
  elseif ($secretbyname != "") {
    include_once('./ppp/secretbyname.php');
  }

// remove enable disable secret
  elseif ($removesecr != "" || $enablesecr != "" || $disablesecr != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/psecret.php');
  }


// ppp profile
  elseif ($ppp == "profiles") {
    include_once('./ppp/pppprofile.php');
  }

// add ppp profile
  elseif ($ppp == "add-profile") {
    include_once('./ppp/addpppprofile.php');
  }

// edit ppp profile
elseif (substr($ppp, 0, 12) == "edit-profile") {
  include_once('./ppp/profilebyname.php');
}
// remove enable disable profile
  elseif ($removepprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepprofile.php');
  }

// ppp active connection
  elseif ($ppp == "active") {
    include_once('./ppp/pppactive.php');
  }

// remove ppp active connection
  elseif ($removepactive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepactive.php');
  }

// sys scheduler
  elseif ($sys == "scheduler") {
    include_once('./system/scheduler.php');
  }
// remove enable disable scheduler
  elseif ($removesch != "" || $enablesch != "" || $disablesch != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pscheduler.php');
  }

  ?>

</div>
</div>
</div>
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= $theme; ?>.js"></script>
<script src="./js/mikhmon-ui.<?= $theme; ?>.min.js"></script>
<script src="./js/mikhmon.js?t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>

<?php
if ($hotspot == "dashboard" || substr(end(explode("/", $url)), 0, 8) == "?session") {
  echo '<script>
    $("#r_3").load("./dashboard/aload.php?session=' . $session . '&load=logs #r_3");  
    var interval1 = "' . ($areload * 1000) . '";
    var dashboard = setInterval(function() {
      
    $("#r_1").load("./dashboard/aload.php?session=' . $session . '&load=sysresource #r_1"); 
    $("#r_2").load("./dashboard/aload.php?session=' . $session . '&load=hotspot #r_2"); 
    $("#r_3").load("./dashboard/aload.php?session=' . $session . '&load=logs #r_3"); 
    
  }, interval1);

';
if ($livereport == "enable" || $livereport == "") {
  if($_SESSION[$session.'sdate'] != $_SESSION[$session.'idhr']){
    $_SESSION[$session.'totalHr'] = "0";
    echo '$("#r_4").load("./report/livereport.php?session=' . $session . ' #r_4");';
    }else if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){  
    }else{
      echo '$("#r_4").load("./report/livereport.php?session=' . $session . ' #r_4");';
    }
  echo  '
    var interval2 = "65432";
    var livereport = setInterval(function() {
    $("#r_4").load("./report/livereport.php?session=' . $session . ' #r_4"); 
  }, interval2);
 ';}
  echo ' 
  function cancelPage(){
    window.stop();
    clearInterval(dashboard);';
    if ($livereport == "enable" || $livereport == "") {
    echo '
    clearInterval(livereport);';
    }
  echo '
    }
</script>';

} elseif ($hotspot == "active" && $serveractive != "") {
  echo '<script>
  $(document).ready(function(){
    var interval = "' . ($areload * 1000) . '";
    setInterval(function() {
    $("#reloadHotspotActive").load("./hotspot/hotspotactive.php?server=' . $serveractive . '&session=' . $session . '"); }, interval);})
</script>
';
} elseif ($hotspot == "active" && $serveractive == "") {
  echo '<script>
  $(document).ready(function(){
    var interval = "' . ($areload * 1000) . '";
    setInterval(function() {
    $("#reloadHotspotActive").load("./hotspot/hotspotactive.php?session=' . $session . '"); }, interval);})
</script>
';
} elseif ($userprofile == "add" || substr($userprofile, 0, 1) == "*" || $userprofile != "") {
  echo "<script>
  //enable disable input on ready
$(document).ready(function(){
    var exp = document.getElementById('expmode').value;
    var val = document.getElementById('validity').style;
    var vali = document.getElementById('validi');
    if (exp === 'rem' || exp === 'remc') {
      val.display= 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else if (exp === 'ntf' || exp === 'ntfc') {
      val.display = 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else {
      val.display = 'none';
      vali.type = 'hidden';
    }
});
</script>";

} elseif (in_array($hotspot, $pagehotspot) || in_array($ppp, $pageppp) || in_array($report, $pagereport) || $sys == "scheduler") {
echo '
<script>
$(document).ready(function(){
  makeAllSortable();
  $("#filterTable").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dataTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});

</script>
';
}
}
?>
</body>
</html>

