<?php

require 'hooks.php';

class git_hooks extends hooks {
	
	public function __construct($repository, array $tests) {
		return parent::__construct($repository, $transaction = null, $tests);
	}

	/**
	 * Get commit log message
	 *
	 * @return string
	 */
	protected function getLogMessage() {
		return 1;
	}

	/**
	 * Get content of file from current transaction
	 *
	 * @param string $file
	 * @return string
	 * @throws Exception
	 */
	protected function getFileContent($file) {
		return 1;
	}

	/**
	 * Get svn properties for file
	 *
	 * @param string $file
	 * @return array
	 */
	protected function getFileProps($file) {
		return 1;
	}

	/**
	 * Get commit files list
	 *
	 * @return array filenames are keys and status letters are values
	 */
	protected function getCommitList() {
		$output = array();
		$return = 0;
		exec('git rev-parse --verify HEAD 2> /dev/null', $output, $return);
		$against = $return == 0 ? 'HEAD' : '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

		exec("git diff-index --cached --name-only {$against}", $output);

		$exit_status = 0;

		var_dump($output);

		return 1;
	}

	/**
	 * Get array of modified and added files
	 *
	 * @param array $filetypes array of file types used for filtering
	 * @return array
	 */
	protected function getChangedFiles(array $filetypes=array()) {
		$this->getCommitList();
		return 1;
	}
}