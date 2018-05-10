#! /bin/sh
set -e
hphpize
#export PROTOBUF_INCLUDE_DIR=/home/cy/co/protobuf/src/.libs
#export PROTOBUF_LIBRARY=/home/cy/co/protobuf/src
cmake .
make
./test/test.sh
