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

// Handle remove PPP secret
if ($removesecr != "") {
    $API->comm("/ppp/secret/remove", array(
        ".id" => "$removesecr",
    ));
    // Redirect back to the same profile view
    if (isset($_SESSION['ppp_profile']) && $_SESSION['ppp_profile'] != "") {
        echo "<script>window.location='./?ppp=secrets&profile=" . $_SESSION['ppp_profile'] . "&session=" . $session . "'</script>";
    } else {
        echo "<script>window.location='./?ppp=secrets&profile=all&session=" . $session . "'</script>";
    }
}

// Handle enable PPP secret
elseif ($enablesecr != "") {
    $API->comm("/ppp/secret/set", array(
        ".id" => "$enablesecr",
        "disabled" => "no",
    ));
    // Redirect back to the same profile view
    if (isset($_SESSION['ppp_profile']) && $_SESSION['ppp_profile'] != "") {
        echo "<script>window.location='./?ppp=secrets&profile=" . $_SESSION['ppp_profile'] . "&session=" . $session . "'</script>";
    } else {
        echo "<script>window.location='./?ppp=secrets&profile=all&session=" . $session . "'</script>";
    }
}

// Handle disable PPP secret
elseif ($disablesecr != "") {
    $API->comm("/ppp/secret/set", array(
        ".id" => "$disablesecr",
        "disabled" => "yes",
    ));
    // Redirect back to the same profile view
    if (isset($_SESSION['ppp_profile']) && $_SESSION['ppp_profile'] != "") {
        echo "<script>window.location='./?ppp=secrets&profile=" . $_SESSION['ppp_profile'] . "&session=" . $session . "'</script>";
    } else {
        echo "<script>window.location='./?ppp=secrets&profile=all&session=" . $session . "'</script>";
    }
}

?>
