#!/bin/sh
set -e
hhvm -d log_errors=1 -d display_errors=1 -d error_log=/dev/stderr conformance/conformance.php $@
