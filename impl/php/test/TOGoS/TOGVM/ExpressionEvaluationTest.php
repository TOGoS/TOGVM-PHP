<?php

class TOGoS_TOGVM_ExpressionEvaluationTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'expressions'; }
	protected function getTestVectorExtensions() { return array('json','expected-value'); }
	
	protected function setUp() {
		$this->interpreter = new TOGoS_TOGVM_Interpreter( array(
			'functions' => array(
				'http://ns.nuke24.net/TOGVM/Functions/Concatenate' => function($arguments) {
					// TODO: Concatenate might work on sequences other than strings.
					// Need to check type of operands.
					return new TOGoS_TOGVM_Thneed($arguments);
				},
				'http://ns.nuke24.net/TOGVM/Functions/Add' => function($arguments) {
					$rez = 0;
					foreach( $arguments as $k=>$v ) {
						$rez += $v;
					}
					return $rez;
				}
			)
		));
		$this->compiler = new TOGoS_TOGVM_Compiler_InterpreterBinder( $this->interpreter );
	}
	
	protected function evalExpressionAst(array $ast) {
		$compiled = $this->compiler->compileExpression($ast);
		return call_user_func($compiled, array('variableResolver' => array('some variable'=>56)));
	}
	
	public function _testFilePair($expressionJson, $expressionJsonFilename, $expectedValue, $expectedValueFilename) {
		$expressionAst = EarthIT_JSON::decode($expressionJson);
		$actualValue = (string)$this->evalExpressionAst($expressionAst);
		preg_match('#/([^/]+)\.json$#',$expressionJsonFilename,$bif);
		$this->assertEquals($expectedValue, $actualValue, "Value of '{$bif[1]}' did not match expected.");
	}
}
