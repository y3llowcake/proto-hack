#!/bin/bash
set -euo pipefail
#ls -R

echo "running conformance suite..."

CFR=`find -L -name conformance_test_runner`
CFR=`readlink $CFR`
echo runner path: $CFR


ARGS="--enforce_recommended"
if [ $# -gt 0 ]; then
  # test mode
  ARGS="$ARGS --failure_list conformance/failures.txt"
fi

$CFR $ARGS conformance_hhvm_harness.sh

if [ $# -gt 0 ]; then
else
  # not test mdoe
  cp failing_tests.txt ${BUILD_WORKSPACE_DIRECTORY}/conformance/failures.txt
fi
