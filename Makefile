default: run-unit-tests

.PHONY: \
	clean \
	default \
	run-unit-tests

clean:
	rm -rf vendor composer.lock

composer.lock: | composer.json
	composer update

vendor: composer.lock
	composer install
	touch "$@"

run-unit-tests: vendor
	vendor/bin/phpunit --bootstrap vendor/autoload.php test
