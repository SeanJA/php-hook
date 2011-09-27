<?php
require 'hooks.php';
/**
 * Subversion pre-commit hook script validating commit log message
 */

/**
 * Class for performing various tests in subversion pre-commit hooks
 *
 */
class svn_hooks extends hooks{
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
}

