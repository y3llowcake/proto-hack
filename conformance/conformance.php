<?hh // strict
namespace conformance;


# https://github.com/google/protobuf/blob/master/conformance/conformance_test_runner.cc
# https://github.com/google/protobuf/blob/master/conformance/conformance.proto

<<__EntryPoint>>
function main(): void {
  \set_error_handler(
    function($errno, $errstr, $errfile, $errline, $errcontext): bool {
      p(\sprintf("ERROR: %s", $errstr));
      return true;
    },
  );

  require 'lib/errors.php';
  require 'lib/protobuf.php';
  require 'generated/google/protobuf/any_proto.php';
  require 'generated/google/protobuf/duration_proto.php';
  require 'generated/google/protobuf/field_mask_proto.php';
  require 'generated/google/protobuf/struct_proto.php';
  require 'generated/google/protobuf/timestamp_proto.php';
  require 'generated/google/protobuf/wrappers_proto.php';

  require 'generated/google/protobuf/test_messages_proto2_proto.php';
  require 'generated/google/protobuf/test_messages_proto3_proto.php';
  require
  'generated/external/com_google_protobuf/conformance/conformance_proto.php';

  $av = \HH\global_get('argv') as KeyedContainer<_, _>;
  $argv = vec[];
  foreach ($av as $arg) {
    $argv[] = (string)$arg;
  }
  # throw new \Exception(\print_r($argv, true));

  if (\count($argv) > 1 && $argv[1] != "conformance_hhvm_harness.sh") {
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

    $wfi = WireFormat::PROTOBUF;
    $wfo = WireFormat::PROTOBUF;
    switch ($mode) {
      case 'proto:proto':
        break;
      case 'proto:json':
        $wfo = WireFormat::JSON;
        break;
      case 'json:proto':
        $wfi = WireFormat::JSON;
        break;
      case 'json:json':
        $wfo = WireFormat::JSON;
        $wfi = WireFormat::JSON;
        break;
      default:
        die("unsupported mode $mode");
    }
    $result = remarshal($tm, $in, $wfi, $wfo)->MustValue();
    if ($wfo === WireFormat::PROTOBUF) {
      $result = \addcslashes($result, $result);
    }
    echo "output: \"$result\"\n";
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
  $err = \Protobuf\Unmarshal($raw, $creq);
  if (!$err->Ok()) {
    throw new \Exception($err->Error());
  }
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
  if ($creq->payload is ConformanceRequest_protobuf_payload) {
    $payload = $creq->payload->protobuf_payload;
    $wfi = WireFormat::PROTOBUF;
  } else if ($creq->payload is ConformanceRequest_json_payload) {
    $payload = $creq->payload->json_payload;
    $wfi = WireFormat::JSON;
  } else {
    $cresp->result = new ConformanceResponse_skipped("unsupported input type");
    return $cresp;
  }
  $wfo = $creq->requested_output_format;
  if ($wfo != WireFormat::PROTOBUF && $wfo != WireFormat::JSON) {
    $cresp->result = new ConformanceResponse_skipped("unsupported output type");
    return $cresp;
  }
  $r = remarshal($tm, $payload, $wfi, $wfo);
  if (!$r->Ok()) {
    $estr = $r->Error()->Error();
    p('parse error: '.$estr);
    $cresp->result = new ConformanceResponse_parse_error($estr);
    return $cresp;
  }
  $result = $r->MustValue();
  switch ($wfo) {
    case WireFormat::PROTOBUF:
      $cresp->result = new ConformanceResponse_protobuf_payload($result);
      break;
    case WireFormat::JSON:
      $cresp->result = new ConformanceResponse_json_payload($result);
      break;
  }
  p("response: ".\print_r($cresp, true));
  return $cresp;
}

use \Errors\Result;
use function \Errors\{ResultE, ResultV, Ok};

function remarshal(
  \Protobuf\Message $tm,
  string $in,
  int $wfi,
  int $wfo,
): Result<string> {
  $err = null;
  switch ($wfi) {
    case WireFormat::PROTOBUF:
      $err = \Protobuf\Unmarshal($in, $tm);
      break;
    case WireFormat::JSON:
      $err = \Protobuf\UnmarshalJson($in, $tm);
      break;
    default:
      throw new \Exception('unexpected wire format');
  }
  if (!$err->Ok()) {
    return ResultE($err);
  }
  p("remarshaling: ".\print_r($tm, true));
  switch ($wfo) {
    case WireFormat::PROTOBUF:
      return ResultV(\Protobuf\Marshal($tm));
    case WireFormat::JSON:
      return ResultV(\Protobuf\MarshalJson($tm));
  }
  throw new \Exception("invalid output wire format: $wfo");
}
