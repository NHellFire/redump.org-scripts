<?php
/*
    PHP redump.org checker
    Copyright (C) 2016 Nathan Rennie-Waldock

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!$CleanupDeletedFiles) {
	return;
}

require_once(__DIR__."/config.php");

$mysql = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if ($mysql->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysql->connect_errno . ") " . $mysql->connect_error."\n";
	die;
}

$res = $mysql->query("SELECT * FROM $Table");
while ($row = $res->fetch_assoc()) {
	if (!file_exists($row["path"])) {
		$delete = $mysql->prepare("DELETE FROM $Table WHERE id=?");
		$delete->bind_param("i", $row["id"]);
		$delete->execute();
		$delete->close();
	}
}

$mysql->close();
