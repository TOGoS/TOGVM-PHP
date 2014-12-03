<?php

$TOGoS_TOGVM_expressionDir = __DIR__.'/../../../../../test-vectors/json-expressions';

class TOGoS_TOGVM_CompositeBlob extends Nife_AbstractBlob
{
	protected $elements;
	
	public function __construct( array $elements ) {
		$this->elements = $elements;
	}
	
	protected static function describe($thing) {
		if( gettype($thing) == 'object' ) {
			return get_class($thing);
		} else {
			return gettype($thing);
		}
	}
	
	protected static function strlen($thing) {
		if( is_string($thing) ) return strlen($thing);
		if( $thing instanceof Nife_Blob ) return $thing->getLength();
		throw new Exception("Don't know how to get length of ".self::describe($thing));
	}
	
	protected static function write($thing, $dest) {
		if( is_string($thing) ) call_user_func($dest, $thing);
		if( $thing instanceof Nife_Blob ) $thing->writeTo($dest);
		throw new Exception("Don't know how to write ".self::describe($thing));
	}
	
	public function __toString() {
		$str = '';
		foreach( $this->elements as $e ) $str .= (string)$e;
		return $str;
	}
	
	public function getLength() {
		$len = 0;
		foreach( $this->elements as $e ) $len += self::strlen($e);
		return $len;
	}
	
	public function writeTo($dest) {
		foreach( $this->elements as $e ) self::write($e, $dest);
	}
}

class TOGoS_TOGVM_ExpressionEvaluationTest extends PHPUnit_Framework_TestCase
{
	protected function setUp() {
		$this->interpreter = new TOGoS_TOGVM_Interpreter( array(
			'functions' => array(
				'http://ns.nuke24.net/TOGVM/Functions/Concatenate' => function($operands) {
					// TODO: Concatenate might work on sequences other than strings.
					// Need to check type of operands.
					return new TOGoS_TOGVM_CompositeBlob($operands);
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
