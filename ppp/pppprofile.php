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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
	echo '
<html>
<head><title>403 Forbidden</title></head>
<body bgcolor="white">
<center><h1>403 Forbidden</h1></center>
<hr><center>nginx/1.14.0</center>
</body>
</html>
';
} else {

	// get ppp profile
	$getprofile = $API->comm("/ppp/profile/print");
	$TotalReg = count($getprofile);
	// count ppp profile
	$countprofile = $API->comm("/ppp/profile/print", array(
		"count-only" => "",
	));
	// get name
	$getname = $API->comm("/ppp/profile/print");
	$Totalname = count($getname);
}
?>
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header align-middle">
				<h3><i class=" fa fa-pie-chart"></i> PPP Profile
					&nbsp; | &nbsp; <a href="./?ppp=add-profile&session=<?= $session; ?>" title="Add User"><i class="fa fa-user-plus"></i> Add</a>
				</h3>
			</div>
			<!-- /.card-header -->
			<div class="card-body">
				<!-- /.card-header -->
				<div class="card-body">
					<div class="input-group">
						<div class="input-group-3 col-box-3">
							<input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?>">
						</div>
					</div>
					<br>
					<div class="overflow box-bordered" style="max-height: 75vh">
						<table id="dataTable" class="table table-bordered table-hover text-nowrap">
							<thead>
								<tr>
									<th style="min-width:50px;" class="text-center">
										<?php
										if ($countprofile < 2) {
											echo "$countprofile item  ";
										} elseif ($countprofile > 1) {
											echo "$countprofile items   ";
										}
										?></th>
									<th class="align-middle"><?= $_name ?></th>
									<th class="align-middle">Local<br>Address</th>
									<th class="align-middle">Remote<br>Address</th>
									<th class="align-middle">Bridge</th>
									<th class="align-middle">Rate<br>Limit</th>
									<th class="align-middle">Only<br>One</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<?php

									for ($i = 0; $i < $TotalReg; $i++) {

										$profiledetalis = $getprofile[$i];
										$pid = $profiledetalis['.id'];
										$pname = $profiledetalis['name'];
										$local_address = $profiledetalis['local-address'];
										$remote_address = $profiledetalis['remote-address'];
										$bridge = $profiledetalis['bridge'];
										$rate_limit = $profiledetalis['rate-limit'];
										$only_one = $profiledetalis['only-one'];
									?>
										<td style='text-align:center;'><i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('Are you sure to delete profile (<?= $pname; ?>)?')){loadpage('./?remove-pprofile=<?= $pid; ?>&pname=<?= $pname ?>&session=<?= $session; ?>')}else{}" title='Remove <?= $pname; ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
										<?php
										echo "</td>";
										echo "<td><a title='Open User Profile " . $pname . "' href='./?ppp=edit-profile=" . $pid . "&session=" . $session . "'><i class='fa fa-edit'></i> $pname</a></td>";
										//$profiledetalis = $ARRAY[$i];echo "<td>" . $profiledetalis['name'];echo "</td>";
										echo "<td>" . $local_address;
										echo "</td>";
										echo "<td>" . $remote_address;
										echo "</td>";
										echo "<td>" . $bridge;
										echo "</td>";
										echo "<td>" . $rate_limit;
										echo "</td>";
										echo "<td>" . $only_one;
										echo "</td>";
										echo "</tr>";
									}
										?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>