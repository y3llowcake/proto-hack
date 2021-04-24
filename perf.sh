#!/bin/bash
set -xeuo pipefail
PID=`pgrep -f 'hhvm.*test.php' | head -n 1`
echo found pid $PID
PIDMAP="/tmp/perf-${PID}.map"
echo checking for symbols...
cat /tmp/perf-$PID.map | grep 'test_suite.php::bench'
echo recording...
sudo perf record -g -p $PID -- sleep 20
sudo perf report -f
