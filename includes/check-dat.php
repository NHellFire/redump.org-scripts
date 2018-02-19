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

if ($OutputDirectory !== NULL) {
	if (!is_dir($OutputDirectory) && !mkdir($OutputDirectory)) {
		echo "Failed to create output directory.\n";
		die;
	}
} else {
	$OutputDirectory = ".";
}

$mysql = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if ($mysql->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysql->connect_errno . ") " . $mysql->connect_error."\n";
	die;
}


// Parse XML into an array
// Each element contains the keys: name, size, sha1, md5, crc
$xml = simplexml_load_file($dat);
$roms = array();
foreach ($xml->game as $game) {
	foreach ($game->rom as $rom) {
		$attributes = $rom->attributes();
		$rom = array();
		foreach ($attributes as $key => $val) {
			$rom[$key] = (string) $val;
			unset($key, $val);
		}
		$roms[] = $rom;
		unset($rom, $attributes);
	}
	unset($game);
}


$have = array();
$missing = array();
$unknown = array();

// First figure out have and unknown
$res = $mysql->query("SELECT * FROM $Table");
while ($row = $res->fetch_assoc()) {
	$key = array_search_md5($row["md5"], $roms);
	if ($key) {
		$rom = $roms[$key];
		if ($RenameToDat) {
			$NewPath = dirname($row["path"]).DIRECTORY_SEPARATOR.basename($rom["name"]);

			if (!rename($row["path"], $NewPath)) {
				echo "Renaming rom failed.\n";
				die;
			}
			$update = $mysql->prepare("UPDATE $Table SET path=? WHERE id=?");
			if (!$update->bind_param("si", $NewPath, $row["id"])) {
				echo "Binding parameters failed: (" . $update->errno . ") " . $update->error."\n";
				die;
			}

			if (!$update->execute()) {
				echo "Update failed: " . $update->errno . ") " . $update->error."\n";
				die;
			}
		} else {
			$rom["name"] = basename($row["path"]);
		}
		$have[] = $rom;
	} else {
		$unknown[] = array("name" => basename($row["path"]), "size" => $row["size"], "md5" => $row["md5"]);
	}
}
unset($res, $row, $key);

// Now do the missing roms
foreach ($roms as $rom) {
	if (!array_search_md5($rom["md5"], $have)) {
		$missing[] = $rom;
	}
	unset($rom);
}


// Print summary
$romCount = count($roms);
$haveCount = count($have);
$missingCount = count($missing);
$unknownCount = count($unknown);

printf("\nHave: %s/%s (%.2f%%)\n", number_format($haveCount), number_format($romCount), ($haveCount/$romCount) * 100);
printf("Unknown: %s\n", number_format($unknownCount));

$OutputMissing = "$OutputDirectory/{$Table}_Missing.txt";
$OutputUnknown = "$OutputDirectory/{$Table}_Unknown.txt";
$OutputHave = "$OutputDirectory/{$Table}_Have.txt";

if ($missing) {
	file_put_contents($OutputMissing, MakeSummary($missing));
} else {
	@unlink($OutputMissing);
}

if ($unknown) {
	file_put_contents($OutputUnknown, MakeSummary($unknown));
} else {
	@unlink($OutputUnknown);
}

if ($have) {
	file_put_contents($OutputHave, MakeSummary($have));
} else {
	@unlink($OutputHave);
}


// Functions
function array_search_md5 ($needle, $haystack) {
	foreach ($haystack as $key => $rom) {
		if (!strcasecmp($needle, $rom["md5"])) {
			return $key;
		}
	}
	return false;
}


function MakeSummary ($roms) {
	$ret = array();

	// This will tell us the longest filename:  egrep -o 'rom name="[^"]*"' *.dat | awk -F\" '{ length($2) > MAX && MAX=length($2) } END { print MAX }'
	// Currently 120

	$LongestName = 0;
	foreach ($roms as $rom) {
		$LongestName = max(strlen($rom["name"]), $LongestName);
	}

	// Columns
	//	Heading:width:key
	// Padding will be added to each field length
	$padding = 4;
	$columns = array("Name:$LongestName:name", "Size:9:size", "MD5:32:md5");

	// First generate the header
	$header = array();
	foreach ($columns as $index => $column) {
		list($Title, $Length, $Key) = explode(":", $column, 3);
		$header[] = str_pad($Title, $Length + $padding, " ", $index ? STR_PAD_BOTH : STR_PAD_RIGHT);
		unset($Title, $Length, $Key);
	}
	$header = implode("|", $header);
	$headerLength = strlen($header);
	$ret[] = str_repeat("-", $headerLength);
	$ret[] = $header;
	$ret[] = str_repeat("-", $headerLength);
	unset($header, $headerLength);


	$TotalSize = 0;
	$FileCount = count($arr);

	usort($roms, "SortRoms");
	foreach ($roms as $rom) {
		$TotalSize += $rom["size"];
		$rom["size"] = FormatBytes($rom["size"]);

		$line = array();
		foreach ($columns as $index => $column) {
			list($Title, $Length, $Key) = explode(":", $column, 3);
			$line[] = str_pad($rom[$Key], $Length + $padding, " ", $index ? STR_PAD_BOTH : STR_PAD_RIGHT);
		}
		$ret[] = implode("|", $line);
	}

	return implode("\r\n", $ret)."\r\n";
}


function FormatBytes ($bytes) {
	$suffixes = array("B", "kB", "MB", "GB", "TB");
	$count = count($suffixes);

	for ($pow = 1; $pow <= $count; $pow++) {
		if ($bytes < pow(1000, $pow) || $pow == $count) {
			return number_format($bytes/pow(1024, $pow-1), 2)." ".$suffixes[$pow-1];
		}
	}
}

function SortRoms ($a, $b) {
	return strcasecmp($a["name"], $b["name"]);
}
