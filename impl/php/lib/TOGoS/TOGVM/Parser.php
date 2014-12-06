<?php

class TOGoS_TOGVM_Parser
{
	protected $closeBrackets = array(
		'(' => ')',
		'[' => ']',
		'{' => '}',
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
	
	protected static function mergeSourceLocation( array &$into, array $append ) {
		// For now this assumes that $append comes after $into, since
		// that's the order in which we receive tokens.
		// This function could be modified to check column/line numbers and
		// only expand the range if needed.
		if( $into === null ) {
			$into = $append;
		} else {
			$into['endLineNumber'] = $append['endLineNumber'];
			$into['endColumnNumber'] = $append['endColumnNumber'];
		}
	}
	
	const STATE_BLOCK_OPENED    = 0; // Block opened
	const STATE_BLOCK           = 1; // Reading content of block
	const STATE_PHRASE          = 2; // Reading a phrase; additional words may follow
	const STATE_EXPRESSION_READ = 3; // Left-hand expression's been read; expecting infix operator or close block
	const STATE_INFIX_READ      = 4; // Infix operator's been read; expecting another expression
	const STATE_PREFIX_READ     = 5; // Prefix operator's been read
	const STATE_EOF             = 6; // End of file's been reached
	
	protected $state = array(
		'state' => self::STATE_BLOCK_OPENED,
		'sourceLocation' => null,
		//'words' => array(), // Words in phrase being read
		//'left' => null, // Left AST
		//'infixOperator' => null, // Name of infix operator being applied
		//'prefixOperator' => null, // Name of prefix operator being applied
		'precedence' => 0, // Any operators on right <= this precedence close the state
		//'openBracket' => '', // Bracket that opened this state
		'closeBracket' => '', // Bracket allowed to close a block, e.g. ")"; "" at top-level
		'parent' => null, // Parent state array whose 
	);
	
	const DISP_ATOM                 =  1;
	const DISP_WORD                 =  2;
	const DISP_ATOM_ILLEGAL         =  9;
	const DISP_OPEN_BRACKET         = 11;
	const DISP_OPEN_BRACKET_ILLEGAL = 19;
	const DISP_END_BRACKET          = 21;
	const DISP_END_BRACKET_ILLEGAL  = 29;
	const DISP_INFIX_TIGHTER        = 31;
	const DISP_INFIX_LOOSER         = 32;
	const DISP_INFIX_ILLEGAL        = 39;
	const DISP_PREFIX               = 41;
	const DISP_PREFIX_ILLEGAL       = 49;
	
	protected function tokenDisposition( array $token, &$closeBracket=null ) {
		switch( $this->state['state'] ) {
		case self::STATE_BLOCK_OPENED:
			switch( $token['quoting'] ) {
			case 'bare':
				if( isset($this->closeBrackets[$token['value']]) ) {
					$closeBracket = $this->closeBrackets[$token['value']];
					return self::DISP_OPEN_BRACKET;
				} else if( isset($this->prefixOperators[$token['value']]) ) {
					return self::DISP_PREFIX;
				} else if( isset($this->infixOperators[$token['value']]) ) {
					return self::DISP_INFIX_ILLEGAL;
				} else {
					return self::DISP_WORD;
				}
			case 'single': return self::DISP_WORD;
			case 'double': return self::DISP_ATOM;
			default: throw new Exception("Unhandled token quoting: '{$token['quoting']}");
			}
		case self::STATE_PHRASE:
			switch( $token['quoting'] ) {
			case 'bare':
				if( isset($this->bracketPairs[$token['value']]) ) {
					return self::DISP_OPEN_BRACKET;
				} else if( isset($this->prefixOperators[$token['value']]) ) {
					return self::DISP_PREFIX_ILLEGAL;
				} else if( isset($this->infixOperators[$token['value']]) ) {
					$infixOperator = $this->infixOperators[$token['value']];
					return $infixOperator['precedence'] > $this->state['precedence'] ?
						self::DISP_INFIX_TIGHTER : self::SELF_INFIX_LOOSER;
				} else {
					return self::DISP_WORD;
				}
			case 'single': return self::DISP_WORD;
			case 'double': return self::DISP_ATOM_ILLEGAL;
			default: throw new Exception("Unhandled token quoting: '{$token['quoting']}");
			}
		default: throw new Exception("Unhandled parser state: {$this->state['state']}");
		}
	}
	
	protected function openBlock($closeBracket) {
		// ...
	}
	
	protected function _closeState( $childAst=null ) {
		switch( $this->state['state'] ) {
		case self::STATE_PHRASE:
			return array(
				'type' => 'symbol',
				'words' => $this->state['words']
			);
			/*
		case self::STATE_BLOCK_OPENED:
			return array(
							 'type' => 'operation',
							 '
			);
		case self::STATE_BLOCK:
			return $childAst;
			*/
		default: throw new Exception("Don't know how to close state #{$this->state['state']}");
		}
	}
	
	protected function closeState( $childAst=null ) {
		$ast = $this->_closeState($childAst);
		$this->state = $this->state['parent'];
		return $ast;
	}
	
	protected function closeBlock($closeBracket) {
		while( $this->state['state'] != self::STATE_BLOCK ) {
			$ast = $this->closeState();
		}
		if( $this->state['closeBracket'] != $closeBracket ) {
			$gotText = $closeBracket ? "got '$closeBracket'" : 'reached end of file';
			throw new TOGoS_TOGVM_ParseError("Expected '{$this->state['closeBracket']}' but $gotText");
		}
		$ast = array(
			'type' => 'operation',
			'operator' => array(
				'type' => 'symbol',
				'words' => array($this->state['openBracket'].$this->State['closeBracket'])
			),
			'operands' => array($ast)
		);
		$this->state = $this->state['parent'];
		return $ast;
	}
	
	public function token( array $token ) {
		if( $this->state['sourceLocation'] === null ) {
			$this->state['sourceLocation'] = $token['sourceLocation'];
		}
		
		$closeBracket = null;
		$disp = $this->tokenDisposition($token, $closeBracket);
		
		switch( $disp ) {
		case self::DISP_WORD:
			if( $this->state['state'] == self::STATE_PHRASE ) {
				$this->state['words'][] = $token['value'];
				self::mergeSourceLocation($this->state['sourceLocation'], $token['sourceLocation']);
			} else {
				$this->state = array(
					'state' => self::STATE_PHRASE,
					'sourceLocation' => $token['sourceLocation'],
					'words' => array($token['value']),
					'parent' => $this->state
				);
			}
			return;
		default:
			throw new Exception("Unhandled token disposition: $disp");
		}
	}
	
	/** Indicate that the end of the input file has been reached. */
	public function eof( array $sourceLocation ) {
		print_r($this->state);
		call_user_func($this->astCallback, $this->closeBlock(''));
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
