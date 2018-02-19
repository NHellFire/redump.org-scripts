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

require_once(__DIR__."/config.php");

$Directory = new RecursiveDirectoryIterator($BaseDir);
$Iterator = new RecursiveIteratorIterator($Directory);
$Regex = new RegexIterator($Iterator, $FileTypes);

$mysql = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if ($mysql->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysql->connect_errno . ") " . $mysql->connect_error."\n";
	die;
}

$insert = $mysql->prepare("INSERT INTO $Table (path, size, mtime, md5) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE size=?, mtime=?, md5=?");
if (!$insert->bind_param("siisiis", $FilePath, $FileSize, $FileMTime, $FileMD5, $FileSize, $FileMTime, $FileMD5)) {
	echo "Binding parameters failed: (" . $insert->errno . ") " . $insert->error."\n";
	die;
}

$select = $mysql->prepare("SELECT id FROM $Table WHERE path=? AND size=? AND mtime=?");
if (!$select->bind_param("sii", $FilePath, $FileSize, $FileMTime)) {
	echo "Binding parameters failed: (" . $select->errno . ") " . $select->error."\n";
	die;
}


foreach ($Regex as $File) {
	$FilePath = $File->__toString();
	$FileSize = $File->getSize();
	$FileMTime = $File->getMTime();

	echo "Processing ".$File->getBasename().": ";

	if (preg_match("/#Homebrew/", $File->getPath())) {
		echo "skipped (homebrew)\n";
	}

	if (!$select->execute()) {
		echo "Select failed: " . $select->errno . ") " . $select->error."\n";
		die;
	}
	$select->store_result();
	$FileNeedsUpdating = $select->num_rows == 0;
	$select->free_result();
	if (!$FileNeedsUpdating) {
		echo "up-to-date.\n";
		continue;
	}
	$FileMD5 = md5_file($FilePath);
	if (!$insert->execute()) {
		echo "Insert failed: " . $insert->errno . ") " . $insert->error."\n";
		die;
	}
	echo "done\n";
	print_r($insert->get_warnings());
}

$insert->close();
$select->close();

$mysql->close();
