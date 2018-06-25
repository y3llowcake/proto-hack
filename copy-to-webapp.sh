#!/bin/bash
set -e
DEST="/home/cy/sl/webapp1"
VERSION="`git config --get remote.origin.url`-`git rev-parse --abbrev-ref HEAD`-`git rev-parse HEAD`"
echo dest $DEST
echo version $VERSION
#go build -ldflags "-x" ./protoc-gen-hack 
GOOS="linux" go build -ldflags "-X main.version=$VERSION" -o $DEST/bin/arch/Linux-x86_64/protoc-gen-hack ./protoc-gen-hack
GOOS="darwin" go build -ldflags "-X main.version=$VERSION" -o $DEST/bin/arch/Darwin-x86_64/protoc-gen-hack ./protoc-gen-hack

for i in `find lib | grep \.php`
do
  cp $i $DEST/vendor/proto-hack
done
