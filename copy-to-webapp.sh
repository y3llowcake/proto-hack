#!/bin/bash
set -e
DEST="/home/cy/sl/webapp1"
for i in `find lib | grep \.php`
do
  cp $i $DEST/vendor/proto-hack
done
