<?php

abstract class hooks {

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
		return ($result >= 1)? 1:0;
	}

	/**
	 * Get commit log message
	 *
	 * @return string
	 */
	abstract protected function getLogMessage();

	/**
	 * Get content of file from current transaction
	 *
	 * @param string $file
	 * @return string
	 * @throws Exception
	 */
	abstract protected function getFileContent($file);

	/**
	 * Get svn properties for file
	 *
	 * @param string $file
	 * @return array
	 */
	abstract protected function getFileProps($file);

	/**
	 * Get commit files list
	 *
	 * @return array filenames are keys and status letters are values
	 */
	abstract protected function getCommitList();

	/**
	 * Get array of modified and added files
	 *
	 * @param array $filetypes array of file types used for filtering
	 * @return array
	 */
	abstract protected function getChangedFiles(array $filetypes=array());

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
	protected function _testDebugOutput(array $filetypes = array()) {
		$result = true;
		$files = $this->getChangedFiles($filetypes);
		foreach ($files as $file => $extension) {
			$content = $this->getFileContent($file);
			$tokens = token_get_all($content);
			foreach ($tokens as $t) {
				foreach ($tokens as $t) {
					//if the token id is an int (sometimes it isn't)
					if (is_int($t[0])) {
						//if it matches our debug stuff...
						if ($t[0] == T_STRING && (in_array($t[1], self::$debug_functions) || preg_match('/(' . implode('|', self::$debug_patterns) . ')/', $t[1]))) {
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
