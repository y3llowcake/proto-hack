<?hh // strict

namespace Protobuf {
  use type \Errors\Error;
  use function \Errors\Ok;

  interface Message {
    public function MergeFrom(Internal\Decoder $d): void;
    public function MergeJsonFrom(mixed $m): void;
    public function WriteTo(Internal\Encoder $e): void;
    public function WriteJsonTo(Internal\JsonEncoder $e): void;
    public function CopyFrom(Message $m): Error;
    public function MessageName(): string;
  }

  function Unmarshal(string $data, Message $message): Error {
    try {
      $message->MergeFrom(Internal\Decoder::FromString($data));
    } catch (\ProtobufException $e) {
      return $e->Error();
    }
    return Ok();
  }

  function UnmarshalJson(string $data, Message $message): Error {
    try {
      $message->MergeJsonFrom(Internal\JsonDecoder::FromString($data));
    } catch (\ProtobufException $e) {
      return $e->Error();
    }
    return Ok();
  }

  function UnmarshalCopy(Message $from, Message $to): Error {
    return $to->CopyFrom($from);
  }

  function UnmarshalAny(\google\protobuf\Any $any, Message $m): Error {
    $exp = 'type.googleapis.com/'.$m->MessageName();
    $got = $any->type_url;
    if ($exp !== $got) {
      return \Errors\Errorf(
        "invalid Any.type_url, expected '%s' got '%s'",
        $exp,
        $got,
      );
    }
    return Unmarshal($any->value, $m);
  }

  function Marshal(Message $message): string {
    $e = new Internal\Encoder();
    $message->WriteTo($e);
    return $e->buffer();
  }

  function MarshalJson(Message $message, int $opt = 0): string {
    $e = new Internal\JsonEncoder(new Internal\JsonEncodeOpt($opt));
    $message->WriteJsonTo($e);
    return $e->buffer();
  }

  function MarshalAny(Message $m): \google\protobuf\Any {
    $any = new \google\protobuf\Any();
    $any->type_url = 'type.googleapis.com/'.$m->MessageName();
    $any->value = Marshal($m);
    return $any;
  }

  class JsonEncode {
    // https://developers.google.com/protocol-buffers/docs/proto3#json_options
    const int PRETTY_PRINT = 1 << 1;
    const int EMIT_DEFAULT_VALUES = 1 << 2;
    const int PRESERVE_NAMES = 1 << 3;
    const int ENUMS_AS_INTS = 1 << 4;
  }

  class BoolMapKey {
    const Internal\bool_map_key_t TRUE = 'true';
    const Internal\bool_map_key_t FALSE = 'false';
    public static function FromBool(bool $b): Internal\bool_map_key_t {
      return $b ? self::TRUE : self::FALSE;
    }
    public static function ToBool(Internal\bool_map_key_t $b): bool {
      return $b == self::TRUE ? true : false;
    }
  }
}
// namespace Protobuf

namespace Protobuf\Internal {
  newtype bool_map_key_t as string = string;

  // AVERT YOUR EYES YE! NOTHING TO SEE BELOW!

  function AssertEndiannessAndIntSize(): void {
    if (\PHP_INT_SIZE != 8) {
      throw new \ProtobufException(
        "unsupported PHP_INT_SIZE size: ".\PHP_INT_SIZE,
      );
    }
    $end = \unpack('l', \chr(0x70).\chr(0x10).\chr(0xF0).\chr(0x00))[1];
    if ($end !== 15732848) {
      throw new \ProtobufException(
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
      private Encoder $skipped,
    ) {}

    public static function FromString(string $buf): Decoder {
      return new Decoder($buf, 0, \strlen($buf), new Encoder());
    }

    // TODO(perf) unroll the while loop?
    public function readVarint(): int {
      $val = 0;
      $shift = 0;
      while (true) {
        if ($this->isEOF()) {
          throw new \ProtobufException(
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
        throw new \ProtobufException("zero field number");
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
        throw new \ProtobufException("buffer overrun while reading raw");
      }
      $noff = $this->offset + $size;
      if ($noff > $this->len) {
        throw new \ProtobufException(
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
        throw new \ProtobufException(
          "buffer overrun while reading buffer: ".$size,
        );
      }
      $buf = new Decoder($this->buf, $this->offset, $noff, new Encoder());
      $this->offset = $noff;
      return $buf;
    }

    public function isEOF(): bool {
      return $this->offset >= $this->len;
    }

    public function skip(int $fn, int $wt): void {
      // Uncomment the line below to test the accuracy of discard logic.
      // $this->_discard($wt); return;

      $this->skipped->writeTag($fn, $wt);
      switch ($wt) {
        case 0:
          $this->skipped->writeVarint($this->readVarint());
          break;
        case 1:
          $this->skipped->writeRaw($this->readRaw(8));
          break;
        case 2:
          $this->skipped->writeString($this->readString());
          break;
        case 5:
          $this->skipped->writeRaw($this->readRaw(4));
          break;
        default:
          throw new \ProtobufException(
            "encountered unknown wire type $wt during skip",
          );
      }
    }

    public function skippedRaw(): string {
      return $this->skipped->buffer();
    }

    // Function below this line are not used internally by the API or generated
    // code, but are intentionally exposed for custom parsers to make use of.
    public function _discard(int $wt): void {
      $size = 0;
      switch ($wt) {
        case 0:
          $this->readVarint();
          return; // done, early exit.
        case 1:
          $size = 8;
          break;
        case 2:
          $size = $this->readVarint();
          break;
        case 5:
          $size = 4;
          break;
        default:
          throw new \ProtobufException(
            "encountered unknown wire type $wt during discard",
          );
      }
      $noff = $this->offset + $size;
      if ($noff > $this->len) {
        throw new \ProtobufException("buffer overrun while discarding: ".$size);
      }
      $this->offset = $noff;
    }

    public function _offset(): int {
      return $this->offset;
    }

    public function _buffer(): string {
      return $this->buf;
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

    public function writeRaw(string $s): void {
      $this->buf .= $s;
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
      $this->writeTag($fn, 2);
      $this->writeString($e->buf);
    }

    public function isEmpty(): bool {
      return \strlen($this->buf) == 0;
    }

    public function buffer(): string {
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
      $this->emit_default_values = (bool)(
        $opt & \Protobuf\JsonEncode::EMIT_DEFAULT_VALUES
      );
      $this->preserve_names = (bool)(
        $opt & \Protobuf\JsonEncode::PRESERVE_NAMES
      );
      $this->enums_as_ints = (bool)($opt & \Protobuf\JsonEncode::ENUMS_AS_INTS);
    }
  }

  class JsonEncoder {
    private JsonEncodeOpt $o;

    private dict<string, mixed> $a;
    private bool $custom_encoding;
    private mixed $custom_value;

    // https://developers.google.com/protocol-buffers/docs/proto3#json_options
    public function __construct(JsonEncodeOpt $o) {
      $this->a = dict[];
      $this->o = $o;
      $this->custom_encoding = false;
      $this->custom_value = null;
    }

    public function encodeMessage(?\Protobuf\Message $m): mixed {
      return $this->encodeMessageWithDefault($m)[0];
    }

    // the bool indicates if the value is the 'default' (empty) value.
    private function encodeMessageWithDefault(
      ?\Protobuf\Message $m,
    ): (mixed, bool) {
      $e = new JsonEncoder($this->o);
      if ($m !== null) {
        $m->WriteJsonTo($e);
      }
      if ($e->custom_encoding) {
        return tuple($e->custom_value, false);
      }
      return tuple($e->a, \count($e->a) == 0);
    }

    public function setCustomEncoding(mixed $m): void {
      $this->custom_encoding = true;
      $this->custom_value = $m;
    }

    public function writeMessage(
      string $oname,
      string $cname,
      ?\Protobuf\Message $value,
      bool $emit_default,
    ): void {
      $a = $this->encodeMessageWithDefault($value);
      if (!$a[1] || $emit_default || $this->o->emit_default_values) {
        $this->a[$this->o->preserve_names ? $oname : $cname] = $a[0];
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
        $this->a[$this->o->preserve_names ? $oname : $cname] = \sprintf(
          '%d',
          $value,
        );
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
        $this->a[$this->o->preserve_names ? $oname : $cname] = \sprintf(
          '%u',
          $value,
        );
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

    public static function encodeBytes(string $d): string {
      // base64 URL encoding.
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
          self::encodeBytes($value);
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
        $vs[$k] = self::encodeBytes($v);
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
        $vs[] = self::encodeBytes($v);
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

    public static function encodeDuration(int $s, int $ns): string {
      $ret = (string)$s;
      if ($ns != 0) {
        $sns = \rtrim(\sprintf("%09d", \abs($ns)), '0');
        $len = \strlen($sns);
        $pad = $len > 3 ? $len > 6 ? 9 : 6 : 3;
        $sns = \str_pad($sns, $pad, '0');
        $ret .= '.'.$sns;
      }
      return $ret.'s';
    }

    public function buffer(): string {
      $opt = \JSON_PARTIAL_OUTPUT_ON_ERROR;
      if ($this->o->pretty_print) {
        $opt |= \JSON_PRETTY_PRINT;
      }
      return \json_encode($this->a, $opt);
    }
  } // class JsonEncoder

  abstract class JsonDecoder {
    public static function FromString(string $str): mixed {
      if ($str === "null") {
        return null;
      }
      $data = \json_decode($str, true, 512, \JSON_FB_HACK_ARRAYS);
      if ($data !== null) {
        return $data;
      }
      throw new \ProtobufException(
        "json_decode failed; ".\json_last_error_msg(),
      );
    }

    public static function readObject(mixed $m): dict<string, mixed> {
      if ($m is dict<_, _>) {
        $ret = dict[];
        foreach ($m as $k => $v) {
          $ret[(string)$k] = $v;
        }
        return $ret;
      }
      throw new \ProtobufException(
        \sprintf("expected dict got %s", \gettype($m)),
      );
    }

    public static function readList(mixed $m): vec<mixed> {
      $ret = vec[];
      if ($m === null)
        return $ret;
      if ($m is vec<_>) {
        foreach ($m as $v) {
          $ret[] = $v;
        }
        return $ret;
      }
      throw new \ProtobufException(
        \sprintf("expected vec got %s", \gettype($m)),
      );
    }

    public static function readBytes(mixed $m): string {
      if ($m === null)
        return '';
      if ($m is string) {
        return self::decodeBytes($m);
      }
      throw new \ProtobufException(
        \sprintf("expected string got %s", \gettype($m)),
      );
    }

    private static function decodeBytes(string $d): string {
      // base64 url decode.
      $b = \base64_decode(
        \str_pad(\strtr($d, '-_', '+/'), \strlen($d) % 4, '=', \STR_PAD_RIGHT),
      );
      if ($b is string)
        return $b;
      throw new \ProtobufException("base64 decode failed");
    }

    public static function readString(mixed $m): string {
      if ($m === null)
        return '';
      if ($m is string)
        return $m;
      throw new \ProtobufException(
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
      if ($m is int)
        return $m;
      if ($m is string) {
        if ($m === '') {
          throw new \ProtobufException('empty integer string');
        }

        if (!self::isDigitString($m, $signed)) {
          throw new \ProtobufException('invalid char in integer string');
        }

        $mgmp = \gmp_init($m, 10);
        if ($b64) {
          if ($signed) {
            if (
              ((int)\gmp_cmp($mgmp, '9223372036854775807')) > 0 ||
              ((int)\gmp_cmp($mgmp, '-9223372036854775808')) < 0
            ) {
              throw new \ProtobufException('int64 out of bounds');
            }
          } else {
            if (((int)\gmp_cmp($m, '9223372036854775807')) > 0) {
              if (((int)\gmp_cmp($m, '18446744073709551615')) > 0) {
                throw new \ProtobufException('uint64 out of bounds');
              }
              \gmp_clrbit(inout $mgmp, 63);
              return \gmp_intval($mgmp) | 0x8000000000000000;
            }
          }
        }

        return \gmp_intval($mgmp);
      }
      if ($m is float) {
        if (\fmod($m, 1.0) !== 0.00) {
          throw new \ProtobufException('expected int got non integral float');
        }
        return (int)$m;
      }
      throw new \ProtobufException(
        \sprintf("expected int got %s", \gettype($m)),
      );
    }

    public static function readInt32Signed(mixed $m): int {
      $i = self::readInt($m, true, false);
      if ($i > 2147483647 || $i < -2147483648) {
        throw new \ProtobufException('int32 out of bounds');
      }
      return $i;
    }

    public static function readInt32Unsigned(mixed $m): int {
      $i = self::readInt($m, false, false);
      if ($i > 4294967295) {
        throw new \ProtobufException('uint32 out of bounds');
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
      if ($m is string) {
        if ($m == "NaN")
          return \NAN;
        if ($m == "Infinity")
          return \INF;
        if ($m == "-Infinity")
          return -\INF;
      }
      if (\is_numeric($m)) {
        return (float)$m;
      }
      throw new \ProtobufException(
        \sprintf("expected float got %s", \gettype($m)),
      );
    }

    public static function readBool(mixed $m): bool {
      if ($m === null)
        return false;
      if ($m is bool)
        return $m;
      throw new \ProtobufException(
        \sprintf("expected bool got %s", \gettype($m)),
      );
    }

    public static function readBoolMapKey(mixed $m): bool_map_key_t {
      if ($m is string) {
        if ($m === 'true')
          return $m;
        if ($m === 'false')
          return $m;
        throw new \ProtobufException('could not map string to bool');
      }
      throw new \ProtobufException(
        \sprintf("expected string got %s", \gettype($m)),
      );
    }

    public static function readDuration(mixed $m): (int, int) {
      if ($m === null)
        return tuple(0, 0);
      if ($m is string) {
        if (\substr($m, -1) != 's') {
          throw new \ProtobufException('duration missing trailing \'s\'');
        }
        $m = \substr($m, 0, -1);
        $parts = \explode('.', $m);
        $s = (int)$parts[0];
        $ns = 0;
        if (\count($parts) == 2) {
          $sns = \str_pad($parts[1], 9, '0');
          $ns = (int)$sns;
        } else if (\count($parts) > 2) {
          throw new \ProtobufException(\sprintf(
            'duration has wrong number of parts; got %d expected <= 2',
            \count($parts),
          ));
        }
        if ($s < 0) {
          $ns = -$ns;
        }
        if (
          $s < -315576000000 ||
          $s > 315576000000 ||
          $ns < -999999999 ||
          $ns > 999999999
        ) {
          throw new \ProtobufException('duration out of bounds');
        }
        return tuple($s, $ns);
      }
      throw new \ProtobufException(
        \sprintf("expected string got %s", \gettype($m)),
      );
    }
  }
}
// namespace Protobuf/Internal

namespace {
  class ProtobufException extends \Exception {
    public function Error(): \Errors\Error {
      return \Errors\Error($this->getMessage());
    }
  }
}
