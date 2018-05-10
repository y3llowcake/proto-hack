#! /bin/sh
set -e
cd "$(dirname "$0")"
EDIR=`dirname $PWD`
hhvm -d extension_dir=$EDIR -d hhvm.extensions[]=protobuf.so test.php
