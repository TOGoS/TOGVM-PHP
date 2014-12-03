default: run-unit-tests

.PHONY: \
	clean \
	default \
	run-unit-tests

run-unit-tests:
	${MAKE} -C impl/php run-unit-tests

clean:
	${MAKE} -C impl/php clean
