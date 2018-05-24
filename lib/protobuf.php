<?hh // strict

namespace Protobuf {

  class ProtobufException extends \Exception {}

  interface Message {
    public function MergeFrom(Internal\Decoder $d): void;
    public function WriteTo(Internal\Encoder $e): void;
    // public function WriteJsonTo(Internal\JsonEncoder $e): void;
  }

  function Unmarshal(string $data, Message $message): void {
    $message->MergeFrom(Internal\Decoder::FromString($data));
  }

  function Marshal(Message $message): string {
    $e = new Internal\Encoder();
    $message->WriteTo($e);
    return (string) $e;
  }
  /*
   function MarshalJson(Message $message, int $opt = 0): string {
   $e = new Internal\JsonEncoder(new Internal\JsonEncodeOpt($opt));
   $message->WriteJsonTo($e);
   return (string) $e;
   }
   class JsonEncode {
   // https://developers.google.com/protocol-buffers/docs/proto3#json_options
   const PRETTY_PRINT = 1 << 1;
   const EMIT_DEFAULT_VALUES = 1 << 2;
   const PRESERVE_NAMES = 1 << 3;
   const ENUMS_AS_INTS = 1 << 4;
   }
   */
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
      return $this->readRaw($this->readVarint());
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
        throw new \Protobuf\ProtobufException(
          "buffer overrun while reading raw",
        );
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
        $this->writeString((string) $e);
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
  /*
   class JsonEncodeOpt {
   public bool $pretty_print;
   public bool $emit_default_values;
   public bool $preserve_names;
   public bool $enums_as_ints;
   public function __construct(int $opt) {
   $this->pretty_print =
   (bool) ($opt & \Protobuf\JsonEncode::PRETTY_PRINT);
   $this->emit_default_values =
   (bool) ($opt & \Protobuf\JsonEncode::EMIT_DEFAULT_VALUES);
   $this->preserve_names = (bool) ($opt &
   \Protobuf\JsonEncode::PRESERVE_NAMES);
   $this->enums_as_ints = (bool) ($opt &
   \Protobuf\JsonEncode::ENUMS_AS_INTS);
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
   private function encodeMessage(
   ?\Protobuf\Message $m,
   ): dict<string, mixed> {
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
   ): void {
   $a = $this->encodeMessage($value);
   if (count($a) != 0 || $this->o->emit_default_values) {
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
   if (count($as) != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $as;
   }
   }
   public function writeInt(string $oname, string $cname, int $value): void {
   if ($value != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
   }
   }
   public static function encodeFloat(float $value): mixed {
   if (is_finite($value)) {
   return $value;
   }
   if (is_nan($value)) {
   return "NaN";
   }
   return $value > 0 ? "Infinity" : "-Infinity";
   }
   public function writeFloat(
   string $oname,
   string $cname,
   float $value,
   ): void {
   if ($value != 0.0 || $this->o->emit_default_values) {
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
   if (count($vs) != 0 || $this->o->emit_default_values) {
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
   if (count($vs) != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
   }
   }
   public function writeBool(
   string $oname,
   string $cname,
   bool $value,
   ): void {
   if ($value != false || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
   }
   }
   public function writeString(
   string $oname,
   string $cname,
   string $value,
   ): void {
   if ($value != '' || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
   }
   }
   public function writePrimitiveList(
   string $oname,
   string $cname,
   vec $value,
   ): void {
   if (count($value) != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
   }
   }
   private function encodeEnum(dict<int, string> $itos, int $v): mixed {
   return $this->o->enums_as_ints ? $v : $itos[$v];
   }
   public function writeEnum(
   string $oname,
   string $cname,
   dict<int, string> $itos,
   int $value,
   ): void {
   if ($value != 0 || $this->o->emit_default_values) {
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
   if (count($vs) != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
   }
   }
   public function writePrimitiveMap(
   string $oname,
   string $cname,
   dict $value,
   ): void {
   if (count($value) != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $value;
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
   if (count($vs) != 0 || $this->o->emit_default_values) {
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
   if (count($vs) != 0 || $this->o->emit_default_values) {
   $this->a[$this->o->preserve_names ? $oname : $cname] = $vs;
   }
   }
   public function __toString(): string {
   $opt = JSON_PARTIAL_OUTPUT_ON_ERROR;
   if ($this->o->pretty_print) {
   $opt |= JSON_PRETTY_PRINT;
   }
   return json_encode($this->a, $opt);
   }
   }
   */
}
// namespace Protobuf/Internal
