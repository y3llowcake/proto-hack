#!/bin/bash
set -euo pipefail
hhvm -d hhvm.repo.central.path=/tmp/proto-gen-hack.repo -d log_errors=1 -d display_errors=1 -d error_log=/dev/stderr conformance/conformance.php "$@"
