<?php

class TOGoS_TOGVM_TokenizerTest extends PHPUnit_Framework_TestCase
{
	public function _testTokenize( array $expected, $source, $sourceFilename ) {
		$sourceLocation = array('filename'=>$sourceFilename, 'lineNumber'=>1, 'columnNumber'=>1);
		$tokens = TOGoS_TOGVM_Tokenizer::tokenize($source, $sourceLocation);
		$actual = array();
		foreach( $tokens as $t ) {
			$actual[] = array('value'=>$t['value'], 'quoting'=>$t['quoting']);
		}
		$this->assertEquals($expected, $actual);
	}
	
	public function testTokenize() {
		$tokenDir = __DIR__.'/../../../../../test-vectors/tokens';
		$dh = opendir($tokenDir);
		while( ($fn = readdir($dh)) !== false ) {
			if( preg_match('/(.*)\.json$/', $fn, $bif) ) {
				$tokenJsonFile = $tokenDir."/".$fn;
				$sourceFile = $tokenDir."/".$bif[1].".txt";
				$tokenJson = file_get_contents($tokenJsonFile);
				$source = file_get_contents($sourceFile);
				$expectedTokens = EarthIT_JSON::decode($tokenJson);
				$this->_testTokenize($expectedTokens, $source, $sourceFile);
			}
		}
		closedir($dh);
	}
}

