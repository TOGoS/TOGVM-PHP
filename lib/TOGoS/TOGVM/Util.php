<?php

class TOGoS_TOGVM_Util
{
	public static function sourceLocationToString(array $sl) {
		return $sl['filename'].';'.$sl['lineNumber'].','.$sl['columnNumber'];
	}
}
