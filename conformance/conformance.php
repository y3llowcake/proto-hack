<?hh // partial
namespace conformance;

\set_error_handler(
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
include 'gen-src/third_party/google/protobuf/conformance/conformance_proto.php';

main($argv);

# https://github.com/google/protobuf/blob/master/conformance/conformance_test_runner.cc
# https://github.com/google/protobuf/blob/master/conformance/conformance.proto

function main(array<string> $argv): void {
  if (\count($argv) > 1) {
    echo "oneoff test mode\n";
    $in = $argv[1];
    echo 'raw input: "'.$in.'"'."\n";
    $in = \stripcslashes($in);
    if ($argv[2] != 'json') {
      $result = testMessageRaw($in, WireFormat::PROTOBUF, WireFormat::PROTOBUF);
      $result = \addcslashes($result, $result);
      echo "output: \"$result\"\n";
    } else {
      $result = testMessageRaw($in, WireFormat::PROTOBUF, WireFormat::JSON);
      echo "output: '$result'\n";
    }
    \exit();
  } else {
    conformancePipe();
  }
}

function p(string $s): void {
  # Uncomment for debug output.
  # fwrite(STDERR, 'conformance.php: '.$s."\n");
}

function conformancePipe(): void {
  $in = \fopen('php://stdin', 'r');
  while (true) {
    $lens = \fread($in, 4);
    if (\feof($in)) {
      return;
    }
    $len = \unpack('l', $lens)[1];
    p("reading: $len");
    $payload = \fread($in, $len);
    $result = conformanceRaw($payload);
    p('writing: '.\strlen($result));
    echo \pack('l', \strlen($result)).$result;
  }
  p('fin');
}

function conformanceRaw(string $raw): string {
  $creq = new ConformanceRequest();
  \Protobuf\Unmarshal($raw, $creq);
  return \Protobuf\Marshal(conformance($creq));
}

function conformance(ConformanceRequest $creq): ConformanceResponse {
  $cresp = new ConformanceResponse();
  if (
    $creq->payload->WhichOneof() != ConformanceRequest_payload::protobuf_payload
  ) {
    $cresp->result =
      new ConformanceResponse_skipped("unsupported payload type");
    return $cresp;
	}
	$payload = "";
	$wfi = -1;
  if ($creq->payload instanceof ConformanceRequest_protobuf_payload) {
		$payload = $creq->payload->protobuf_payload;
		$wfi = WireFormat::PROTOBUF;
	} else if ($creq->payload instanceof ConformanceRequest_json_payload) {
		$payload = $creq->payload->json_payload;
		$wfi = WireFormat::JSON;
	}
  $wfo = $creq->requested_output_format;
  try {
    switch ($wfo) {
      case WireFormat::PROTOBUF:
        $cresp->result = new ConformanceResponse_protobuf_payload(
          testMessageRaw($payload, $wfi, $wfo),
        );
        break;
      case WireFormat::JSON:
        $cresp->result = new ConformanceResponse_json_payload(
          testMessageRaw($payload, $wfi, $wfo),
        );
        break;
      default:
        $cresp->result =
          new ConformanceResponse_skipped("unsupported output type");
    }
  } catch (\Exception $e) {
    p('parse error: '.$e->getMessage());
    $cresp->result = new ConformanceResponse_parse_error($e->getMessage());
  }
  p("response: ".\print_r($cresp, true));
  return $cresp;
}

function testMessageRaw(string $in, int $wfi, int $wfo): string {
  $tm = new \protobuf_test_messages\proto3\TestAllTypesProto3();
	switch ($wfi) {
    case WireFormat::PROTOBUF:
			\Protobuf\Unmarshal($in, $tm);
			break;
    case WireFormat::JSON:
			\Protobuf\UnmarshalJson($in, $tm);
			break;
		default:
			throw new \Exception('wtf');
  }
  p("remarshaling: ".\print_r($tm, true));
  switch ($wfo) {
    case WireFormat::PROTOBUF:
      return \Protobuf\Marshal($tm);
    case WireFormat::JSON:
      return \Protobuf\MarshalJson($tm);
  }
  throw new \Exception("invalid output wire format: $wfo");
}
