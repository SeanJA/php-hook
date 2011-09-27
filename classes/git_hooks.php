<?php

require 'hooks.php';

class git_hooks extends hooks {
	
	/**
	 * override the constructor because git only sends through one param, you have to figure out the second one
	 * @param string $repository
	 * @param array $tests
	 */
	public function __construct($repository, array $tests) {
		parent::__construct($repository, $transaction = null, $tests);
	}

	/**
	 * Get commit log message
	 *
	 * @return string
	 */
	protected function getLogMessage() {
		throw new Exception('Not implemented yet');
	}

	/**
	 * Get content of file from current transaction
	 *
	 * @param string $file
	 * @return string
	 * @throws Exception
	 */
	protected function getFileContent($file) {
		throw new Exception('Not implemented yet');
	}

	/**
	 * Get svn properties for file
	 *
	 * @param string $file
	 * @return array
	 */
	protected function getFileProps($file) {
		throw new Exception('Not implemented yet');
	}

	/**
	 * Get commit files list
	 *
	 * @return array filenames are keys and status letters are values
	 */
	protected function getCommitList() {
		throw new Exception('Not implemented yet');
	}

	/**
	 * Get array of modified and added files
	 *
	 * @param array $filetypes array of file types used for filtering
	 * @return array
	 */
	protected function getChangedFiles(array $filetypes=array()) {
		throw new Exception('Not implemented yet');
	}
}