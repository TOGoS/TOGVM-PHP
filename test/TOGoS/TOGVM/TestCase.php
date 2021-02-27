<?php

abstract class TOGoS_TOGVM_TestCase extends TOGoS_SimplerTest_TestCase
{
	protected function findTestVectorDirectory() {
		for( $dir = __DIR__, $x=0; is_dir($dir) and $x<20; $dir = dirname($dir), ++$x ) {
			if( is_dir($tvDir = "$dir/test-vectors") ) return $tvDir;
			if( is_dir($tvDir = "$dir/vendor/togos/togvm-spec/test-vectors") ) return $tvDir;
		}
		throw new Exception("Couldn't find 'test-vectors' directory.");
	}
	
	protected $testVectorDir;
	protected function getTestVectorDirectory() {
		if( $this->testVectorDir === null ) {
			$this->testVectorDir = $this->findTestVectorDirectory();
		}
		return $this->testVectorDir;
	}

	protected function getTestLanguageConfig() {
		$config = TOGoS_TOGES_Util::loadOperators($this->getTestVectorDirectory().'/test-language.json');
		$config['flushingOperators'] = ["\n"];
		return $config;
	}
}
