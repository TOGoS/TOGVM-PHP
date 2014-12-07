<?php

abstract class TOGoS_TOGES_ParseState {
	protected $PC;
	protected $astCallback;
	
	public function __construct( TOGoS_TOGES_ParserConfig $PC, callable $astCallback ) {
		$this->PC = $PC;
		$this->astCallback = $astCallback;
	}
	
	/** Returns the new parse state */
	public abstract function _token( array $ti );
	
	protected function utt( array $ti ) {
		throw new TOGoS_TOGES_ParseError(
			TOGoS_TOGES_Parser::tokenInfoToString($ti)." at ".
			TOGoS_TOGES_Util::sourceLocationToString($ti['sourceLocation'])." not handled by ".
			get_class($this),
			array($ti['sourceLocation'])
		);
	}
}

class TOGoS_TOGES_ParseState_LValue extends TOGoS_TOGES_ParseState
{
	protected $leftAst;
	protected $minPrecedence;
	
	public function __construct( TOGoS_TOGES_ParserConfig $PC, $leftAst, $minPrecedence, callable $astCallback ) {
		parent::__construct($PC, $astCallback);
		$this->leftAst = $leftAst;
		$this->minPrecedence = $minPrecedence;
	}
	
	protected function letSomeoneElseHandle( array $ti ) {
		return call_user_func($this->astCallback, $this->leftAst)->_token($ti);
	}
	
	public function _token( array $ti ) {
		switch( $ti['type'] ) {
		case TOGoS_TOGES_Parser::TT_OPEN_BRACKET:
			$bracket = $this->PC->bracketsByOpenBracket[$ti['openBracket']];
			if( $bracket['precedence'] >= $this->minPrecedence ) {
				return new TOGoS_TOGES_ParseState_Initial($this->PC, $bracket, function($ast) use ($bracket) {
					$ast = array(
						'type' => 'operation',
						'operatorName' => $bracket['openBracket'].$bracket['closeBracket'],
						'operands' => array( $this->leftAst, $ast ),
						'sourceLocation' => TOGoS_TOGES_Parser::mergeSourceLocations($this->leftAst['sourceLocation'], $ast['sourceLocation'])
					);
					return new TOGoS_TOGES_ParseState_LValue($this->PC, $ast, $this->minPrecedence, $this->astCallback);
				});
			}
			return $this->letSomeoneElseHandle($ti);
		case TOGoS_TOGES_Parser::TT_OPERATOR:
			if( !isset($this->PC->infixOperators[$ti['name']]) ) $this->utt($ti);
			$op = $this->PC->infixOperators[$ti['name']];
			if(
				$op['precedence'] > $this->minPrecedence or
				$op['precedence'] == $this->minPrecedence && $op['associativity'] == 'right'
			) {
				return new TOGoS_TOGES_ParseState_Infix( $this->PC, $this->leftAst, $ti['name'], $op['precedence'], function($ast) {
					return new TOGoS_TOGES_ParseState_LValue($this->PC, $ast, $this->minPrecedence, $this->astCallback);
				});
			}
			return $this->letSomeoneElseHandle($ti);
		case TOGoS_TOGES_Parser::TT_CLOSE_BRACKET: case TOGoS_TOGES_Parser::TT_EOF:
			return $this->letSomeoneElseHandle($ti);
		default: $this->utt($ti);
		}
	}
}

class TOGoS_TOGES_ParseState_Infix extends TOGoS_TOGES_ParseState
{
	protected $leftAst;
	protected $operatorName;
	protected $minPrecedence;
	
	public function __construct( TOGoS_TOGES_ParserConfig $PC, $leftAst, $opName, $minPrecedence, callable $astCallback ) {
		parent::__construct($PC, $astCallback);
		$this->leftAst = $leftAst;
		$this->operatorName = $opName;
		$this->minPrecedence = $minPrecedence;
	}
	
	public function _ast( array $ast ) {
		return new TOGoS_TOGES_ParseState_LValue($this->PC, $ast, $this->minPrecedence, function($ast) {
			$ast = array(
				'type' => 'operation',
				'operatorName' => $this->operatorName,
				'operands' => array($this->leftAst, $ast),
				'sourceLocation' => TOGoS_TOGES_Parser::mergeSourceLocations($this->leftAst['sourceLocation'], $ast['sourceLocation'])
			);
			return call_user_func($this->astCallback, $ast);
		});
	}

	protected function operatorMayBeIgnored() {
		return !empty($this->PC->infixOperators[$this->operatorName]['ignorableAsPostfix']);
	}
	
	public function _token( array $ti ) {
		switch( $ti['type'] ) {
		case TOGoS_TOGES_Parser::TT_LITERAL:
			$ast = array('type'=>'literal', 'value'=>$ti['value'], 'sourceLocation'=>$ti['sourceLocation']);
			return $this->_ast($ast);
		case TOGoS_TOGES_Parser::TT_WORD:
			return new TOGoS_TOGES_ParseState_Word($this->PC, array($ti['value']), $ti['sourceLocation'], array($this,'_ast'));
		case TOGoS_TOGES_Parser::TT_OPERATOR:
			if( isset($this->PC->prefixOperators[$ti['name']]) ) {
				throw new Exception("Prefix operators not yet supported");
			}
			$op = $this->PC->infixOperators[$ti['name']];
			$myOp = $this->PC->infixOperators[$this->operatorName];
			if( !empty($myOp['ignorableAsPostfix']) ) {
				if( $op['precedence'] == $this->minPrecedence ) {
					// Ignore myOp by replacing it
					return new TOGoS_TOGES_ParseState_Infix($this->PC, $this->leftAst, $ti['name'], $op['precedence'], $this->astCallback );
				} else if( $op['precedence'] < $this->minPrecedence ) {
					// Ignore myOp by deferring upward
					return call_user_func( $this->astCallback, $this->leftAst )->_token($ti);
				} else {
					// e.g. foo ; + bar, where + isn't a prefix operator
					$this->utt($ti);
				}
			}
		case TOGoS_TOGES_Parser::TT_EOF: case TOGoS_TOGES_Parser::TT_CLOSE_BRACKET:
			if( $this->operatorMayBeIgnored() ) {
				return call_user_func( $this->astCallback, $this->leftAst )->_token($ti);
			}
			// Otherwise fall through to that's an error
		default: $this->utt($ti);
		}
	}
}

class TOGoS_TOGES_ParseState_BlockRead extends TOGoS_TOGES_ParseState
{
	protected $ast;
	protected $bracketPair;
	
	public function __construct( TOGoS_TOGES_ParserConfig $PC, array $ast, $bracketPair, callable $astCallback ) {
		parent::__construct($PC, $astCallback);
		$this->ast = $ast;
		$this->bracketPair = $bracketPair;
	}
	
	protected function closeBracketMatches( array $ti ) {
		$expectedCloseBracket = $this->bracketPair['closeBracket'];
		if( $expectedCloseBracket == '' ) {
			if( $ti['type'] != TOGoS_TOGES_Parser::TT_EOF ) return false;
		} else {
			if( $ti['type'] != TOGoS_TOGES_Parser::TT_CLOSE_BRACKET ) return false;
			if( $ti['closeBracket'] != $expectedCloseBracket ) return false;
		}
		return true;
	}
	
	public function _token( array $ti ) {
		if( $this->closeBracketMatches($ti) ) {
			return call_user_func($this->astCallback, $this->ast);
		} else {
			$this->utt($ti);
		}
	}
}

class TOGoS_TOGES_ParseState_Initial extends TOGoS_TOGES_ParseState
{
	protected $bracketPair;
	
	public function __construct( TOGoS_TOGES_ParserConfig $PC, $bracketPair, callable $astCallback ) {
		parent::__construct($PC, $astCallback);
		$this->bracketPair = $bracketPair;
	}
	
	public function _ast($ast) {
		return new TOGoS_TOGES_ParseState_LValue($this->PC, $ast, 0, function($ast) {
			return new TOGoS_TOGES_ParseState_BlockRead($this->PC, $ast, $this->bracketPair, $this->astCallback);
		});
	}
	
	public function _token( array $ti ) {
		// This might end up being almost exactly the same as for Infix.
		// Maybe they can share _token implementation somehow.
		switch( $ti['type'] ) {
		case TOGoS_TOGES_Parser::TT_LITERAL:
			$ast = array('type'=>'literal', 'value'=>$ti['value'], 'sourceLocation'=>$ti['sourceLocation']);
			return $this->_ast($ast);
		case TOGoS_TOGES_Parser::TT_WORD:
			return new TOGoS_TOGES_ParseState_Word($this->PC, array($ti['value']), $ti['sourceLocation'], array($this,'_ast'));
		case TOGoS_TOGES_Parser::TT_OPERATOR:
			if( isset($this->PC->prefixOperators[$ti['name']]) ) {
				throw new Exception("Prefix operators not yet supported");
			}
			if( isset($this->PC->infixOperators[$ti['name']]) ) {
				if( $this->PC->infixOperators[$ti['name']]['ignorableAsPrefix'] ) {
					return $this;
				}
			}
		default: $this->utt($ti);
		}
	}
}

class TOGoS_TOGES_ParseState_Word extends TOGoS_TOGES_ParseState
{
	protected $words;
	protected $sourceLocation;
	
	public function __construct( TOGoS_TOGES_ParserConfig $PC, array $words, $sourceLocation, callable $astCallback ) {
		parent::__construct($PC, $astCallback);
		$this->words = $words;
		$this->sourceLocation = $sourceLocation;
	}
	
	public function _token( array $ti ) {
		switch( $ti['type'] ) {
		case TOGoS_TOGES_Parser::TT_WORD:
			return new TOGoS_TOGES_ParseState_Word(
				$this->PC,
				array_merge($this->words, array($ti['value'])),
				TOGoS_TOGES_Parser::mergeSourceLocations($this->sourceLocation, $ti['sourceLocation']),
				$this->astCallback
			);
		default:
			// Anything else is the end of the phrase;
			// Let someone else handle it!
			$ast = array(
				'type' => 'phrase',
				'words' => $this->words,
				'sourceLocation' => $this->sourceLocation
			);
			return call_user_func($this->astCallback, $ast)->_token($ti);
		}
	}
}

class TOGoS_TOGES_ParserConfig
{
	public $infixOperators;
	public $prefixOperators;
	public $brackets;
	public $bracketsByOpenBracket = array();
	
	public function __construct( $config ) {
		$this->infixOperators = $config['infixOperators'];
		$this->prefixOperators = $config['prefixOperators'];
		$this->brackets = $config['brackets'];
		foreach( $config['brackets'] as $b ) {
			$this->bracketsByOpenBracket[$b['openBracket']] = $b;
		}
	}
}

class TOGoS_TOGES_Parser
{
	protected $prefixOperators = array();
	protected $infixOperators = array();
	protected $bracketsByOpenBracket = array();
	protected $bracketsByCloseBracket = array();
	
	public function __construct( array $config, callable $astCallback ) {
		$PC = new TOGoS_TOGES_ParserConfig($config);
		$this->infixOperators = $config['infixOperators'];
		$this->prefixOperators = $config['prefixOperators'];
		$this->state = new TOGoS_TOGES_ParseState_Initial($PC, array('openBracket'=>'', 'closeBracket'=>''), $astCallback );
		foreach( $config['brackets'] as $b ) {
			$this->bracketsByOpenBracket[$b['openBracket']] = $b;
			$this->bracketsByCloseBracket[$b['closeBracket']] = $b;
		}
	}
	
	const TT_WORD          = 'wrd';
	const TT_LITERAL       = 'lit';
	const TT_OPEN_BRACKET  = 'opn';
	const TT_CLOSE_BRACKET = 'cls';
	const TT_OPERATOR      = 'opr';
	const TT_EOF           = 'eof';
	
	public function parseToken( array $token ) {
		switch( $token['quoting'] ) {
		case 'bare':
			if( isset($this->bracketsByOpenBracket[$token['value']]) ) {
				return array(
					'type' => self::TT_OPEN_BRACKET,
					'openBracket' => $token['value'],
					'closeBracket' => $this->bracketsByOpenBracket[$token['value']]['closeBracket'],
					'sourceLocation' => $token['sourceLocation']
				);
			} else if( isset($this->bracketsByCloseBracket[$token['value']]) ) {
				return array(
					'type' => self::TT_CLOSE_BRACKET,
					'closeBracket' => $token['value'],
					'openBracket' => $this->bracketsByCloseBracket[$token['value']]['openBracket'],
					'sourceLocation' => $token['sourceLocation']
				);			 
			} else if( isset($this->prefixOperators[$token['value']]) or isset($this->infixOperators[$token['value']]) ) {
				return array(
					'type' => self::TT_OPERATOR,
					'name' => $token['value'],
					'sourceLocation' => $token['sourceLocation']
				);
			} else {
				return array(
					'type' => self::TT_WORD,
					'value' => $token['value'],
					'sourceLocation' => $token['sourceLocation']
				);
			}
		case 'single':
			return array(
				'type' => self::TT_WORD,
				'value' => $token['value'],
				'sourceLocation' => $token['sourceLocation']
			);
		case 'double':
			return array(
				'type' => self::TT_LITERAL,
				'value' => $token['value'],
				'sourceLocation' => $token['sourceLocation']
			);
		default:
			throw new Exception("Unrecognized quote style: '{$token['quoting']}'");
		}
	}
	
	public static function tokenInfoToString( array $ti ) {
		switch( $ti['type'] ) {
		case self::TT_WORD: return "'".$ti['value']."'";
		case self::TT_LITERAL: return '"'.$ti['value'].'"';
		case self::TT_OPEN_BRACKET: return $ti['openBracket'];
		case self::TT_CLOSE_BRACKET: return $ti['closeBracket'];
		case self::TT_OPERATOR: return $ti['name'] == "\n" ? "newline" : $ti['name'];
		case self::TT_EOF: return 'EOF';
		default: throw new Exception("Unrecognized parsed token type: '{$ti['type']}'");
		}
	}
	
	public static function mergeSourceLocations( array $a, array $b ) {
		// For now this assumes that $b > $a, since
		// that's the order in which we receive tokens.
		// This function could be modified to check column/line numbers and
		// only expand the range if needed.
		if( $a === null ) return $b;
		$a['endLineNumber']   = $b['endLineNumber'];
		$a['endColumnNumber'] = $b['endColumnNumber'];
		return $a;
	}
	/** In-place version, no 's' */
	protected static function mergeSourceLocation( array &$into, array $append ) {
		$into = self::mergeSourceLocations($into, $append);
	}
		
	protected function _token( array $ti ) {
		$this->state = $this->state->_token($ti);
	}
	
	public function token( array $token ) {
		$this->_token( $this->parseToken($token) );
	}
	
	/** Indicate that the end of the input file has been reached. */
	public function eof( array $sourceLocation ) {
		$sourceLocation['endLineNumber'] = $sourceLocation['lineNumber'];
		$sourceLocation['endColumnNumber'] = $sourceLocation['columnNumber'];
		$this->_token( array(
			'type' => self::TT_EOF,
			'sourceLocation' => $sourceLocation
		));
	}

	public static function getDefaultBrackets() {
		return EarthIT_JSON::decode(file_get_contents(__DIR__.'/brackets.json'));
	}	
	
	public static function getDefaultInfixOperators() {
		return EarthIT_JSON::decode(file_get_contents(__DIR__.'/infix-operators.json'));
	}
	
	public static function getDefaultPrefixOperators() {
		return EarthIT_JSON::decode(file_get_contents(__DIR__.'/prefix-operators.json'));
	}
	
	public static function tokensToAst( array $tokens, array $sourceLocation, array $config ) {
		$C = new TOGoS_TOGVM_Thneed();
		$parser = new TOGoS_TOGES_Parser($config, $C);
		foreach($tokens as $token) {
			$parser->token($token);
		}
		$parser->eof( array(
			'filename'=>$sourceLocation['filename'],
			'lineNumber'=>$sourceLocation['endLineNumber'],
			'columnNumber'=>$sourceLocation['endColumnNumber']
		));
		return $C[0];
	}
}
