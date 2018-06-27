<?hh // strict

namespace Protobuf {

  class ProtobufException extends \Exception {}

  interface Message {
    public function MergeFrom(Internal\Decoder $d): void;
    public function MergeJsonFrom(Internal\JsonDecoder $d): void;
    public function WriteTo(Internal\Encoder $e): void;
    public function WriteJsonTo(Internal\JsonEncoder $e): void;
  }

  function Unmarshal(string $data, Message $message): void {
    $message->MergeFrom(Internal\Decoder::FromString($data));
  }

  function UnmarshalJson(string $data, Message $message): void {
    $message->MergeJsonFrom(Internal\JsonDecoder::FromString($data));
  }

  function Marshal(Message $message): string {
    $e = new Internal\Encoder();
    $message->WriteTo($e);
    return (string)$e;
  }

  function MarshalJson(Message $message, int $opt = 0): string {
    $e = new Internal\JsonEncoder(new Internal\JsonEncodeOpt($opt));
    $message->WriteJsonTo($e);
    return (string)$e;
  }

  class JsonEncode {
    // https://developers.google.com/protocol-buffers/docs/proto3#json_options
    const int PRETTY_PRINT = 1 << 1;
    const int EMIT_DEFAULT_VALUES = 1 << 2;
    const int PRESERVE_NAMES = 1 << 3;
    const int ENUMS_AS_INTS = 1 << 4;
  }
}
// namespace Protobuf

namespace Protobuf\Internal {
  // AVERT YOUR EYES YE! NOTHING TO SEE BELOW!

  function AssertEndiannessAndIntSize(): void {
    if (\PHP_INT_SIZE != 8) {
      throw new \Protobuf\ProtobufException(
        "unsupported PHP_INT_SIZE size: ".\PHP_INT_SIZE,
      );
    }
    $end = \unpack('l', \chr(0x70).\chr(0x10).\chr(0xF0).\chr(0x00))[1];
    if ($end !== 15732848) {
      throw new \Protobuf\ProtobufException(
        "unsupported endianess (is this machine little endian?): ".$end,
      );
    }
  }

  // https://developers.google.com/protocol-buffers/docs/encoding
  class Decoder {
    private function __construct(
      private string $buf,
      private int $offset,
      private int $len,
    ) {}

    public static function FromString(string $buf): Decoder {
      return new Decoder($buf, 0, \strlen($buf));
    }

    public function readVarint(): int {
      $val = 0;
      $shift = 0;
      while (true) {
        if ($this->isEOF()) {
          throw new \Protobuf\ProtobufException(
            "buffer overrun while reading varint-128",
          );
        }
        $c = \ord($this->buf[$this->offset]);
        $this->offset++;
        $val += (($c & 127) << $shift);
        $shift += 7;
        if ($c < 128) {
          break;
        }
      }
      return $val;
    }

    public function readVarint32(): int {
      # Throw away the upper 32 bits.
      return $this->readVarint() & 0xFFFFFFFF;
    }

    public function readVarint32Signed(): int {
      $i = $this->readVarint32();
      if ($i > 0x7FFFFFFF) {
        # This is a corner validation case, the writer wrote to the 32 bit, but
        # we only support 31 bits.
        return $i | (0xFFFFFFFF << 32);
      }
      return $i;
    }

    // returns (field number, wire type)
    public function readTag(): (int, int) {
      $k = $this->readVarint();
      $fn = $k >> 3;
      if ($fn == 0) {
        throw new \Protobuf\ProtobufException("zero field number");
      }
      return tuple($fn, $k & 0x7);
    }

    public function readLittleEndianInt32Signed(): int {
      return \unpack('l', $this->readRaw(4))[1];
    }

    public function readLittleEndianInt32Unsigned(): int {
      return \unpack('L', $this->readRaw(4))[1];
    }

    public function readLittleEndianInt64(): int {
      return \unpack('q', $this->readRaw(8))[1];
    }

    public function readBool(): bool {
      return $this->readVarint() != 0;
    }

    public function readFloat(): float {
      $f = \unpack('f', $this->readRaw(4))[1];
      return $f;
    }

    public function readDouble(): float {
      return \unpack('d', $this->readRaw(8))[1];
    }

    public function readString(): string {
      $len = $this->readVarint();
      if ($len == 0) {
        return '';
      }
      return $this->readRaw($len);
    }

    public function readVarintZigZag32(): int {
      $i = $this->readVarint();
      $i |= ($i & 0xFFFFFFFF);
      return (($i >> 1) & 0x7FFFFFFF) ^ (-($i & 1));
    }

    public function readVarintZigZag64(): int {
      $i = $this->readVarint();
      return (($i >> 1) & 0x7FFFFFFFFFFFFFFF) ^ (-($i & 1));
    }

    private function readRaw(int $size): string {
      if ($this->isEOF()) {
        throw
          new \Protobuf\ProtobufException("buffer overrun while reading raw");
      }
      $noff = $this->offset + $size;
      if ($noff > $this->len) {
        throw new \Protobuf\ProtobufException(
          "buffer overrun while reading raw: ".$size,
        );
      }
      $ss = \substr($this->buf, $this->offset, $size);
      $this->offset = $noff;
      return $ss;
    }

    public function readDecoder(): Decoder {
      $size = $this->readVarint();
      $noff = $this->offset + $size;
      if ($noff > $this->len) {
        throw new \Protobuf\ProtobufException(
          "buffer overrun while reading buffer: ".$size,
        );
      }
      $buf = new Decoder($this->buf, $this->offset, $noff);
      $this->offset = $noff;
      return $buf;
    }

    public function isEOF(): bool {
      return $this->offset >= $this->len;
    }

    public function skipWireType(int $wt): void {
      switch ($wt) {
        case 0:
          $this->readVarint(); // We could technically optimize this to skip.
          break;
        case 1:
          $this->offset += 8;
          break;
        case 2:
          $this->offset += $this->readVarint();
          break;
        case 5:
          $this->offset += 4;
          break;
        default:
          throw new \Protobuf\ProtobufException(
            "encountered unknown wire type $wt during skip",
          );
      }
      if ($this->offset > $this->len) { // Note: not EOF.
        throw new \Protobuf\ProtobufException("buffer overrun after skip");
      }
    }
  }

  class Encoder {
    private string $buf;
    public function __construct() {
      $this->buf = "";
    }

    public function writeVarint(int $i): void {
      if ($i < 0) {
        // Special case: The sign bit is preserved while right shifiting.
        $this->buf .= \chr(($i & 0x7F) | 0x80);
        // Now shift and move sign bit.
        $i = (($i & 0x7FFFFFFFFFFFFFFF) >> 7) | 0x100000000000000;
      }
      while (true) {
        $b = $i & 0x7F; // lower 7 bits
        $i = $i >> 7;
        if ($i == 0) {
          $this->buf .= \chr($b);
          return;
        }
        $this->buf .= \chr($b | 0x80); // set the top bit.
      }
    }

    public function writeTag(int $fn, int $wt): void {
      $this->writeVarint(($fn << 3) | $wt);
    }

    public function writeLittleEndianInt32Signed(int $i): void {
      $this->buf .= \pack('l', $i);
    }

    public function writeLittleEndianInt32Unsigned(int $i): void {
      $this->buf .= \pack('L', $i);
    }

    public function writeLittleEndianInt64(int $i): void {
      $this->buf .= \pack('q', $i);
    }

    public function writeBool(bool $b): void {
      $this->buf .= $b ? \chr(0x01) : \chr(0x00);
    }

    public function writeFloat(float $f): void {
      $this->buf .= \pack('f', $f);
    }

    public function writeDouble(float $d): void {
      $this->buf .= \pack('d', $d);
    }

    public function writeString(string $s): void {
      $this->writeVarint(\strlen($s));
      $this->buf .= $s;
    }

    public function writeVarintZigZag32(int $i): void {
      $i = $i & 0xFFFFFFFF;
      $i = (($i << 1) ^ ($i << 32 >> 63)) & 0xFFFFFFFF;
      $this->writeVarint($i);
    }

    public function writeVarintZigZag64(int $i): void {
      $this->writeVarint(($i << 1) ^ ($i >> 63));
    }

    public function writeEncoder(Encoder $e, int $fn): void {
      if (!$e->isEmpty()) {
        $this->writeTag($fn, 2);
        $this->writeString((string)$e);
      }
    }

    public function isEmpty(): bool {
      return \strlen($this->buf) == 0;
    }

    public function __toString(): string {
      return $this->buf;
    }
  }

  interface FileDescriptor {
    public function Name(): string;
    public function FileDescriptorProtoBytes(): string;
  }

  function LoadedFileDescriptors(): vec<\Protobuf\Internal\FileDescriptor> {
    $ret = vec[];
    foreach (\get_declared_classes() as $cname) {
      if (\strpos($cname, 'XXX_FileDescriptor_') === false) {
        continue;
      }
      $rc = new \ReflectionClass($cname);
      if (!$rc->implementsInterface('Protobuf\Internal\FileDescriptor')) {
        continue;
      }
      $ins = $rc->newInstance();
      $ret[] = $ins;
    }
    return $ret;
  }

  class JsonEncodeOpt {
    public bool $pretty_print;
    public bool $emit_default_values;
    public bool $preserve_names;
    public bool $enums_as_ints;

    public function __construct(int $opt) {
      $this->pretty_print = (bool)($opt & \Protobuf\JsonEncode::PRETTY_PRINT);
      $this->emit_default_values =
        (bool)($opt & \Protobuf\JsonEncode::EMIT_DEFAULT_VALUES);
      $this->preserve_names =
        (bool)($opt & \Protobuf\JsonEncode::PRESERVE_NAMES);
      $this->enums_as_ints = (bool)($opt & \Protobuf\JsonEncode::ENUMS_AS_INTS);
    }
  }

  class JsonEncoder {
    private dict<string, mixed> $a;
    private JsonEncodeOpt $o;

    // https://developers.google.com/protocol-buffers/docs/proto3#json_options
    public function __construct(JsonEncodeOpt $o) {
      $this->a = dict[];
      $this->o = $o;
    }

    private function encodeMessage(?\Protobuf\Message $m): dict<string, mixed> {
      $e = new JsonEncoder($this->o);
      if ($m !== null) {
        $m->WriteJsonTo($e);
      }
      return $e->a;
    }

    public function writeMessage(
      string $oname,
      string $cname,
      ?\Protobuf\Message $value,
      bool $emit_default,
    ): void {
      $a = $this->encodeMessage($value);
      if (\count($a) != 0 || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $a;
      }
    }

    public function writeMessageList(
      string $oname,
      string $cname,
      vec<?\Protobuf\Message> $value,
    ): void {
      $as = vec[];
      foreach ($value as $v) {
        $as[] = $this->encodeMessage($v);
      }
      if (\count($as) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $as;
      }
    }

    public function writeMessageMap(
      string $oname,
      string $cname,
      dict<arraykey, ?\Protobuf\Message> $value,
    ): void {
      $vs = dict[];
      foreach ($value as $k => $v) {
        $vs[$k] = $this->encodeMessage($v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeInt32(
      string $oname,
      string $cname,
      int $value,
      bool $emit_default,
    ): void {
      if ($value != 0 || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
      }
    }

    public function writeInt64Signed(
      string $oname,
      string $cname,
      int $value,
      bool $emit_default,
    ): void {
      if ($value != 0 || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] =
          \sprintf('%d', $value);
      }
    }

    public function writeInt64SignedList(
      string $oname,
      string $cname,
      vec<int> $value,
    ): void {
      $vs = vec[];
      foreach ($value as $v) {
        $vs[] = \sprintf('%d', $v);
      }
      if (\count($value) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeInt64SignedMap(
      string $oname,
      string $cname,
      dict<arraykey, int> $value,
    ): void {
      $vs = dict[];
      foreach ($value as $k => $v) {
        $vs[$k] = \sprintf('%d', $v);
      }
      if (\count($value) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeInt64Unsigned(
      string $oname,
      string $cname,
      int $value,
      bool $emit_default,
    ): void {
      if ($value != 0 || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] =
          \sprintf('%u', $value);
      }
    }

    public function writeInt64UnsignedList(
      string $oname,
      string $cname,
      vec<int> $value,
    ): void {
      $vs = vec[];
      foreach ($value as $v) {
        $vs[] = \sprintf('%u', $v);
      }
      if (\count($value) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeInt64UnsignedMap(
      string $oname,
      string $cname,
      dict<arraykey, int> $value,
    ): void {
      $vs = dict[];
      foreach ($value as $k => $v) {
        $vs[$k] = \sprintf('%u', $v);
      }
      if (\count($value) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    private static function encodeFloat(float $value): string {
      if (\is_finite($value)) {
        //return $value;
        return \sprintf('%.999e', $value);
      }
      if (\is_nan($value)) {
        return "NaN";
      }
      return $value > 0 ? "Infinity" : "-Infinity";
    }

    public function writeFloat(
      string $oname,
      string $cname,
      float $value,
      bool $emit_default,
    ): void {
      if ($value != 0.0 || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] =
          self::encodeFloat($value);
      }
    }

    public function writeFloatList(
      string $oname,
      string $cname,
      vec<float> $value,
    ): void {
      $vs = vec[];
      foreach ($value as $v) {
        $vs[] = self::encodeFloat($v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeFloatMap(
      string $oname,
      string $cname,
      dict<arraykey, float> $value,
    ): void {
      $vs = dict[];
      foreach ($value as $k => $v) {
        $vs[$k] = self::encodeFloat($v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeBool(
      string $oname,
      string $cname,
      bool $value,
      bool $emit_default,
    ): void {
      if ($value != false || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
      }
    }

    public function writeString(
      string $oname,
      string $cname,
      string $value,
      bool $emit_default,
    ): void {
      if ($value != '' || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
      }
    }

    private static function base64_url_encode(string $d): string {
      return \strtr(\base64_encode($d), '+/', '-_');
    }

    public function writeBytes(
      string $oname,
      string $cname,
      string $value,
      bool $emit_default,
    ): void {
      if ($value != '' || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] =
          self::base64_url_encode($value);
      }
    }

    private function encodeEnum(dict<int, string> $itos, int $v): mixed {
      if (!$this->o->enums_as_ints) {
        return idx($itos, $v, $v);
      }
      return $v;
    }

    public function writeEnum(
      string $oname,
      string $cname,
      dict<int, string> $itos,
      int $value,
      bool $emit_default,
    ): void {
      if ($value != 0 || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] =
          $this->encodeEnum($itos, $value);
      }
    }

    public function writeEnumList(
      string $oname,
      string $cname,
      dict<int, string> $itos,
      vec<int> $value,
    ): void {
      $vs = vec[];
      foreach ($value as $v) {
        $vs[] = $this->encodeEnum($itos, $v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeEnumMap(
      string $oname,
      string $cname,
      dict<int, string> $itos,
      dict<arraykey, int> $value,
    ): void {
      $vs = dict[];
      foreach ($value as $k => $v) {
        $vs[$k] = $this->encodeEnum($itos, $v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writePrimitiveList<T>(
      string $oname,
      string $cname,
      vec<T> $value,
    ): void {
      if (\count($value) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
      }
    }

    public function writeBytesMap<T>(
      string $oname,
      string $cname,
      dict<arraykey, string> $value,
    ): void {
      $vs = dict[];
      foreach ($value as $k => $v) {
        $vs[$k] = self::base64_url_encode($v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writeBytesList<T>(
      string $oname,
      string $cname,
      vec<string> $value,
    ): void {
      $vs = vec[];
      foreach ($value as $k => $v) {
        $vs[] = self::base64_url_encode($v);
      }
      if (\count($vs) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
      }
    }

    public function writePrimitiveMap<T>(
      string $oname,
      string $cname,
      dict<arraykey, T> $value,
    ): void {
      if (\count($value) != 0 || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
      }
    }

    public function __toString(): string {
      $opt = \JSON_PARTIAL_OUTPUT_ON_ERROR;
      if ($this->o->pretty_print) {
        $opt |= \JSON_PRETTY_PRINT;
      }
      return \json_encode($this->a, $opt);
    }
  } // class JsonEncoder

  class JsonDecoder {
    public function __construct(
      public dict<string, mixed> $d,
      // TODO consider extending IteratorAggregate
    ) {}

    public static function FromString(string $str): JsonDecoder {
      //$data = \json_decode($str, true, 512 /* todo make optional*/, \JSON_OBJECT_AS_ARRAY | \JSON_BIGINT_AS_STRING);
      $data = \json_decode($str, true, 512 /* todo make optional*/);
      if ($data !== null) {
        $v = self::readObjectOrNull($data);
        if ($v !== null) {
          return new JsonDecoder($v);
        }
      }
      throw new \Protobuf\ProtobufException(\sprintf(
        "json_decode failed; got %s expected array: %s",
        \gettype($data),
        \json_last_error_msg(),
      ));
    }

    private static function readObjectOrNull(mixed $m): ?dict<string, mixed> {
      if (is_array($m)) {
        $ret = dict[];
        foreach ($m as $k => $v) {
          // TODO, I could check for objects by seeing if the key is int.
          $ret[(string)$k] = $v;
        }
        return $ret;
      }
      return null;
    }

    public static function readObject(mixed $m): dict<string, mixed> {
      $ret = self::readObjectOrNull($m);
      return $ret === null ? dict[] : $ret;
    }

    public static function readDecoder(mixed $m): JsonDecoder {
      return new JsonDecoder(self::readObject($m));
    }

    public static function readList(mixed $m): vec<mixed> {
      $ret = vec[];
      if ($m === null)
        return $ret;
      if (is_array($m)) {
        foreach ($m as $v) {
          // TODO, I could check for objects by seeing if the key is string.
          $ret[] = $v;
        }
        return $ret;
      }
      throw new \Protobuf\ProtobufException(
        \sprintf("expected list got %s", \gettype($m)),
      );
    }

    public static function readBytes(mixed $m): string {
      if ($m === null)
        return '';
      if (is_string($m)) {
        return self::base64_url_decode($m);
      }
      throw new \Protobuf\ProtobufException(
        \sprintf("expected string got %s", \gettype($m)),
      );
    }

    private static function base64_url_decode(string $d): string {
      $b = \base64_decode(
        \str_pad(\strtr($d, '-_', '+/'), \strlen($d) % 4, '=', \STR_PAD_RIGHT),
      );
      if (is_string($b))
        return $b;
      throw new \Protobuf\ProtobufException("base64 decode failed");
    }

    public static function readString(mixed $m): string {
      if ($m === null)
        return '';
      if (is_string($m))
        return $m;
      throw new \Protobuf\ProtobufException(
        \sprintf("expected string got %s", \gettype($m)),
      );
    }

    private static function isDigitString(string $s, bool $signed): bool {
      if ($signed && $s[0] === '-') {
        return \ctype_digit(\substr($s, 1));
      }
      return \ctype_digit($s);
    }

    private static function readInt(mixed $m, bool $signed, bool $b64): int {
      if ($m === null)
        return 0;
      if (\is_int($m))
        return $m;
      if (\is_string($m)) {
        if ($m === '') {
          throw new \Protobuf\ProtobufException('empty integer string');
        }

        if (!self::isDigitString($m, $signed)) {
          throw
            new \Protobuf\ProtobufException('invalid char in integer string');
        }

        if ($b64) {
          // sscanf behaves unexpectedly when the input exceeds int64 bounds.
          if ($signed) {
            if (
              \bccomp($m, '9223372036854775807') > 0 ||
              \bccomp($m, '-9223372036854775808') < 0
            ) {
              throw new \Protobuf\ProtobufException('int64 out of bounds');
            }
          } else {
            if (\bccomp($m, '9223372036854775807') > 0) {
              if (\bccomp($m, '18446744073709551615') > 0) {
                throw new \Protobuf\ProtobufException('uint64 out of bounds');
              }
              // TODO SPECIAL CASE PARSE
            }
          }
        }

        $a = \sscanf($m, '%d');
        if (\count($a) > 0) {
          if (is_int($a[0])) {
            return $a[0];
          }
        }
        throw new \Protobuf\ProtobufException(
          \sprintf("expected int got weird string"),
        );
      }
      if (\is_float($m)) {
        if (\fmod($m, 1) !== 0.00) {
          throw new \Protobuf\ProtobufException(
            'expected int got non integral float',
          );
        }
        return (int)$m;
      }
      throw new \Protobuf\ProtobufException(
        \sprintf("expected int got %s", \gettype($m)),
      );
    }

    public static function readInt32Signed(mixed $m): int {
      $i = self::readInt($m, true, false);
      if ($i > 2147483647 || $i < -2147483648) {
        throw new \Protobuf\ProtobufException('int32 out of bounds');
      }
      return $i;
    }

    public static function readInt32Unsigned(mixed $m): int {
      $i = self::readInt($m, false, false);
      if ($i > 4294967295) {
        throw new \Protobuf\ProtobufException('uint32 out of bounds');
      }
      return $i;
    }

    public static function readInt64Unsigned(mixed $m): int {
      return self::readInt($m, false, true);
    }

    public static function readInt64Signed(mixed $m): int {
      return self::readInt($m, true, true);
    }

    public static function readFloat(mixed $m): float {
      if ($m === null)
        return 0.0;
      if (is_string($m)) {
        if ($m == "NaN")
          return \NAN;
        if ($m == "Infinity")
          return \INF;
        if ($m == "-Infinity")
          return -\INF;
      }
      return (float)$m;
    }

    public static function readMapKeyBool(mixed $m): bool {
      return $m === "true";
    }

    public static function readBool(mixed $m): bool {
      if ($m === null)
        return false;
      if (is_bool($m))
        return $m;
      throw new \Protobuf\ProtobufException(
        \sprintf("expected bool got %s", \gettype($m)),
      );
    }
  }
}
// namespace Protobuf/Internal
