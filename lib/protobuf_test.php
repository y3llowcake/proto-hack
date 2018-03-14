<?hh //strict
namespace Protobuf\Internal;
include "protobuf.php";

AssertEndiannessAndIntSize();

function a(mixed $got, mixed $exp, string $msg) {
	if ($got !== $exp) {
		throw new Exception($msg . "; got:'{$got}' expected:'{$exp}'");
	}
}

// Varint 128
$b = Decoder::FromString(chr(0x0));
a($b->readVarInt128(), 0, "read varint 0");

$b = Decoder::FromString(chr(0xAC) . chr(0x02));
a($b->readVarInt128(), 300, "read varint 0xAC02");

// Zigzag
$b = Decoder::FromString(chr(0x00));
a(ZigZagDecode($b->readVarInt128()), 0, "read zigzag 0");

$b = Decoder::FromString(chr(0x01));
a(ZigZagDecode($b->readVarInt128()), -1, "read zigzag 1");

$b = Decoder::FromString(chr(0x02));
a(ZigZagDecode($b->readVarInt128()), 1, "read zigzag 2");

$b = Decoder::FromString(chr(0x03));
a(ZigZagDecode($b->readVarInt128()), -2, "read zigzag 3");
