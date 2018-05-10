#! /bin/sh
set -e
hphpize
cmake .
make
./test/test.sh
