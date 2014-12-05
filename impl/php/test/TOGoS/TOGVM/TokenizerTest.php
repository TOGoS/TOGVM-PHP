<?php

class TOGoS_TOGVM_TokenizerTest extends PHPUnit_Framework_TestCase
{
	public function _testTokenize( array $expected, $source, $sourceFilename ) {
		$tokens = TOGoS_TOGVM_Tokenizer::tokenize($source, $sourceFilename, 1, 1);
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
				$jsonFile = $tokenDir."/".$fn;
				$tokenFile = $tokenDir."/".$bif[1].".txt";
				$json = file_get_contents($jsonFile);
				$txt = file_get_contents($tokenFile);
				$expected = EarthIT_JSON::decode($json);
				$this->_testTokenize($expected, $txt, $tokenFile);
			}
		}
		closedir($dh);
	}
}

