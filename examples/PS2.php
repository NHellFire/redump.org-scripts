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

// Database table to work on
// Must be created with the schema in examples/database.sql
$Table = "PS2";
$dat = "dats/Sony - PlayStation 2 (20160406 23-41-52).dat"; // redump.org dat file

$BaseDir = "/media/Games/PS2"; // Directory to scan (recursive)
$OutputDirectory = "output"; // Where to save text results to
$OutputName = ""; // Prefix for output filenames. Defaults to $Table

$FileTypes = "/\.(iso|bin|cue)$/i"; // File types to scan (regex)

$CleanupDeletedFiles = true; // Remove nonexistent files from the database
$RenameToDat = true; // Rename files to match the .dat

require(__DIR__."/includes/cleanup.php"); // Remove nonexistent files

require(__DIR__."/includes/hash-all.php"); // Check md5 database is up-to-date

require(__DIR__."/includes/check-dat.php"); // Check files against .dat
