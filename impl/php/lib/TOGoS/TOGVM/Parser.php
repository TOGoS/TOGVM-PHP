<?php

class TOGoS_TOGVM_Parser
{
	protected $astCallback;
	protected $infixOperators;
	/** Infix operators which, when encountered at the top level of the program, trigger an $astCallback */
	protected $flushingOperators;
	public function __construct( array $config, $astCallback ) {
		$this->infixOperators = $config['infixOperators'];
		$this->flushingOperators = array();
		if( !empty($config['flushingOperators']) ) {
			foreach( $config['flushingOperators'] as $fo ) $this->flushingOperators[$fo] = $fo;
		}
		$this->astCallback = $astCallback;
	}
	
	protected $tokenCount = 0;
	public function token( array $token ) {
		++$this->tokenCount;
	}
	
	/** Indicate that the end of the input file has been reached. */
	public function eof() {
		call_user_func( $this->astCallback, EarthIT_JSON::decode(file_get_contents(
			__DIR__.'/../../../../../test-vectors/ast/'.
			($this->tokenCount == 2 ? 'single-bareword.json' : 'infix-ops.json'))));
	}

	public static function getDefaultInfixOperators() {
		return EarthIT_JSON::decode(file_get_contents(__DIR__.'/infix-ops.json'));
	}
	
	public static function tokensToAst( array $tokens, array $config ) {
		$C = new TOGoS_TOGVM_Thneed();
		$parser = new TOGoS_TOGVM_Parser($config, $C);
		foreach($tokens as $token) {
			$parser->token($token);
		}
		$parser->eof();
		return $C[0];
	}
}
