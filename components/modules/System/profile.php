<?php
global $Config, $Index;
$rc			= &$Config->routing['current'];
$subparts	= _json_decode(_file_get_contents(MFOLDER.DS.'index.json'))[$rc[0]];
if (!in_array($rc[1], $subparts)) {
	$rc[2] = $rc[1];
	$rc[1] = $subparts[0];
}