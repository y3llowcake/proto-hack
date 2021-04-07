<?hh // strict
namespace Protobuf\Internal;

function a(mixed $got, mixed $exp, string $msg): void {
  if ($got !== $exp) {
    $m = \sprintf(
      "%s got:'%s' expected:'%s'",
      $msg,
      \print_r($got, true),
      \print_r($exp, true),
    );
    throw new \Exception($m);
  }
}

function cat(int ...$is): string {
  $v = '';
  foreach ($is as $i) {
    $v .= \chr($i);
  }
  return $v;
}

function testVarint(int $dec, string $enc): void {
  $d = Decoder::FromString($enc);
  a($d->readVarint(), $dec, "read varint");
  $e = new Encoder();
  $e->writeVarint($dec);
  a($e->buffer(), $enc, "write varint");
}

function testVarintZigZag32(int $dec, string $enc): void {
  $d = Decoder::FromString($enc);
  a($d->readVarintZigZag32(), $dec, "read varint zigzag 32");
  $e = new Encoder();
  $e->writeVarintZigZag32($dec);
  a($e->buffer(), $enc, "write varint zigzag 32");
}

function testVarintZigZag64(int $dec, string $enc): void {
  $d = Decoder::FromString($enc);
  a($d->readVarintZigZag64(), $dec, "read varint zigzag 64");
  $e = new Encoder();
  $e->writeVarintZigZag64($dec);
  a($e->buffer(), $enc, "write varint zigzag 64");
}

function testLittleEndianInt32Signed(int $dec, string $enc): void {
  $d = Decoder::FromString($enc);
  a($d->readLittleEndianInt32Signed(), $dec, "read le int32");
  $e = new Encoder();
  $e->writeLittleEndianInt32Signed($dec);
  a($e->buffer(), $enc, "write le int32");
}

function testLittleEndianInt64(int $dec, string $enc): void {
  $d = Decoder::FromString($enc);
  a($d->readLittleEndianInt64(), $dec, "read le int64");
  $e = new Encoder();
  $e->writeLittleEndianInt64($dec);
  a($e->buffer(), $enc, "write le int64");
}

<<__EntryPoint>>
function main(): void {
  require "lib/protobuf.php";

  AssertEndiannessAndIntSize();

  // Varint 128
  testVarint(0, cat(0x0));
  testVarint(3, cat(0x3));
  testVarint(300, cat(0xAC, 0x02));
  testVarint(
    -1,
    cat(0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x01),
  );
  testVarint(
    -15,
    cat(0xF1, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x01),
  );

  // Zigzag
  testVarintZigZag32(0, cat(0x00));
  testVarintZigZag32(-1, cat(0x01));
  testVarintZigZag32(1, cat(0x02));
  testVarintZigZag32(-2, cat(0x03));

  testVarintZigZag64(0, cat(0x00));
  testVarintZigZag64(-1, cat(0x01));
  testVarintZigZag64(1, cat(0x02));
  testVarintZigZag64(-2, cat(0x03));

  // TODO this seems broken:
  // testVarintZigZag(-1, cat(0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x01));

  // Little endian 32
  testLittleEndianInt32Signed(0, cat(0x00, 0x00, 0x00, 0x00));
  testLittleEndianInt32Signed(1, cat(0x01, 0x00, 0x00, 0x00));
  testLittleEndianInt32Signed(1234567890, cat(0xD2, 0x02, 0x96, 0x49));
  testLittleEndianInt32Signed(-1, cat(0xFF, 0xFF, 0xFF, 0xFF));

  testLittleEndianInt64(0, cat(0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));
  testLittleEndianInt64(1, cat(0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));
  testLittleEndianInt64(
    -1,
    cat(0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF),
  );
}
