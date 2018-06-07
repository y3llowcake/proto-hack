#!/bin/bash
for i in `find lib | grep \.php`
do
  echo formatting $i
  hackfmt -i $i
done
