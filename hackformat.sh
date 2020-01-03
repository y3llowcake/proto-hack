#!/bin/bash
FILES=`find lib | grep \.php | grep -v \.swp | grep -v 'wellknowntype/.*_proto.php'`
FILES="$FILES test/test.php"
FILES="$FILES conformance/conformance_strict.php"
for i in $FILES
do
  echo formatting $i
  hackfmt -i $i
done
