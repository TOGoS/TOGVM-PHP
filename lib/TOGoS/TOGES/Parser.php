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
	
	/**
	 * Throw an unhandled token exception
	 */
	protected function utt( array $ti ) {
		throw new TOGoS_TOGVM_ParseError(
			TOGoS_TOGES_Parser::tokenInfoToString($ti)." at ".
			TOGoS_TOGVM_Util::sourceLocationToString($ti['sourceLocation'])." not handled by ".
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
		$rez = call_user_func($this->astCallback, $this->leftAst, $ti);
		if( !is_object($rez) ) {
			throw new Exception("AST callback returned non-ParseState: ".var_export($rez,true)."; ".var_export($this->astCallback,true));
		}
		return $rez->_token($ti);
	}
	
	public function _token( array $ti ) {
		switch( $ti['type'] ) {
		case TOGoS_TOGES_Parser::TT_OPEN_BRACKET:
			$bracket = $this->PC->bracketsByOpenBracket[$ti['openBracket']];
			if( $bracket['bracketPrecedence'] >= $this->minPrecedence ) {
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
			if( !isset($this->PC->operators[$ti['name']]) ) $this->utt($ti);
			$op = $this->PC->operators[$ti['name']];
			if(
				$op['infixPrecedence'] > $this->minPrecedence or
				$op['infixPrecedence'] == $this->minPrecedence && $op['associativity'] == 'right'
			) {
				return new TOGoS_TOGES_ParseState_Infix( $this->PC, $this->leftAst, $ti['name'], $op['infixPrecedence'], function($ast) {
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
	
	// TODO: This shares a lot in common with Initial#_token; should probably make them share somehow
	public function _token( array $ti ) {
		switch( $ti['type'] ) {
		case TOGoS_TOGES_Parser::TT_LITERAL:
			$ast = array('type'=>'literal', 'value'=>$ti['value'], 'sourceLocation'=>$ti['sourceLocation']);
			return $this->_ast($ast);
		case TOGoS_TOGES_Parser::TT_WORD:
			return new TOGoS_TOGES_ParseState_Word($this->PC, array($ti['value']), $ti['sourceLocation'], array($this,'_ast'));
		case TOGoS_TOGES_Parser::TT_OPEN_BRACKET:
			$openBracketTi = $ti;
			$bracket = $this->PC->bracketsByOpenBracket[$openBracketTi['openBracket']];
			return new TOGoS_TOGES_ParseState_Initial($this->PC, $bracket, function($ast,$closeBracketTi) use ($bracket,$openBracketTi) {
				$ast = array(
					'type' => 'operation',
					'operatorName' => $bracket['openBracket'].$bracket['closeBracket'],
					'operands' => array( $ast ),
					'sourceLocation' => TOGoS_TOGES_Parser::mergeSourceLocations(
						$openBracketTi['sourceLocation'],
						$ast['sourceLocation'],
						$closeBracketTi['sourceLocation']
					)
				);
				return new TOGoS_TOGES_ParseState_LValue($this->PC, $ast, 0, array($this,'_ast'));
			});
		case TOGoS_TOGES_Parser::TT_OPERATOR:
			$op = $this->PC->operators[$ti['name']];
			$myOp = $this->PC->operators[$this->operatorName];
			
			// Figure out if we can ignore one
			// $keep = 'mine'|'new'|'both';
			if( !empty($myOp['ignorableAsPostfix']) ) {
				if( !empty($op['ignorableAsPrefix']) ) {
					// If they're the same operator, ignore the new one because it's easier.
					if( $ti['name'] == $this->operatorName ) {
						$keep = 'mine';
					} else if( $myOp['infixPrecedence'] == $op['infixPrecedence'] ) {
						throw new TOGoS_TOGVM_ParseError(
							"Uh oh; found ambiguously ignorable operators '{$this->operatorName}' and '{$ti['name']}' together!",
							[$ti['sourceLocation']]);
					}
					// Go with the one with lower precedence!
					$keep = $myOp['infixPrecedence'] < $op['infixPrecedence'] ? 'mine' : 'new';
				} else {
					$keep = 'new';
				}
			} else if( !empty($op['ignorableAsPrefix']) ) {
				$keep = 'mine';
			} else {
				$keep = 'both';
			}

			
			if( $keep == 'new' ) {
				if( $op['infixPrecedence'] == $this->minPrecedence ) {
					// Ignore myOp by replacing it
					return new TOGoS_TOGES_ParseState_Infix($this->PC, $this->leftAst, $ti['name'], $op['infixPrecedence'], $this->astCallback );
				} else if( $op['infixPrecedence'] < $this->minPrecedence ) {
					// Ignore myOp by deferring upward
					return call_user_func( $this->astCallback, $this->leftAst, $ti )->_token($ti);
				} else {
					// e.g. foo ; + bar, where + isn't a prefix operator
					$this->utt($ti);
				}
			} else if( $keep == 'mine' ) {
				return $this;
			} else {
				// We can't keep both!
				// Unless one or other is a post/prefix operator...
				// But that's for another day.
				$this->utt($ti);
			}
		case TOGoS_TOGES_Parser::TT_EOF: case TOGoS_TOGES_Parser::TT_CLOSE_BRACKET:
			if( !empty($this->PC->operators[$this->operatorName]['ignorableAsPostfix']) ) {
				return call_user_func( $this->astCallback, $this->leftAst, $ti )->_token($ti);
			}
			$this->utt($ti);
		default: $this->utt($ti);
		}
	}
}

// TODO: Somewhere along the line we need to include the source locations
// of the brackets themselves into the resulting AST's sourcelocation
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
			return call_user_func($this->astCallback, $this->ast, $ti);
		} else {
			$this->utt($ti);
		}
	}
}

class TOGoS_TOGES_ParseState_Initial extends TOGoS_TOGES_ParseState
{
	protected $bracketPair;
	
	/**
	 * @param $astCallback callback to be called with the AST of the block
	 */
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
		case TOGoS_TOGES_Parser::TT_OPEN_BRACKET:
			$openBracketTi = $ti;
			$bracket = $this->PC->bracketsByOpenBracket[$openBracketTi['openBracket']];
			return new TOGoS_TOGES_ParseState_Initial($this->PC, $bracket, function($ast,$closeBracketTi) use ($bracket,$openBracketTi) {
				$ast = array(
					'type' => 'operation',
					'operatorName' => $bracket['openBracket'].$bracket['closeBracket'],
					'operands' => array( $ast ),
					'sourceLocation' => TOGoS_TOGES_Parser::mergeSourceLocations(
						$openBracketTi['sourceLocation'],
						$ast['sourceLocation'],
						$closeBracketTi['sourceLocation']
					)
				);
				return new TOGoS_TOGES_ParseState_LValue($this->PC, $ast, 0, array($this,'_ast'));
			});
		case TOGoS_TOGES_Parser::TT_OPERATOR:
			if( isset($this->PC->operators[$ti['name']]) ) {
				if( $this->PC->operators[$ti['name']]['ignorableAsPrefix'] ) {
					return $this;
				}
			}
			if( isset($this->PC->operators[$ti['name']]['prefixPrecedence']) ) {
				throw new Exception("Prefix operators not yet supported");
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
			return call_user_func($this->astCallback, $ast, $ti)->_token($ti);
		}
	}
}

class TOGoS_TOGES_ParseState_EOF extends TOGoS_TOGES_ParseState
{
	// I had a dream about skiing last night.
	// I think it was last night.
	// I remember stepping into a pond or something.
	// But I was wearing my wool sweater so the top of me stayed dry.
	// I had some other dream later.
	
	protected $eofSourceLocation;
	
	public function __construct(TOGoS_TOGES_ParserConfig $PC, array $eofSourceLocation) {
		parent::__construct($PC, function($ast) { throw new Exception("WTF"); });
		$this->eofSourceLocation = $eofSourceLocation;
	}
	
	public function _token( array $ti ) {
		throw new Exception(
			"Somehow got another token after EOF: ".TOGoS_TOGES_Parser::tokenInfoToString($ti)." at ".
			TOGoS_TOGVM_Util::sourceLocationToString($ti['sourceLocation'])."; EOF was at ".
			TOGoS_TOGVM_Util::sourceLocationToString($this->eofSourceLocation));
	}
}

class TOGoS_TOGES_ParserConfig
{
	public $operators;
	public $bracketsByOpenBracket = array();
	
	public function __construct( $config ) {
		$this->operators = $config['operators'];
		foreach( $config['operators'] as $b ) if(isset($b['openBracket'])) {
			$this->bracketsByOpenBracket[$b['openBracket']] = $b;
		}
	}
}

class TOGoS_TOGES_Parser
{
	protected $PC;
	protected $operators = array();
	protected $bracketsByOpenBracket = array();
	protected $bracketsByCloseBracket = array();
	protected $astCallback;
	
	public function __construct( array $config, callable $astCallback ) {
		$this->PC = new TOGoS_TOGES_ParserConfig($config);
		$this->operators = $config['operators'];
		$this->astCallback = $astCallback;
		// For now, assuming 1 file = 1 expression
		$fileBrackets = array('openBracket'=>'', 'closeBracket'=>'');
		$this->state = new TOGoS_TOGES_ParseState_Initial(
			$this->PC, $fileBrackets,
			function($ast, $eofTi) use ($fileBrackets) {
				if( $eofTi['type'] != self::TT_EOF ) {
					throw new Exception("Got something other than EOF as following token in outermost AST callback.");
				}
				call_user_func($this->astCallback, $ast);
				return new TOGoS_TOGES_ParseState_EOF($this->PC, $eofTi['sourceLocation']);
			}
		);
		foreach( $config['operators'] as $b ) if(isset($b['openBracket'])) {
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
			} else if( isset($this->operators[$token['value']]) ) {
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
	
	public static function mergeSourceLocations() {
		$merged = null;
		foreach( func_get_args() as $sl ) {
			if( $merged === null ) $merged = $sl;
			else {
				if( $sl['lineNumber'] < $merged['lineNumber'] or
				    $sl['lineNumber'] == $merged['lineNumber'] && $sl['columnNumber'] < $merged['columnNumber'] ) {
					$merged['lineNumber'] = $sl['lineNumber'];
					$merged['columnNumber'] = $sl['columnNumber'];
				}
				if( $sl['endLineNumber'] > $merged['endLineNumber'] or
					 $sl['endLineNumber'] == $merged['endLineNumber'] && $sl['endColumnNumber'] > $merged['endColumnNumber'] ) {
					$merged['lineNumber'] = $sl['lineNumber'];
					$merged['columnNumber'] = $sl['columnNumber'];
				}
			}
		}
		return $merged;
	}
	
	/** In-place version, no 's' */
	protected static function mergeSourceLocation( array &$into, array $append ) {
		$into = self::mergeSourceLocations($into, $append);
	}
		
	protected function _token( array $ti ) {
		$newState = $this->state->_token($ti);
		if( !($newState instanceof TOGoS_TOGES_ParseState) ) {
			throw new Exception(
				get_class($this->state)." returned something other than ".
				"a ParseState in response to token ".self::tokenInfoToString($ti)
			);
		}
		$this->state = $newState;
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
	
	public static function getDefaultOperators() {
		return EarthIT_JSON::decode(file_get_contents(__DIR__.'/operators.json'));
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
