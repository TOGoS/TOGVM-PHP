PHP 5.4 is required primarily for:
- Support for $this in closures

Since it was required anyway, other I went ahead and used 5.4
features, even though normally I try to keep code 5.2-compatible.

## Test Vectors

While developing the test vectors, you can make a symlink to the test
vector directory from the root TOGVM-PHP directory, e.g.

  ln -s ../TOGVM-Spec/test-vectors ./test-vectors

If that's not present, the unit tests will look for
vendor/togos/togvm-spec/test-vectors.
