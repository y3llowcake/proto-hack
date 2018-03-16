<?hh // partial
namespace Protobuf\Internal;
include "protobuf.php";

function a(mixed $got, mixed $exp, string $msg): void {
	if ($got !== $exp) {
		$m = sprintf("%s got:'%s' expected:'%s'", $msg, print_r($got, true), print_r($exp, true));
		throw new \Exception($m);
	}
}

function test(): void {
	// Varint 128
	$b = Decoder::FromString(chr(0x0));
	a($b->readVarInt128(), 0, "read varint 0");

	$b = Decoder::FromString(chr(0xAC) . chr(0x02));
	a($b->readVarInt128(), 300, "read varint 0xAC02");

	// Zigzag
	$b = Decoder::FromString(chr(0x00));
	a($b->readVarInt128ZigZag(), 0, "read zigzag 0");

	$b = Decoder::FromString(chr(0x01));
	a($b->readVarInt128ZigZag(), -1, "read zigzag 1");

	$b = Decoder::FromString(chr(0x02));
	a($b->readVarInt128ZigZag(), 1, "read zigzag 2");

	$b = Decoder::FromString(chr(0x03));
	a($b->readVarInt128ZigZag(), -2, "read zigzag 3");
}

AssertEndiannessAndIntSize();
test();
