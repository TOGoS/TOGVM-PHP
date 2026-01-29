# TOGVM-PHP

PHP library for parsing a customizable language into [TOGVM](https://github.com/TOGoS/TOGVM-Spec/) data structures.

There's also an interpreter, in case you want to evaluate expressions directly
rather than, like, compiling them to SQL or COBOL or whatever.

## PHP Version

PHP 5.4 is required primarily for support for `$this` in closures.
Otherwise this could be PHP 5.2-compatible.

## Test Vectors

While developing the test vectors, you can make a symlink to the test
vector directory from the root TOGVM-PHP directory, e.g.

  ln -s ../TOGVM-Spec/test-vectors ./test-vectors

If that's not present, the unit tests will look for
vendor/togos/togvm-spec/test-vectors.
