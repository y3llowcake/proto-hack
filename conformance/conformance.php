<?hh // partial

ini_set("display_errors", "stderr");
set_error_handler(
  function($errno, $errstr, $errfile, $errline, $errcontext): bool {
    p(sprintf("ERROR: %s", $errstr));
    return true;
  },
);

include '../lib/protobuf.php';
include 'gen-src/google/protobuf/any_proto.php';
include 'gen-src/google/protobuf/duration_proto.php';
include 'gen-src/google/protobuf/field_mask_proto.php';
include 'gen-src/google/protobuf/struct_proto.php';
include 'gen-src/google/protobuf/test_messages_proto3_proto.php';
include 'gen-src/google/protobuf/timestamp_proto.php';
include 'gen-src/google/protobuf/wrappers_proto.php';
include
  'gen-src/third_party/google/protobuf/conformance/conformance_proto.php'
;

use conformance\ConformanceRequest;
use conformance\ConformanceRequest_payload;
use conformance\WireFormat;
use conformance\ConformanceResponse;
use protobuf_test_messages\proto3\TestAllTypesProto3;

conformancePipe();

# https://github.com/google/protobuf/blob/master/conformance/conformance_test_runner.cc
# https://github.com/google/protobuf/blob/master/conformance/conformance.proto

function p(string $s): void {
  fwrite(STDERR, 'conformance.php: '.$s."\n");
}

function conformancePipe(): void {
  $in = fopen('php://stdin', 'r');
  while (true) {
    $lens = fread($in, 4);
    if (feof($in)) {
      return;
    }
    $len = unpack('l', $lens)[1];
    p("reading: $len");
    $payload = fread($in, $len);
    $result = conformanceRaw($payload);
    p('writing: '.strlen($result));
    echo pack('l', strlen($result)).$result;
  }
  p('fin');
}

function conformanceRaw(string $raw): string {
  $creq = new ConformanceRequest();
  Protobuf\Unmarshal($raw, $creq);
  return Protobuf\Marshal(conformance($creq));
}

function conformance(ConformanceRequest $creq): ConformanceResponse {
  $cresp = new ConformanceResponse();
  if ($creq->oneof_payload() !=
      ConformanceRequest_payload::protobuf_payload) {
    $cresp->skipped = "unsupported payload type";
    return $cresp;
  }
  if ($creq->requested_output_format != WireFormat::PROTOBUF) {
    $cresp->skipped = "unsupported output type";
    return $cresp;
  }
  $tm = new TestAllTypesProto3();
  try {
    Protobuf\Unmarshal($creq->protobuf_payload, $tm);
  } catch (Exception $e) {
    p('parse error: '.$e->getMessage());
    $cresp->parse_error = $e->getMessage();
    return $cresp;
  }
  $cresp->protobuf_payload = Protobuf\Marshal($tm);
  return $cresp;
}
