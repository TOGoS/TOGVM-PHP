<?php

class TOGoS_TOGES_TokenizerTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'tokens'; }
	protected function getTestVectorExtensions() { return array('txt','json'); }
	
	public function _testTokenize( array $expected, $source, $sourceFilename ) {
		$sourceLocation = array('filename'=>$sourceFilename, 'lineNumber'=>1, 'columnNumber'=>1);
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
		$actual = array();
		foreach( $tokens as $t ) {
			$actual[] = array('value'=>$t['value'], 'quoting'=>$t['quoting']);
		}
		$this->assertEquals($expected, $actual, $sourceFilename);

		$newlineCount = substr_count($source,"\n");
		$expectedEndLine = 1 + $newlineCount;
		$expectedEndColumn = 1 + strlen($source) - ($newlineCount ? strrpos($source, "\n")+1 : 0);
		$this->assertEquals($expectedEndLine  , $sourceLocation['endLineNumber']);
		$this->assertEquals($expectedEndColumn, $sourceLocation['endColumnNumber']);
	}

	public function _testFilePair($source, $sourceFile, $expectedTokenJson, $expectedAstJsonFile) {
		$this->_testTokenize(EarthIT_JSON::decode($expectedTokenJson), $source, basename($sourceFile));
	}
	
	public function testParseError() {
		$failures = array();
		foreach( array(
			"foo«bar",
			"foo\\bar"
		) as $source ) {
			try {
				$sourceLocation = array('filename'=>__FILE__, 'lineNumber'=>__LINE__, 'columnNumber'=>1);
				$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
				$failures[] = $source;
			} catch( TOGoS_TOGVM_ParseError $e ) {
				// that's the correct parse error!
			}
		}
		
		if( $failures ) $this->fail("Tokenizer should have thrown a ParseError for these: ".json_encode($failures));
	}
}
