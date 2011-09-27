To see more options, check out svn_pre_commit

	#!/usr/bin/php
	<?php
	//set the file types
	$file_types = array('php', 'inc');
	new svn_hooks($argv[1], $argv[2], array(
		//at least 3 characters in your commit message
		'LogMessageLength' => array(3),
		//php lint these files
		'PHPLint' => $file_types,
		//check these files for debug output
		'DebugOutput' => $file_types
	));