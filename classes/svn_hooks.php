<?php

/**
 * Subversion pre-commit hook script validating commit log message
 */

/**
 * Class for performing various tests in subversion pre-commit hooks
 *
 */
class svn_hooks {
	/**
	 * The php binary location
	 * @var type 
	 */
	public static $PHP = "/usr/bin/php";
	/**
	 * Debug functions to catch
	 * @var array
	 */
	public static $debug_functions = array(
		'var_dump',
		'var_export',
		'print_r',
		'debug_backtrace',
	);
	public static $debug_patterns = array(
		'xdebug_.*?',
		
	);
	/**
	 * Holds the error messages
	 * @var string
	 */
	protected $msg = '';
	/**
	 * Subversion repository path
	 * @var string
	 */
	protected $_repository;
	/**
	 * Transaction number
	 * @var int
	 */
	protected $_transaction;
	/**
	 * Commit message string
	 * @var string
	 */
	protected $_logMessage;
	/**
	 * Commit files list
	 * @var array
	 */
	protected $_commitList;
	/**
	 * Changed files list
	 * @var array
	 */
	protected $_changedFiles;
	/**
	 * Class constructor
	 *
	 * @param string $repository
	 * @param string $transaction
	 * @param array $tests array of test names to run
	 */
	public function __construct($repository, $transaction, array $tests) {
		$this->_repository = $repository;
		$this->_transaction = $transaction;
		exit($this->runTests($tests));
	}
	/**
	 * Run subversion pre-commit tests
	 *
	 * @param array $tests array of test names to run
	 * @return int result code, 0 == all test passed, other value 
	  represents
	 *  number of failed tests
	 */
	protected function runTests(array $tests) {
		$result = 0;
		$messages = '';
		foreach ($tests as $k => $v) {
			if (is_numeric($k)) {
				$test = $v;
				$params = array();
			} else {
				$test = $k;
				$params = $v;
				if (!is_array($params)) {
					throw new Exception('Test arguments should be in an array.');
				}
			}
			$method = "_test$test";
			$this->msg = '';
			$result +=!call_user_func_array(array($this, $method), $params);
			if ($this->msg) {
				$messages .= " *) $this->msg\n";
			}
		}
		if ($messages) {
			$messages = rtrim($messages);
			fwrite(STDERR, "----------------\n$messages\n----------------");
		}
		return $result;
	}
	/**
	 * Get commit log message
	 *
	 * @return string
	 */
	protected function getLogMessage() {
		if (null !== $this->_logMessage) {
			return $this->_logMessage;
		}
		$output = null;
		$cmd = "svnlook log -t '{$this->_transaction}' '{$this->_repository}'";
		exec($cmd, $output);
		$this->_logMessage = implode($output);
		return $this->_logMessage;
	}
	/**
	 * Get content of file from current transaction
	 *
	 * @param string $file
	 * @return string
	 * @throws Exception
	 */
	protected function getFileContent($file) {
		static $cached = array();
		if (!isset($cached[$file])) {
			$content = '';
			$cmd = "svnlook cat -t '{$this->_transaction}' '{$this->_repository}' '$file' 2>&1";
			// can't use exec() here because it will strip trailing spaces
			$handle = popen($cmd, 'r');
			while (!feof($handle)) {
				$content .= fread($handle, 1024);
			}
			$return = pclose($handle);
			if (0 != $return) {
				throw new Exception($content, $return);
			}
			$cached[$file] = $content;
		}
		return $cached[$file];
	}
	/**
	 * Get svn properties for file
	 *
	 * @param string $file
	 * @return array
	 */
	protected function getFileProps($file) {
		static $cached = array();
		if (!isset($cached[$file])) {
			$props = array();
			$cmd = "svnlook proplist -t '{$this->_transaction}' '{$this->_repository}' '$file'";
			$output = null;
			exec($cmd, $output);
			foreach ($output as $line) {
				$propname = trim($line);
				$cmd = "svnlook propget -t '{$this->_transaction}' '{$this->_repository}' $propname" . " '$file'";
				$output2 = null;
				exec($cmd, $output2);
				$propval = trim(implode($output2));

				$props[] = "$propname=$propval";
			}
			$cached[$file] = $props;
		}
		return $cached[$file];
	}
	/**
	 * Get commit files list
	 *
	 * @return array filenames are keys and status letters are values
	 */
	protected function getCommitList() {
		if (null !== $this->_commitList) {
			return $this->_commitList;
		}
		$output = null;
		$cmd = "svnlook changed -t '{$this->_transaction}' '{$this->_repository}'";
		exec($cmd, $output);
		$list = array();
		foreach ($output as $item) {
			$pos = strpos($item, ' ');
			$status = substr($item, 0, $pos);
			$file = trim(substr($item, $pos));

			$list[$file] = $status;
		}

		$this->_commitList = $list;
		return $this->_commitList;
	}
	/**
	 * Get array of modified and added files
	 *
	 * @param array $filetypes array of file types used for filtering
	 * @return array
	 */
	protected function getChangedFiles(array $filetypes=array()) {
		if (null === $this->_changedFiles) {
			$list = $this->getCommitList();
			$files = array();
			foreach ($list as $file => $status) {
				if ('D' == $status || substr($file, -1) == DIRECTORY_SEPARATOR) {
					continue;
				}
				$files[] = $file;
			}
			$this->_changedFiles = $files;
		}
		$files = array();
		foreach ($this->_changedFiles as $file) {
			$extension = pathinfo($file, PATHINFO_EXTENSION);
			$extension = strtolower($extension);
			if ($filetypes && !in_array($extension, $filetypes)) {
				continue;
			}
			$files[$file] = $extension;
		}
		return $files;
	}
	/**
	 * Check if log message validates length rules
	 *
	 * @param int $minlength minimum length of log message
	 * @return bool
	 */
	protected function _testLogMessageLength($minlength = 1) {
		$length = strlen(trim($this->getLogMessage()));
		if ($length < $minlength) {
			if ($minlength <= 1) {
				$this->msg = "Log message should not be empty.";
			} else {
				$this->msg = "Your log message is too short, be more descriptive.";
			}
			return false;
		}
		return true;
	}
	/**
	 * Tests if the committed files pass PHP syntax checking
	 *
	 * @param array $filetypes array of file types which should be tested
	 * @return bool
	 */
	protected function _testPHPLint(array $filetypes=array()) {
		$result = true;
		$files = $this->getChangedFiles($filetypes);

		$tempDir = sys_get_temp_dir();
		foreach ($files as $file => $extension) {
			$content = $this->getFileContent($file);
			$tempfile = tempnam($tempDir, "stax_");
			file_put_contents($tempfile, $content);
			$tempfile = realpath($tempfile); //sort out the formatting of the filename
			$output = shell_exec(self::$PHP . ' -l "' . $tempfile . '"');
			//try to find a parse error text and chop it off
			$syntaxErrorMsg = preg_replace("/Errors parsing.*$/", "", $output, -1, $count);
			if ($count > 0) { //found errors
				$result = false;
				$syntaxErrorMsg = str_replace($tempfile, $file, $syntaxErrorMsg); //replace temp filename with real filename
				$syntaxErrorMsg = rtrim($syntaxErrorMsg);
				$this->msg .= "\t[$file] PHP lint error in file. Message: $syntaxErrorMsg\n";
			}
			unlink($tempfile);
		}
		return $result;
	}
	/**
	 * Check if files have var_dump, var_export, or print_r in them
	 *
	 * @param array $filetypes
	 * @return bool
	 */
	function _testDebugOutput(array $filetypes = array()) {
		$result = true;
		$files = $this->getChangedFiles(array_keys($filetypes));
		foreach ($files as $file => $extension) {
			$content = $this->getFileContent($file);
			$tokens = token_get_all($content);
			foreach ($tokens as $t) {
				foreach ($tokens as $t) {
					//if the token id is an int (sometimes it isn't)
					if (is_int($t[0])) {
						//if it matches our debug stuff...
						if ($t[0] == T_STRING && (in_array($t[1], self::$debug_functions) || preg_match('/('.implode('|', self::$debug_patterns).')/', $t[1]))) {
							$this->msg .= "\t[$file] " . $t[1] . " found on line " . $t[2] . "\n";
							$result = false;
						}
					}
				}
			}
		}
		if (!$result) {
			$this->msg = rtrim($this->msg);
			$this->msg = "Debug output found:\n$this->msg";
		}
		return $result;
	}
}
