<?php

abstract class TOGoS_TOGVM_MultiTestCase extends TOGoS_TOGVM_TestCase
{
	protected abstract function _testFilePair( $input, $inputFilename, $expectedOutput, $expectedOutputFilename );
	
	protected abstract function getTestVectorSubdirectoryName();
	
	/** @return array(input extension, expectation extension) */
	protected abstract function getTestVectorExtensions();
	
	protected function getTestVectorFilePairs() {
		$testVectorSubdir = $this->getTestVectorDirectory().'/'.$this->getTestVectorSubdirectoryName();
		
		$list = array();
		$dh = opendir($testVectorSubdir);
		if( $dh === false ) {
			throw new Exception("Failed to open $testVectorSubdir");
		}
		$filesFound = array();
		while( ($fn = readdir($dh)) !== false ) {
			$filesFound[$fn] = $fn;
		}
		closedir($dh);
		
		list($inputExtension,$outputExtension) = $this->getTestVectorExtensions();
		$pairs = array();
		foreach( $filesFound as $fn ) {
			if( preg_match("/^(.+?)\\.{$inputExtension}\$/",$fn,$bif) ) {
				$base = $bif[1];
				$expectedOutputBasename = "$base.$outputExtension";
				if( isset($filesFound[$expectedOutputBasename]) ) {
					$pairs[$base] = array("$testVectorSubdir/$fn", "$testVectorSubdir/$expectedOutputBasename");
				}
			}
		}
		
		ksort($pairs, SORT_STRING);
		
		return $pairs;
	}
	
	public function testIO() {
		$testCount = 0;
		foreach( $this->getTestVectorFilePairs() as $p ) {
			list($inputFile, $outputFile) = $p;
			$input = file_get_contents($inputFile);
			$output = file_get_contents($outputFile);
			$this->setName(get_class($this)."#_testFilePair($inputFile, $outputFile)");
			$this->_testFilePair($input, $inputFile, $output, $outputFile);
			++$testCount;
		}
		if( $testCount == 0 ) {
			$this->fail("No test vectors found");
		}
	}
}
