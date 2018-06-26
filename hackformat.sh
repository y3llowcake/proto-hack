#!/bin/bash
for i in `find lib | grep \.php | grep -v \.swp`
do
  echo formatting $i
  hackfmt -i $i
done
