<?hh // strict
namespace conformance;

include '../lib/protobuf.php';
include '../lib/wellknowntype/any_proto.php';
include '../lib/wellknowntype/duration_proto.php';
include '../lib/wellknowntype/field_mask_proto.php';
include '../lib/wellknowntype/struct_proto.php';
include '../lib/wellknowntype/timestamp_proto.php';
include '../lib/wellknowntype/wrappers_proto.php';

include 'gen-src/google/protobuf/test_messages_proto2_proto.php';
include 'gen-src/google/protobuf/test_messages_proto3_proto.php';
include 'gen-src/third_party/google/protobuf/conformance/conformance_proto.php';

# https://github.com/google/protobuf/blob/master/conformance/conformance_test_runner.cc
# https://github.com/google/protobuf/blob/master/conformance/conformance.proto

function main(array<string> $argv): void {
  if (\count($argv) > 1) {
    echo "oneoff test mode\n";
    $in = $argv[1];
    echo 'escaped input: "'.$in.'"'."\n";
    $in = \stripcslashes($in);
    echo 'raw input: "'.$in.'"'."\n";

    $mode = "proto:proto";
    if ($argv[2] != "") {
      $mode = $argv[2];
    }

    $mt = "proto3";
    if ($argv[3] != "") {
      $mt = $argv[3];
    }

    echo "message type: $mt\n";
    echo "mode: $mode\n";

    $tm = new \protobuf_test_messages\proto3\TestAllTypesProto3();
    switch ($mt) {
      case 'proto3':
        break;
      case 'proto2':
        $tm = new \protobuf_test_messages\proto2\TestAllTypesProto2();
        break;
      default:
        die("unsupported message type $mt");
    }

    switch ($mode) {
      case 'proto:proto':
        $result =
          remarshal($tm, $in, WireFormat::PROTOBUF, WireFormat::PROTOBUF);
        $result = \addcslashes($result, $result);
        echo "output: \"$result\"\n";
        break;
      case 'proto:json':
        $result = remarshal($tm, $in, WireFormat::PROTOBUF, WireFormat::JSON);
        echo "output: '$result'\n";
        break;
      case 'json:proto':
        $result = remarshal($tm, $in, WireFormat::JSON, WireFormat::PROTOBUF);
        $result = \addcslashes($result, $result);
        echo "output: \"$result\"\n";
        break;
      case 'json:json':
        $result = remarshal($tm, $in, WireFormat::JSON, WireFormat::JSON);
        echo "output: '$result'\n";
        break;
      default:
        die("unsupported mode $mode");
    }
    \exit();
  } else {
    conformancePipe();
  }
}

function p(string $s): void {
  # Uncomment for debug output.
  # \fwrite(\STDERR, 'conformance.php: '.$s."\n");
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

  p('message_type: '.$creq->message_type);
  $tm = new \protobuf_test_messages\proto3\TestAllTypesProto3();
  switch ($creq->message_type) {
    case 'protobuf_test_messages.proto3.TestAllTypesProto3':
      break;
    case 'protobuf_test_messages.proto2.TestAllTypesProto2':
      $tm = new \protobuf_test_messages\proto2\TestAllTypesProto2();
      break;
    default:
      $cresp->result = new ConformanceResponse_skipped(
        "unsupported message type: ".$creq->message_type,
      );
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
          remarshal($tm, $payload, $wfi, $wfo),
        );
        break;
      case WireFormat::JSON:
        $cresp->result = new ConformanceResponse_json_payload(
          remarshal($tm, $payload, $wfi, $wfo),
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

function remarshal(
  \Protobuf\Message $tm,
  string $in,
  int $wfi,
  int $wfo,
): string {
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
