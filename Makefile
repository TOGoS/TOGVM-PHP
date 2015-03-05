default: run-unit-tests

.PHONY: \
	clean \
	default \
	run-unit-tests \
	test-dependencies

clean:
	rm -rf vendor composer.lock

composer.lock: | composer.json
	composer update

vendor: composer.lock
	composer install
	touch "$@"

test-dependencies: vendor

run-unit-tests: test-dependencies
	vendor/bin/phpunit --bootstrap vendor/autoload.php test
