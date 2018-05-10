#! /bin/sh
set -e
./third_party/deps.sh
hphpize
cmake .
make
./test/test.sh
