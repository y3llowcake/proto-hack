<?hh // partial

include '../lib/protobuf.php';
include 'gen-src/google/protobuf/any_proto.php';
include 'gen-src/third_party/google/protobuf/conformance/conformance_proto.php';

use conformance\ConformanceRequest;
use conformance\ConformanceResponse;

# https://github.com/google/protobuf/blob/master/conformance/conformance_test_runner.cc
# https://github.com/google/protobuf/blob/master/conformance/conformance.proto

function p(string $s): void {
	fwrite(STDERR, 'conformance.php: ' . $s . "\n");
}

function conformancePipe(): void {
	$in = fopen('php://stdin', 'r');
	while (true) {
		$lens = fread($in, 4);
		$len = unpack('l', $lens)[1];
		p("reading: $len");
		$payload = fread($in, $len);
		$result = conformanceRaw($payload);
		p('writing: ' . strlen($payload));
		echo pack('l', strlen($payload)) . $payload;
	}
}

function conformanceRaw(string $raw): string {
	$creq = new	ConformanceRequest();
	Protobuf\Unmarshal($raw, $creq);
	return Protobuf\Marshal(conformance($creq));
}

function conformance(ConformanceRequest $creq): ConformanceResponse {
	$cresp = new	conformance\ConformanceResponse();
	switch ($creq->oneof_payload()) {
	case conformance\ConformanceResponse_payload::protobuf_payload:
		$tm = new protobuf_test_messages\proto3\TestAllTypesProto3();
		try {
			Protobuf\Unmarshal($creq->protobuf_payload, $tm);
		} catch (Exception $e) {
			$cresp->parse_error = $e->getMessage();
			return $cresp;
		}
		$cresp->protobuf_payload = Protobuf\Marshal($tm);
		break;
	default:
		$cresp->skipped = "unsupported payload type";
	}
	return $cresp;
}

conformancePipe();
