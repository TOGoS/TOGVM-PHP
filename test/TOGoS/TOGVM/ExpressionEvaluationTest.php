<?php

class TOGoS_TOGVM_ExpressionEvaluationTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'expressions'; }
	protected function getTestVectorExtensions() { return array('json','expected-value'); }
	
	protected function setUp() {
		$repos = [];
		$repos[] = new TOGoS_PHPN2R_FSSHA1Repository($this->getTestVectorDirectory());
		$this->blobFetcher = function($urn) use ($repos) {
			foreach( $repos as $repo ) {
				if( ($blob = $repo->getBlob($urn)) !== null ) return $blob;
			}
		};
		$this->interpreter = new TOGoS_TOGVM_Interpreter( array(
			'functions' => array(
				'http://ns.nuke24.net/TOGVM/Functions/ResolveHashURN' => function($arguments) {
					return call_user_func($this->blobFetcher, $arguments[0]);
				},
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
				},
				'http://ns.nuke24.net/TOGVM/Functions/Subtract' => function($arguments) {
					return $arguments[0] - $arguments[1];
				}
			)
		));
		$this->compiler = new TOGoS_TOGVM_Compiler_InterpreterBinder( $this->interpreter );
	}
	
	protected function evalExpressionAst(array $ast) {
		$compiled = $this->compiler->compileExpression($ast);
		return call_user_func($compiled, array('variableResolver' => array('some variable'=>56)));
	}
	
	protected function stringify($v) {
		if( $v === false ) return "false";
		if( $v === null ) return "null";
		return (string)$v;
	}
	
	public function _testFilePair($expressionJson, $expressionJsonFilename, $expectedValue, $expectedValueFilename) {
		$expressionAst = EarthIT_JSON::decode($expressionJson);
		$actualValue = self::stringify($this->evalExpressionAst($expressionAst));
		preg_match('#/([^/]+)\.json$#',$expressionJsonFilename,$bif);
		$this->assertEquals(rtrim($expectedValue), rtrim($actualValue), "Value of '{$bif[1]}' did not match expected.");
	}
}
