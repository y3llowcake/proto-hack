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
  $CFR --enforce_recommended --failure_list conformance/failures.txt conformance_hhvm_harness.sh
else
  $CFR --enforce_recommended conformance_hhvm_harness.sh || true
  cp failing_tests.txt ${BUILD_WORKSPACE_DIRECTORY}/conformance/failures.txt
fi

