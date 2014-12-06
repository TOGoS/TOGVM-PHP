<?php

class TOGoS_TOGVM_Parser
{
	protected $closeBrackets = array(
		'(' => ')',
		'[' => ']',
		'{' => '}',
	);
	protected $openBrackets = array(
		')' => '(',
		']' => '[',
		'}' => '{',
	);
	
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
	
	protected static function _mergeSourceLocation( array $a, array $b ) {
		// For now this assumes that $b > $a, since
		// that's the order in which we receive tokens.
		// This function could be modified to check column/line numbers and
		// only expand the range if needed.
		if( $a === null ) return $b;
		$a['endLineNumber']   = $b['endLineNumber'];
		$a['endColumnNumber'] = $b['endColumnNumber'];
		return $a;
	}
	protected static function mergeSourceLocation( array &$into, array $append ) {
		$into = self::_mergeSourceLocation($into, $append);
	}
	
	const STATE_BLOCK_OPENED    = 0; // Block opened, expecting token
	const STATE_BLOCK           = 1; // Expecting block content AST
	const STATE_BLOCK_CLOSING   = 2; // Expecting end bracket
	const STATE_PHRASE          = 3; // Reading a phrase; additional words may follow
	const STATE_EXPRESSION_READ = 4; // Left-hand expression's been read; expecting infix operator or close block
	const STATE_INFIX_READ      = 5; // Infix operator's been read; expecting another expression
	const STATE_PREFIX_READ     = 6; // Prefix operator's been read
	const STATE_EOF             = 7; // End of file's been reached
	
	protected $state = array(
		'state' => self::STATE_BLOCK_OPENED,
		'sourceLocation' => null,
		//'words' => array(), // Words in phrase being read
		//'left' => null, // Left AST
		//'infixOperator' => null, // Name of infix operator being applied
		//'prefixOperator' => null, // Name of prefix operator being applied
		'precedence' => 0, // Any operators on right <= this precedence close the state
		'openBracket' => '', // Bracket that opened this state
		'parent' => null, // Parent state array whose 
	);
	
	const TT_WORD          = 'wrd';
	const TT_LITERAL       = 'lit';
	const TT_OPEN_BRACKET  = 'opn';
	const TT_CLOSE_BRACKET = 'cls';
	const TT_OPERATOR      = 'opr';
	const TT_EOF           = 'eof';
	
	protected function parseToken( array $token ) {
		switch( $token['quoting'] ) {
		case 'bare':
			if( isset($this->closeBrackets[$token['value']]) ) {
				return array(
					'type' => self::TT_OPEN_BRACKET,
					'openBracket' => $token['value'],
					'closeBracket' => $this->closeBrackets[$token['value']],
					'sourceLocation' => $token['sourceLocation']
				);			 
			} else if( isset($this->openBrackets[$token['value']]) ) {
				return array(
					'type' => self::TT_CLOSE_BRACKET,
					'closeBracket' => $token['value'],
					'openBracket' => $this->openBrackets[$token['value']],
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
	
	protected function utt( array $ti ) {
		throw new TOGoS_TOGVM_ParseError("Unexpected token type {$ti['type']} in parse state {$this->state['state']}", array($ti['sourceLocation']));
	}

	/**
	 * An AST's been parsed and the parent state has to handle it
	 * @param array $ast the AST
	 * @param array $ti the token following, if known
	 */
	protected function _ast( array $ast, array $ti=null ) {
		switch( $this->state['state'] ) {
		case self::STATE_BLOCK:
			if( $ti and $ti['type'] == self::TT_EOF || $ti['type'] == self::TT_CLOSE_BRACKET ) {
				// Someone found the end of the block!
				$this->state = array(
					'state' => self::STATE_BLOCK_CLOSING,
					'ast' => $ast,
					'openBracket' => $this->state['openBracket'],
					'sourceLocation' => self::_mergeSourceLocation($this->state['sourceLocation'], $ti['sourceLocation']),
					'parent' => $this->state['parent']					
				);
			} else {
				$this->state = array(
					'state' => self::STATE_EXPRESSION_READ,
					'ast' => $ast,
					'minPrecedence' => 1,
					'sourceLocation' => $ast['sourceLocation'],
					'parent' => $this->state
				);
			}
			break;
		default:
			throw new Exception("Don't know how to handle ast in state {$this->state['state']}");
		}
		
		if( $ti ) $this->_token($ti);
	}
	
	protected function _token( array $ti ) {
		if( $this->state['sourceLocation'] === null ) {
			$this->state['sourceLocation'] = $ti['sourceLocation'];
		}
		switch( $this->state['state'] ) {
		case self::STATE_BLOCK_OPENED:
			$this->state = array(
				'state' => self::STATE_BLOCK,
				'openBracket' => $this->state['openBracket'],
				'sourceLocation' => $this->state['sourceLocation'],
				'parent' => $this->state['parent']
			);
			switch( $ti['type'] ) {
			case self::TT_WORD:
				$this->state = array(
					'state' => self::STATE_PHRASE,
					'words' => array($ti['value']),
					'sourceLocation' => $ti['sourceLocation'],
					'parent' => $this->state
				);
				return;
			default: $this->utt($ti);
			}
		case self::STATE_BLOCK_CLOSING:
			if( $this->state['openBracket'] ) {
				if( $ti['type'] != self::TT_CLOSE_BRACKET or $ti['openBracket'] != $this->state['openBracket'] ) {
					throw new TOGoS_TOGVM_ParseError("Expected '{$this->state['closeBracket']}' but found  ".self::describeToken($ti), array($ti['sourceLocation']));
				}
			} else {
				if( $ti['type'] != self::TT_EOF ) {
					throw new TOGoS_TOGVM_ParseError("Expected end of file but found ".self::describeToken($ti), array($ti['sourceLocation']));
				}
				call_user_func( $this->astCallback, $this->state['ast'] );
				$this->state = array(
					'state' => self::STATE_EOF
				);
			}
			return;
		case self::STATE_PHRASE:
			switch( $ti['type'] ) {
			case self::TT_WORD:
				$this->state['words'][] = $ti['value'];
				self::mergeSourceLocation($this->state['sourceLocation'], $ti['sourceLocation']);
				return;
			case self::TT_OPERATOR:
				$ast = array(
					'type' => 'phrase',
					'words' => $this->state['words'],
					'sourceLocation' => $this->state['sourceLocation']
				);
				$this->state = $this->state['parent'];
				$this->_ast($ast, $ti);
				return;
			default: $this->utt($ti);
			}
		case self::STATE_EXPRESSION_READ:
			$ast = $this->state['ast'];
			
			switch( $ti['type'] ) {
			case self::TT_OPERATOR:
				$precedence = $this->infixOperators[$ti['name']]['precedence'];
				if( $precedence >= $this->state['minPrecedence'] ) {
					$this->state = array(
						'state' => self::STATE_INFIX_READ,
						'leftAst' => $ast,
						'operatorName' => $ti['name'],
						'minPrecedence' => $this->state['minPrecedence']+1,
						'sourceLocation' => self::_mergeSourceLocation($this->state['sourceLocation'], $ti['sourceLocation']),
						'parent' => $this->state['parent']
					);
					return;
				}
				// Otherwise fall through to handle the same as EOF or end bracket
				// and let a parent state handle it.
			case self::TT_EOF: case self::TT_CLOSE_BRACKET:
				$this->state = $this->state['parent'];
				$this->_ast($ast, $ti);
				return;
			default: $this->utt($ti);
			}
		case self::STATE_INFIX_READ:
			switch( $ti['type'] ) {
			case self::TT_EOF: case self::TT_CLOSE_BRACKET:
				if( empty($this->infixOperators[$this->state['operatorName']]['ignorable']) ) {
					throw new TOGoS_TOGVM_ParseError("Expected expression, but got ".self::describeTi($ti), array($ti['sourceLocation']));
				}
				$ast = $this->state['leftAst'];
				$this->state = $this->state['parent'];
				$this->_ast($ast, $ti);
				return;
			default: $this->utt($ti);
			}
		default: throw new Exception("Invalid parse state: {$this->state['state']}");
		}
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

	public static function getDefaultInfixOperators() {
		return EarthIT_JSON::decode(file_get_contents(__DIR__.'/infix-ops.json'));
	}
	
	public static function tokensToAst( array $tokens, array $sourceLocation, array $config ) {
		$C = new TOGoS_TOGVM_Thneed();
		$parser = new TOGoS_TOGVM_Parser($config, $C);
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
