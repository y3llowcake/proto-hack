<?hh // partial
namespace conformance;

include './conformance_strict.php';

\set_error_handler(
  function($errno, $errstr, $errfile, $errline, $errcontext): bool {
    p(sprintf("ERROR: %s", $errstr));
    return true;
  },
);

main($argv);
