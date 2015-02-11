<?php

/*
 * Token format: {
 *   'sourceText': (may not actually fill this in),
 *   'value': (text value of token, after parsing quotes),
 *   'quoting': 'bare|single|double', (logical quote type),
 *   'sourceLocation': { 'filename': ..., 'lineNumber': ..., 'columnNumber': ..., 'endLineNumber': ..., 'endColumnNumber': ... },
 * }
 */

class TOGoS_TOGES_Tokenizer
{
	const QUOTING_BARE   = 'bare';
	const QUOTING_SINGLE = 'single';
	const QUOTING_DOUBLE = 'double';
	
	const STATE_WHITESPACE = 0;
	const STATE_BAREWORD = 1;
	const STATE_QUOTE = 2;
	const STATE_CHAR_ESCAPE_OPENED = 5;
	const STATE_QUOTE_CLOSED = 6;
	const STATE_LINE_COMMENT = 7;
	const STATE_SKIP_WHITESPACE = 8;
	
	const BACKSLASH_DISALLOWED = 0;
	const BACKSLASH_ESCAPES    = 1;
	const BACKSLASH_LITERAL    = 2;
	
	protected $tokenCallback;
	
	protected $state = self::STATE_WHITESPACE;
	protected $parentState; // For states that can return to some other one
	protected $tokenLocation;
	protected $tokenValue = '';
	protected $filename = '(unnamed source)';
	protected $lineNumber = 1;
	protected $columnNumber = 1;
	
	protected $quoting;
	protected $openQuote;
	protected $closeQuote;
	protected $quoteDepth;
	protected $backslashBehavior;
	
	public function __construct($tokenCallback) {
		$this->tokenCallback = $tokenCallback;
	}
	
	public function setSourceLocation( $sourceLocation ) {
		$this->filename = $sourceLocation['filename'];
		$this->lineNumber = $sourceLocation['lineNumber'];
		$this->columnNumber = $sourceLocation['columnNumber'];
	}
	
	/* Returns source location of next character */
	protected function getSourceLocation() {
		return array(
			'filename' => $this->filename,
			'lineNumber' => $this->lineNumber,
			'columnNumber' => $this->columnNumber
		);
	}
	
	public static function isHorizontalWhitespace($c) {
		switch($c) {
		case " ": case "\r": case "\t": return true;
		default: return false;
		}
	}
	
	public static function isSelfDelimitingTokenChar($c) {
		// TODO: I suppose this could be configurable!
		switch($c) {
		case "\n":  case "\v": case "\f":
		case "(": case ")":
		case "[": case "]":
		case "{": case "}":
		case ",": case ";":
			return true;
		default:
			return false;
		}
	}
	
	protected function beginToken( $state, $quoting, $c='' ) {
		$this->state = $state;
		$this->tokenLocation = $this->getSourceLocation();
		$this->quoting = $quoting;
		$this->tokenValue = $c;
	}
	
	protected function beginQuoteToken( $openQuote, $closeQuote, $backslashBehavior, $quoting ) {
		$this->quoteDepth = 1;
		$this->openQuote = $openQuote;
		$this->closeQuote = $closeQuote;
		$this->backslashBehavior = $backslashBehavior;
		$this->beginToken( self::STATE_QUOTE, $quoting );
	}

	protected function checkSelfDelimitingTokenChar($c) {
		if( self::isSelfDelimitingTokenChar($c) ) {
			$this->flush();
			$this->state = self::STATE_BAREWORD;
			$this->quoting = self::QUOTING_BARE;
			$this->tokenLocation = $this->getSourceLocation();
			$this->tokenValue = $c;
			$this->flush(self::STATE_WHITESPACE, true);
			return true;
		}
		return false;
	}

	// Valid during char($c) calls
	protected $nextLineNumber;
	protected $nextColumnNumber;
	
	protected function getCharSourceLocation() {
		return array(
			'filename' => $this->filename,
			'lineNumber' => $this->lineNumber,
			'columnNumber' => $this->columnNumber,
			'endLineNumber' => $this->nextLineNumber,
			'endColumnNumber' => $this->nextColumnNumber
		);
	}

	/**
	 * @param $c string UTF-8-encoded single-character string
	 */
	protected function _char($c) {
		switch( $this->state ) {
		case self::STATE_WHITESPACE:
			if( $this->checkSelfDelimitingTokenChar($c) ) return;
			if( self::isHorizontalWhitespace($c) ) return;
			switch( $c ) {
			case "'":
				$this->beginQuoteToken( $c, $c, self::BACKSLASH_ESCAPES, self::QUOTING_SINGLE );
				return;
			case '"':
				$this->beginQuoteToken( $c, $c, self::BACKSLASH_ESCAPES, self::QUOTING_DOUBLE );
				return;
			case '‘':
				$this->beginQuoteToken( $c, '’', self::BACKSLASH_ESCAPES, self::QUOTING_SINGLE );
				return;
			case '“':
				$this->beginQuoteToken( $c, '”', self::BACKSLASH_ESCAPES, self::QUOTING_DOUBLE );
				return;
			case '‹':
				$this->beginQuoteToken( $c, '›', self::BACKSLASH_LITERAL, self::QUOTING_SINGLE );
				return;
			case '«':
				$this->beginQuoteToken( $c, '»', self::BACKSLASH_LITERAL, self::QUOTING_DOUBLE );
				return;
			case '#':
				$this->parentState = $this->state;
				$this->state = self::STATE_LINE_COMMENT;
				return;
			case '\\':
				$this->state = self::STATE_SKIP_WHITESPACE;
				return;
			default:
				// I guess everything else is a valid bareword character?
				$this->beginToken(self::STATE_BAREWORD, self::QUOTING_BARE, $c);
				return;
			}
		case self::STATE_BAREWORD:
			if( $this->checkSelfDelimitingTokenChar($c) ) return;
			if( self::isHorizontalWhitespace($c) ) {
				$this->flush();
				return;
			}
			
			switch($c) {
			case '"': case '"':
			case '‘': case '’': case '“': case '”':
			case '‹': case '›': case '«': case '»':
				throw new TOGoS_TOGCM_ParseError("Malplaced quote", array($this->getCharSourceLocation()));
			case '\\':
				throw new TOGoS_TOGCM_ParseError("Malplaced backslash", array($this->getCharSourceLocation()));
			}
			
			$this->tokenValue .= $c;
			return;
		case self::STATE_QUOTE:
			if( $c == $this->openQuote and $this->openQuote != $this->closeQuote ) {
				++$this->quoteDepth;
				$this->tokenValue .= $c;
			} else if( $c == $this->closeQuote ) {
				if( --$this->quoteDepth == 0 ) {
					$this->flush(self::STATE_QUOTE_CLOSED, true);
				} else {
					$this->tokenValue .= $c;
				}
			} else if( $c == '\\' ) {
				switch( $this->backslashBehavior ) {
				case self::BACKSLASH_ESCAPES:
					$this->parentState = $this->state;
					$this->state = self::STATE_CHAR_ESCAPE_OPENED;
					break;
				case self::BACKSLASH_LITERAL:
					$this->tokenValue .= $c;
					break;
				case self::BACKSLASH_DISALLOWED:
					throw new TOGoS_TOGVM_ParseError("Backslash not allowed", array($this->getSourceLocation()));
				default:
					throw new Exception("Invalid backslash mode: {$this->backslashBehavior}");
				}
			} else {
				$this->tokenValue .= $c;
			}
			return;
		case self::STATE_CHAR_ESCAPE_OPENED:
			switch( $c ) {
			case '"': case "'": case '\\':
			case '‹': case '›';
			case '«': case '»';
				break;
			case 'a': $c = "\x07"; break;
			case 'b': $c = "\x08"; break;
			case 'f': $c = "\x0C"; break;
			case 'n': $c = "\x0A"; break;
			case 'r': $c = "\x0D"; break;
			case 't': $c = "\x09"; break;
			case 'v': $c = "\x0B"; break;
			case 'u': case 'U':
				throw new Exception("\\{$c} escape sequences not supported");
			default:
				throw new TOGoS_TOGVM_ParseError("Illegal escape sequence \"\\{$c}\"", array($this->getSourceLocation()));
			}
			$this->tokenValue .= $c;
			$this->state = $this->parentState;
			return;
		case self::STATE_LINE_COMMENT:
			if( $c == "\n" ) {
				$this->state = $this->parentState;
				$this->_char($c);
			}
			return;
		case self::STATE_QUOTE_CLOSED:
			if( self::isHorizontalWhitespace($c) ) {
				$this->state = self::STATE_WHITESPACE;
				return;
			}
			if( $this->checkSelfDelimitingTokenChar($c) ) return;
			throw new TOGoS_TOGVM_ParseError("Illegal bareword character '$c'", array($this->getSourceLocation()));
		default:
			throw new Exception("Tokenizer state ".$this->state." character handling not yet implemented");
		}
	}

	public function char($c) {
		if( $c == "\n" ) {
			$this->nextLineNumber = $this->lineNumber+1;
			$this->nextColumnNumber = 1;
		} else {
			$this->nextLineNumber = $this->lineNumber;
			$this->nextColumnNumber = $this->columnNumber + 1;
			if( $c == "\t" ) {
				while( ($this->nextColumnNumber - 1) % 8 != 0 ) ++$this->nextColumnNumber;
			}
		}
		
		$this->_char($c);
		
		$this->lineNumber = $this->nextLineNumber;
		$this->columnNumber = $this->nextColumnNumber;
	}
	
	protected $charByteCount = null;
	protected $charBuffer = '';
	public function byte($b) {
		$ord = ord($b);
		if( ($ord & 0b10000000) == 0 ) {
			if( $this->charByteCount !== null ) {
				throw new TOGoS_TOGVM_ParseError("Illegal byte $ord in {$this->charByteCount}-byte UTF-8 sequence", array($this->getSourceLocation()));
			}
			$this->char($b);
			return;
		}
		
		if( ($ord & 0b11000000) == 0b11000000 ) {
			// It's a prefix one
			if( $this->charByteCount !== null ) {
				throw new TOGoS_TOGVM_ParseError("Illegal byte $ord in {$this->charByteCount}-byte UTF-8 sequence", array($this->getSourceLocation()));
			}
			$this->charBuffer .= $b;
			if( ($ord & 0b11111100) == 0b11111100 ) {
				$this->charByteCount = 6;
			} else if( ($ord & 0b11111000) == 0b11111000 ) {
				$this->charByteCount = 5;
			} else if( ($ord & 0b11110000) == 0b11110000 ) {
				$this->charByteCount = 4;
			} else if( ($ord & 0b11100000) == 0b11100000 ) {
				$this->charByteCount = 3;
			} else {
				$this->charByteCount = 2;
			}
			return;
		}
		
		if( $this->charByteCount === null ) {
			throw new TOGoS_TOGVM_ParseError("Illegal UTF-8 sequence byte $ord", array($this->getSourceLocation()));
		}
		$this->charBuffer .= $b;
		if( strlen($this->charBuffer) == $this->charByteCount ) {
			$this->char($this->charBuffer);
			$this->charBuffer = '';
			$this->charByteCount = null;
		}
	}
	
	public function string($string) {
		for( $i=0; $i<strlen($string); ++$i ) {
			$this->byte($string[$i]);
		}
	}
	
	public function flush( $newState=self::STATE_WHITESPACE, $breakAfter=false ) {
		$this->tokenLocation['endLineNumber']   = $breakAfter ? $this->nextLineNumber   : $this->lineNumber;
		$this->tokenLocation['endColumnNumber'] = $breakAfter ? $this->nextColumnNumber : $this->columnNumber;
		
		switch( $this->state ) {
		case self::STATE_BAREWORD:
		case self::STATE_QUOTE:
			$token = array(
				'value' => $this->tokenValue,
				'quoting' => $this->quoting,
				'sourceLocation' => $this->tokenLocation
			);
			break;
		case self::STATE_WHITESPACE:
		case self::STATE_QUOTE_CLOSED:
			$token = null;
			break;
		default:
			throw new Exception("State {$this->state} unaccounted for by flush");
		}
		
		if( $token !== null ) call_user_func($this->tokenCallback, $token);
		
		$this->state = $newState;
	}
	
	public static function tokenize($string, &$sourceLocation) {
		$C = new TOGoS_TOGVM_Thneed();
		$T = new TOGoS_TOGES_Tokenizer($C);
		$T->setSourceLocation($sourceLocation);
		$T->string($string);
		$T->flush();
		$endSourceLocation = $T->getSourceLocation();
		$sourceLocation['endLineNumber'] = $endSourceLocation['lineNumber'];
		$sourceLocation['endColumnNumber'] = $endSourceLocation['columnNumber'];
		return $C->getElements();
	}
}
