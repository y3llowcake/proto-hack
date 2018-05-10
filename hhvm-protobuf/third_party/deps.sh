#!/bin/bash
set -e
cd "$(dirname "$0")"
if [ -e ./protobuf ]
then
  echo "found local protobuf directory, skipping install"
  exit 0
fi
VER="3.5.1"
echo installing protobuf version $VER
wget https://github.com/google/protobuf/releases/download/v3.5.1/protobuf-cpp-$VER.tar.gz
tar xzpf protobuf-cpp-$VER.tar.gz
ln -s ./protobuf-$VER ./protobuf
cd protobuf-$VER
./configure
make -j8
