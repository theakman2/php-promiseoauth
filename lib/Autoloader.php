<?php
class POA_Autoloader {
	
	public static function autoload($className) {
		$parts = explode("_",$className);
		if (
			($parts[0] === "POA")
			&& ($count = count($parts))
			&& ($count > 1)
		) {
			$baseName = array_pop($parts);
			if ($count > 2) {
				array_shift($parts);
				include __DIR__
					.DIRECTORY_SEPARATOR
					.implode(DIRECTORY_SEPARATOR,$parts)
					.DIRECTORY_SEPARATOR
					.$baseName
					.".php";
			} else {
				include __DIR__
					.DIRECTORY_SEPARATOR
					.$baseName
					.".php";
			}
		}
	}
	
	public function register() {
		spl_autoload_register(array("POA_Autoloader","autoload"));
	}
	
}