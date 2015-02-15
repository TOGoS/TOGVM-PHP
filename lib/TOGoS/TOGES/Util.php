<?php

class TOGoS_TOGES_Util
{
	public static function loadOperators($file) {
		return EarthIT_JSON::decode(file_get_contents($file));
	}
}
