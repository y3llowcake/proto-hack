#!/bin/bash
set -euo pipefail
#ls -R

#
# Setup a tmp directory
#
TMP=`mktemp -d /tmp/webapp-protobuf-gen.XXXXXXXXXX`
SUCCESS=0
function fin() {
  rm -rf $TMP
  echo
  if [ "$SUCCESS" -eq "1" ]; then
    echo -e "\033[1;32mSUCCESS\033[0m"
  else
    echo -e "\033[1;31mFAILURE\033[0m"
  fi
}
trap fin EXIT



GENHACK=`find -L -name protoc-gen-hack`
GENHACK=`readlink $GENHACK`
echo genhack path: $GENHACK
$GENHACK --version

PROTOC=`find -L external -name protoc`
PROTOC=`readlink $PROTOC`
echo protoc path: $PROTOC
$PROTOC --version

PBS=`find . -name '*.proto' | grep -v _virtual_imports`

echo
echo generating hacklang...
for SRC in $PBS
do
  echo source: $SRC
  ARGS="-I external/com_google_protobuf/src -I ./"
  $PROTOC $ARGS --plugin=$GENHACK --hack_out="plugin=grpc,allow_proto2_dangerous:$TMP" $SRC
  echo
done

protoc --encode=foo.bar.example1  ./test/example1.proto < ./test/example1.pb.txt > $TMP/test/example1.pb.bin

if [ $# -gt 0 ]; then
  # Comparison mode; see if there are diffs, if none, exit 0.
  echo
  echo "comparing outputs with destination"
  diff -r $TMP ./generated
else
  DST="${BUILD_WORKSPACE_DIRECTORY}/generated"
  echo
  echo "copying outputs to destination"
  rm -r $DST
  cp -r $TMP $DST
fi

SUCCESS=1
