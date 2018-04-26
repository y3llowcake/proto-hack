#!/bin/sh
set -e
hhvm -d hhvm.log.use_log_file=true -d hhvm.log.file=./error.log conformance.php
