#!/bin/bash
set -xeuo pipefail
echo running benchmark harness...
if [ $# -ge 1 ]; then
  echo with repo auth
  TMP=`mktemp -d`
  hhvm --hphp -t hhbc --module=generated --module=lib --module=test --output-dir $TMP
  hhvm -d hhvm.repo.authoritative=true -d hhvm.repo.central.path=$TMP/hhvm.hhbc test/test.php bench
else
  echo without repo auth
  hhvm -d hhvm.perf_pid_map=1 test/test.php bench
fi
