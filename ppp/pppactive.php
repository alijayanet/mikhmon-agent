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

	// load session MikroTik
	$session = $_GET['session'];
	$serveractive = $_GET['server'];


	$getactive = $API->comm("/ppp/active/print");
	$TotalReg = count($getactive);

	$countactive = $API->comm("/ppp/active/print", array(
		"count-only" => "",
	));
}
?>
<div class="row">
	<div id="reloadHotspotActive">
		<div class="col-12">
			<div class="card">
				<div class="card-header">
					<h3><i class="fa fa-wifi"></i> <?= $_ppp_active ?></h3>
				</div>
				<div class="card-body overflow">
					<div class="input-group">
						<div class="input-group-3 col-box-3">
							<input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?>">
						</div>
					</div>
					<br>
					<table id="dataTable" class="table table-bordered table-hover text-nowrap">
						<thead>
							<tr>
								<th style="min-width:50px;" class="text-center"><?php
																				if ($countactive < 2) {
																					echo "$countactive items";
																				} elseif ($countactive > 1) {
																					echo "$countactive items";
																				};
																				?></th>
								<th class=" align-middle"><?= $_name ?></th>
								<th class="align-middle">Service</th>
								<th class="align-middle">Caller<br>ID</th>
								<th class="align-middle">Encoding</th>
								<th class="align-middle">Address</th>
								<th class="align-middle">Uptime</th>
							</tr>
						</thead>
						<tbody>
							<?php
							for ($i = 0; $i < $TotalReg; $i++) {
								$profileactive = $getactive[$i];
								$pid = $profileactive['.id'];
								$aname = $profileactive['name'];
								$service = $profileactive['service'];
								$caller_id = $profileactive['caller-id'];
								$encoding = $profileactive['encoding'];
								$address = $profileactive['address'];
								$uptime = $profileactive['uptime'];

								$uriprocess = "'./?removepactive=" . $pid . "&session=" . $session . "'";
								echo "<tr>";
							?>
								<td style='text-align:center;'><i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('Are you sure to delete ppp active (<?= $aname; ?>)?')){loadpage('./?remove-pactive=<?= $pid; ?>&disabled-name=<?= $aname; ?>&session=<?= $session; ?>')}else{}" title='Remove <?= $aname; ?>'></i></td>
							<?php
								// echo "<td style='text-align:center;'><span class='pointer'  title='Remove " . $aname . "' onclick=loadpage(" . $uriprocess . ")><i class='fa fa-minus-square text-danger'></i></span></td>";
								echo "<td>" . $aname . "</td>";
								echo "<td>" . $service . "</td>";
								echo "<td>" . $caller_id . "</td>";
								echo "<td>" . $encoding . "</td>";
								echo "<td>" . $address . "</td>";
								echo "<td>" . $uptime . "</td>";
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