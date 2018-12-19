#!/bin/bash
for i in `find lib | grep \.php | grep -v \.swp | grep -v 'wellknowntype/.*_proto.php'`
do
  echo formatting $i
  hackfmt -i $i
done
