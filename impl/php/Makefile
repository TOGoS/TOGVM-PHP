default: run-demo

.PHONY: run-demo

vendor/autoload.php: composer.json
	composer install

run-demo: vendor/autoload.php
	php bin/demo.php
