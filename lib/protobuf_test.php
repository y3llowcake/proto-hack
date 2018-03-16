<?hh // partial
namespace Protobuf\Internal;
include "protobuf.php";

function a(mixed $got, mixed $exp, string $msg): void {
	if ($got !== $exp) {
		$m = sprintf("%s got:'%s' expected:'%s'", $msg, print_r($got, true), print_r($exp, true));
		throw new \Exception($m);
	}
}

function testVarInt(int $dec, string $enc) {
	$d = Decoder::FromString($enc);
	a($d->readVarInt128(), $dec, "read varint");
	$e = new Encoder();
	$e->writeVarInt128($dec);
	a((string)$e, $enc, "write varint");
}

function testVarIntZigZag(int $dec, string $enc) {
	$d = Decoder::FromString($enc);
	a($d->readVarInt128ZigZag(), $dec, "read varint zigzag");
	$e = new Encoder();
	$e->writeVarInt128ZigZag($dec);
	a((string)$e, $enc, "write varint zigzag");
}


function test(): void {
	// Varint 128
	testVarInt(0, chr(0x0));
	testVarInt(300, chr(0xAC) . chr(0x02));

	// Zigzag
	testVarIntZigZag(0, chr(0x00));
	testVarIntZigZag(-1, chr(0x01));
	testVarIntZigZag(1, chr(0x02));
	testVarIntZigZag(-2, chr(0x03));
}

AssertEndiannessAndIntSize();
test();
