default: run-unit-tests

.PHONY: \
	clean \
	default \
	run-unit-tests

clean:
	rm -rf vendor composer.lock

composer.lock:
	composer install

vendor/autoload.php: composer.lock
	composer install

run-unit-tests: vendor/autoload.php
	vendor/bin/phpunit --bootstrap vendor/autoload.php test
