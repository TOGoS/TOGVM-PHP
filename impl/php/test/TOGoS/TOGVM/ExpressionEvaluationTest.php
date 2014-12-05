<?php

$TOGoS_TOGVM_expressionDir = __DIR__.'/../../../../../test-vectors/json-expressions';

class TOGoS_TOGVM_ExpressionEvaluationTest extends PHPUnit_Framework_TestCase
{
	protected function setUp() {
		$this->interpreter = new TOGoS_TOGVM_Interpreter( array(
			'functions' => array(
				'http://ns.nuke24.net/TOGVM/Functions/Concatenate' => function($operands) {
					// TODO: Concatenate might work on sequences other than strings.
					// Need to check type of operands.
					return new TOGoS_TOGVM_Thneed($operands);
				}
			)
		));
		$this->compiler = new TOGoS_TOGVM_Compiler_InterpreterBinder( $this->interpreter );
	}
	
	protected function evalExpressionAst(array $ast) {
		$compiled = $this->compiler->compileExpression($ast);
		return call_user_func($compiled, array());
	}
	
	public function _testExpressionFile($f) {
		$expressionJson = file_get_contents($f);
		$expectedValue = file_get_contents("$f.expected-value");
		$expressionAst = EarthIT_JSON::decode($expressionJson);
		$actualValue = (string)$this->evalExpressionAst($expressionAst);
		$this->assertEquals($expectedValue, $actualValue, "Value of '$f' did not match expected.");
	}
	
	protected function loadExpressionFileList() {
		global $TOGoS_TOGVM_expressionDir;
		
		$list = array();
		$dh = opendir($TOGoS_TOGVM_expressionDir);
		if( $dh === false ) {
			throw new Exception("Failed to open directory  '$TOGoS_TOGVM_expressionDir'");
		}
		while( ($fn = readdir($dh)) !== false ) {
			if( preg_match('/^(.*\.json)\.expected-value$/', $fn, $bif) ) {
				$jsonFile = $TOGoS_TOGVM_expressionDir."/".$bif[1];
				$list[] = $jsonFile;
			}
		}
		closedir($dh);
		return $list;
	}
	
	public function testAllExpressions() {
		$files = $this->loadExpressionFileList();
		if( count($files) == 0 ) {
			throw new Exception("Failed to load expression file list!");
		}
		foreach( $files as $f ) {
			$this->_testExpressionFile($f);
		}
	}
}
