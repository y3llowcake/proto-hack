#!/bin/bash
FILES=`find lib | grep \.php | grep -v \.swp | grep -v 'wellknowntype/.*_proto.php'`
FILES="$FILES test/test.php test/test_suite.php"
FILES="$FILES conformance/conformance.php"
for i in $FILES
do
  echo formatting $i
  hackfmt -i $i
done
