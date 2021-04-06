#!/bin/bash
FILES=`find . | grep \.php | grep -v \.swp | grep -v 'wellknowntype/.*'`
for i in $FILES
do
  echo formatting $i
  hackfmt -i $i
done
