#!/bin/bash
set -euo pipefail
#ls -R

echo "running conformance suite..."

CFR=`find -L -name conformance_test_runner`
CFR=`readlink $CFR`
echo runner path: $CFR

$CFR --enforce_recommended --failure_list conformance/failures.txt conformance.sh
