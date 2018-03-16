<?hh // partial
namespace Protobuf\Internal;
include "protobuf.php";

function a(mixed $got, mixed $exp, string $msg): void {
	if ($got !== $exp) {
		$m = sprintf("%s got:'%s' expected:'%s'", $msg, print_r($got, true), print_r($exp, true));
		throw new \Exception($m);
	}
}

function cat(int ...$is): string {
	$v = '';
	foreach ($is as $i) {
		$v .= chr($i);
	}
	return $v;
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

function testLittleEndianInt(int $dec, string $enc, int $size) {
	$d = Decoder::FromString($enc);
	a($d->readLittleEndianInt($size), $dec, "read le int");
	$e = new Encoder();
	$e->writeLittleEndianInt($dec, $size);
	a((string)$e, $enc, "write le int");
}


// TODO all the read write funcs.

function test(): void {
	// Varint 128
	testVarInt(0, cat(0x0));
	testVarInt(300, cat(0xAC, 0x02));

	// Zigzag
	testVarIntZigZag(0, cat(0x00));
	testVarIntZigZag(-1, cat(0x01));
	testVarIntZigZag(1, cat(0x02));
	testVarIntZigZag(-2, cat(0x03));

	// Little endian
	testLittleEndianInt(0, cat(0x00, 0x00, 0x00, 0x00), 4);
	testLittleEndianInt(0, cat(0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00), 8);
	testLittleEndianInt(1234567890, cat(0xD2, 0x02, 0x96, 0x49), 4);

}

AssertEndiannessAndIntSize();
test();
