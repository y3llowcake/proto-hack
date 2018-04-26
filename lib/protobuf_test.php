<?hh // partial
namespace Protobuf\Internal;
include "protobuf.php";

function a(mixed $got, mixed $exp, string $msg): void {
  if ($got !== $exp) {
    $m = sprintf(
      "%s got:'%s' expected:'%s'",
      $msg,
      print_r($got, true),
      print_r($exp, true),
    );
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
  a((string) $e, $enc, "write varint");
}

function testVarIntZigZag32(int $dec, string $enc) {
  $d = Decoder::FromString($enc);
  a($d->readVarInt128ZigZag32(), $dec, "read varint zigzag 32");
  $e = new Encoder();
  $e->writeVarInt128ZigZag32($dec);
  a((string) $e, $enc, "write varint zigzag 32");
}

function testVarIntZigZag64(int $dec, string $enc) {
  $d = Decoder::FromString($enc);
  a($d->readVarInt128ZigZag64(), $dec, "read varint zigzag 64");
  $e = new Encoder();
  $e->writeVarInt128ZigZag64($dec);
  a((string) $e, $enc, "write varint zigzag 64");
}

function testLittleEndianInt32(int $dec, string $enc) {
  $d = Decoder::FromString($enc);
  a($d->readLittleEndianInt32(), $dec, "read le int32");
  $e = new Encoder();
  $e->writeLittleEndianInt32($dec);
  a((string) $e, $enc, "write le int32");
}

function testLittleEndianInt64(int $dec, string $enc) {
  $d = Decoder::FromString($enc);
  a($d->readLittleEndianInt64(), $dec, "read le int64");
  $e = new Encoder();
  $e->writeLittleEndianInt64($dec);
  a((string) $e, $enc, "write le int64");
}

// TODO all the read write funcs.

function test(): void {
  // Varint 128
  testVarInt(0, cat(0x0));
  testVarInt(3, cat(0x3));
  testVarInt(300, cat(0xAC, 0x02));
  testVarInt(
    -1,
    cat(0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x01),
  );
  testVarInt(
    -15,
    cat(0xF1, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x01),
  );

  // Zigzag
  testVarIntZigZag32(0, cat(0x00));
  testVarIntZigZag32(-1, cat(0x01));
  testVarIntZigZag32(1, cat(0x02));
  testVarIntZigZag32(-2, cat(0x03));

  testVarIntZigZag64(0, cat(0x00));
  testVarIntZigZag64(-1, cat(0x01));
  testVarIntZigZag64(1, cat(0x02));
  testVarIntZigZag64(-2, cat(0x03));

  // TODO this seems broken:
  // testVarIntZigZag(-1, cat(0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x01));

  // Little endian 32
  testLittleEndianInt32(0, cat(0x00, 0x00, 0x00, 0x00));
  testLittleEndianInt32(1, cat(0x01, 0x00, 0x00, 0x00));
  testLittleEndianInt32(1234567890, cat(0xD2, 0x02, 0x96, 0x49));
  testLittleEndianInt32(-1, cat(0xFF, 0xFF, 0xFF, 0xFF));

  testLittleEndianInt64(
    0,
    cat(0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00),
  );
  testLittleEndianInt64(
    1,
    cat(0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00),
  );
  testLittleEndianInt64(
    -1,
    cat(0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF),
  );
}

AssertEndiannessAndIntSize();
test();
