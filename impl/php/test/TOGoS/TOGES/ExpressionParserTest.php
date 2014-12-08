<?php

class TOGoS_TOGES_FakeArray implements ArrayAccess
{
	public function offsetExists($k) { return false; }
	public function offsetGet($k) { throw new Exception(); }
	public function offsetSet($k,$v) { throw new Exception(); }
	public function offsetUnset($k) { throw new Exception(); }
}

class TOGoS_TOGES_VariableSymbolTable extends TOGoS_TOGES_FakeArray
{
	public function offsetExists($k) {
		return true;
	}
	public function offsetGet($k) {
		return ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/Variable', 'variableName'=>$k];
	}
}

class TOGoS_TOGES_OverrideSymbolTable extends TOGoS_TOGES_FakeArray
{
	protected $parent;
	protected $overrides;
	
	public function __construct($parent, $overrides) {
		$this->parent = $parent;
		$this->overrides = $overrides;
	}
	
	public function offsetExists($k) {
		return isset($this->overrides[$k]) or isset($this->parent[$k]);
	}

	public function offsetGet($k) {
		return isset($this->overrides[$k]) ? $this->overrides[$k] : $this->parent[$k];
	}
}

class TOGoS_TOGES_ExpressionParserTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'expressions'; }
	protected function getTestVectorExtensions() { return ['txt','json']; }
	
	protected function parseAst($source, $sourceFile) {
		$beginSourceLocation = ['filename'=>$sourceFile, 'lineNumber'=>1, 'columnNumber'=>1];
		$endSourceLocation = $beginSourceLocation;
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $endSourceLocation);
		
		$parserConfig = [
			'operators'         => TOGoS_TOGES_Parser::getDefaultOperators(),
			'flushingOperators' => ["\n"]
		];
		return TOGoS_TOGES_Parser::tokensToAst($tokens, array_merge($beginSourceLocation,[
			'endLineNumber' => $endSourceLocation['lineNumber'],
			'endColumnNumber' => $endSourceLocation['columnNumber']
		]), $parserConfig);
	}

	protected function parseExpression($source, $sourceFile) {
		$ast = $this->parseAst($source, basename($sourceFile));
		
		$symbolTable = new TOGoS_TOGES_OverrideSymbolTable( new TOGoS_TOGES_VariableSymbolTable(), [
			'forty two' => ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/LiteralInteger','literalValue'=>42]
		]);
		
		$expressionParser = new TOGoS_TOGES_ExpressionParser(
			TOGoS_TOGES_Parser::getDefaultOperators(),
			$symbolTable
		);
		return $expressionParser->astToExpression($ast);
	}
	
	public function _testFilePair($source, $sourceFile, $expectedExpressionJson, $expectedExpressionJsonFile) {
		$expectedExpression = EarthIT_JSON::decode($expectedExpressionJson);
		$this->setName("astToExpression('".basename($sourceFile)."')");
		$expression = $this->parseExpression($source, $sourceFile);
		TOGoS_TOGVM_TestUtil::matchSourceLocateyness($expression, $expectedExpression);
		if( $expression != $expectedExpression ) {
			echo "Expression returned by astToExpression = ", EarthIT_JSON::prettyEncode($expression), "\n";
		}
		$this->assertEquals($expectedExpression, $expression);
	}
}
