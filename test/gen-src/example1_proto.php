<?hh // strict
namespace foo\bar;

// Generated by the protocol buffer compiler.  DO NOT EDIT!
// Source: example1.proto

newtype XXX_AEnum1_t as int = int;
class AEnum1 {
  const XXX_AEnum1_t A = 0;
  const XXX_AEnum1_t B = 2;
  private static dict<int, string> $itos = dict[
    0 => 'A',
    2 => 'B',
  ];
  public static function NumbersToNames(): dict<int, string> {
    return self::$itos;
  }
  public static function FromInt(int $i): XXX_AEnum1_t {
    return $i;
  }
}

class example2 implements \Protobuf\Message {
  public int $aint32;

  public function __construct() {
    $this->aint32 = 0;
  }

  public function MergeFrom(\Protobuf\Internal\Decoder $d): void {
    while (!$d->isEOF()){
      list($fn, $wt) = $d->readTag();
      switch ($fn) {
        case 1:
          $this->aint32 = $d->readVarint32Signed();
          break;
        default:
          $d->skipWireType($wt);
      }
    }
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    if ($this->aint32 !== 0) {
      $e->writeTag(1, 0);
      $e->writeVarint($this->aint32);
    }
  }
  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeInt32('aint32', 'aint32', $this->aint32, false);
  }
  public function MergeJsonFrom(\Protobuf\Internal\JsonDecoder $d): void {
  }
}

newtype XXX_example1_AEnum2_t as int = int;
class example1_AEnum2 {
  const XXX_example1_AEnum2_t C = 0;
  const XXX_example1_AEnum2_t D = 10;
  private static dict<int, string> $itos = dict[
    0 => 'C',
    10 => 'D',
  ];
  public static function NumbersToNames(): dict<int, string> {
    return self::$itos;
  }
  public static function FromInt(int $i): XXX_example1_AEnum2_t {
    return $i;
  }
}

newtype XXX_example1_aoneof_enum_t = int;
interface example1_aoneof {
  const XXX_example1_aoneof_enum_t XXX_NOT_SET = 0;
  const XXX_example1_aoneof_enum_t oostring = 60;
  const XXX_example1_aoneof_enum_t ooint = 61;
  public function WhichOneof(): XXX_example1_aoneof_enum_t;
  public function WriteTo(\Protobuf\Internal\Encoder $e): void;
  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void;
}

class XXX_example1_aoneof_NOT_SET implements example1_aoneof {
  public function WhichOneof(): XXX_example1_aoneof_enum_t {
    return self::XXX_NOT_SET;
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {}

  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {}
}
class example1_oostring implements example1_aoneof {
  public function __construct(public string $oostring) {}

  public function WhichOneof(): XXX_example1_aoneof_enum_t {
    return self::oostring;
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    $e->writeTag(60, 2);;
    $e->writeString($this->oostring);
  }

  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeString('oostring', 'oostring', $this->oostring, true);
  }
}

class example1_ooint implements example1_aoneof {
  public function __construct(public int $ooint) {}

  public function WhichOneof(): XXX_example1_aoneof_enum_t {
    return self::ooint;
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    $e->writeTag(61, 0);;
    $e->writeVarint($this->ooint);
  }

  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeInt32('ooint', 'ooint', $this->ooint, true);
  }
}

class example1_example2 implements \Protobuf\Message {
  public string $astring;

  public function __construct() {
    $this->astring = '';
  }

  public function MergeFrom(\Protobuf\Internal\Decoder $d): void {
    while (!$d->isEOF()){
      list($fn, $wt) = $d->readTag();
      switch ($fn) {
        case 1:
          $this->astring = $d->readString();
          break;
        default:
          $d->skipWireType($wt);
      }
    }
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    if ($this->astring !== '') {
      $e->writeTag(1, 2);
      $e->writeString($this->astring);
    }
  }
  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeString('astring', 'astring', $this->astring, false);
  }
  public function MergeJsonFrom(\Protobuf\Internal\JsonDecoder $d): void {
  }
}

class example1_AmapEntry implements \Protobuf\Message {
  public string $key;
  public string $value;

  public function __construct() {
    $this->key = '';
    $this->value = '';
  }

  public function MergeFrom(\Protobuf\Internal\Decoder $d): void {
    while (!$d->isEOF()){
      list($fn, $wt) = $d->readTag();
      switch ($fn) {
        case 1:
          $this->key = $d->readString();
          break;
        case 2:
          $this->value = $d->readString();
          break;
        default:
          $d->skipWireType($wt);
      }
    }
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    if ($this->key !== '') {
      $e->writeTag(1, 2);
      $e->writeString($this->key);
    }
    if ($this->value !== '') {
      $e->writeTag(2, 2);
      $e->writeString($this->value);
    }
  }
  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeString('key', 'key', $this->key, false);
    $e->writeString('value', 'value', $this->value, false);
  }
  public function MergeJsonFrom(\Protobuf\Internal\JsonDecoder $d): void {
  }
}

class example1_Amap2Entry implements \Protobuf\Message {
  public string $key;
  public ?\fiz\baz\example2 $value;

  public function __construct() {
    $this->key = '';
    $this->value = null;
  }

  public function MergeFrom(\Protobuf\Internal\Decoder $d): void {
    while (!$d->isEOF()){
      list($fn, $wt) = $d->readTag();
      switch ($fn) {
        case 1:
          $this->key = $d->readString();
          break;
        case 2:
          if ($this->value == null) {
            $this->value = new \fiz\baz\example2();
          }
          $this->value->MergeFrom($d->readDecoder());
          break;
        default:
          $d->skipWireType($wt);
      }
    }
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    if ($this->key !== '') {
      $e->writeTag(1, 2);
      $e->writeString($this->key);
    }
    $msg = $this->value;
    if ($msg != null) {
      $nested = new \Protobuf\Internal\Encoder();
      $msg->WriteTo($nested);
      $e->writeEncoder($nested, 2);
    }
  }
  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeString('key', 'key', $this->key, false);
    $e->writeMessage('value', 'value', $this->value, false);
  }
  public function MergeJsonFrom(\Protobuf\Internal\JsonDecoder $d): void {
  }
}

class example1 implements \Protobuf\Message {
  public float $adouble;
  public float $afloat;
  public int $aint32;
  public int $aint64;
  public int $auint32;
  public int $auint64;
  public int $asint32;
  public int $asint64;
  public int $afixed32;
  public int $afixed64;
  public int $asfixed32;
  public int $asfixed64;
  public bool $abool;
  public string $astring;
  public string $abytes;
  public \foo\bar\XXX_AEnum1_t $aenum1;
  public \foo\bar\XXX_example1_AEnum2_t $aenum2;
  public \fiz\baz\XXX_AEnum2_t $aenum22;
  public vec<string> $manystring;
  public vec<int> $manyint64;
  public ?\foo\bar\example1_example2 $aexample2;
  public ?\foo\bar\example2 $aexample22;
  public ?\fiz\baz\example2 $aexample23;
  public dict<string, string> $amap;
  public dict<string, ?\fiz\baz\example2> $amap2;
  public int $outoforder;
  public ?\google\protobuf\Any $anany;
  public example1_aoneof $aoneof;

  public function __construct() {
    $this->adouble = 0.0;
    $this->afloat = 0.0;
    $this->aint32 = 0;
    $this->aint64 = 0;
    $this->auint32 = 0;
    $this->auint64 = 0;
    $this->asint32 = 0;
    $this->asint64 = 0;
    $this->afixed32 = 0;
    $this->afixed64 = 0;
    $this->asfixed32 = 0;
    $this->asfixed64 = 0;
    $this->abool = false;
    $this->astring = '';
    $this->abytes = '';
    $this->aenum1 = \foo\bar\AEnum1::A;
    $this->aenum2 = \foo\bar\example1_AEnum2::C;
    $this->aenum22 = \fiz\baz\AEnum2::Z;
    $this->manystring = vec[];
    $this->manyint64 = vec[];
    $this->aexample2 = null;
    $this->aexample22 = null;
    $this->aexample23 = null;
    $this->amap = dict[];
    $this->amap2 = dict[];
    $this->outoforder = 0;
    $this->anany = null;
    $this->aoneof = new XXX_example1_aoneof_NOT_SET();
  }

  public function MergeFrom(\Protobuf\Internal\Decoder $d): void {
    while (!$d->isEOF()){
      list($fn, $wt) = $d->readTag();
      switch ($fn) {
        case 1:
          $this->adouble = $d->readDouble();
          break;
        case 2:
          $this->afloat = $d->readFloat();
          break;
        case 3:
          $this->aint32 = $d->readVarint32Signed();
          break;
        case 4:
          $this->aint64 = $d->readVarint();
          break;
        case 5:
          $this->auint32 = $d->readVarint32();
          break;
        case 6:
          $this->auint64 = $d->readVarint32();
          break;
        case 7:
          $this->asint32 = $d->readVarintZigZag32();
          break;
        case 8:
          $this->asint64 = $d->readVarintZigZag64();
          break;
        case 9:
          $this->afixed32 = $d->readLittleEndianInt32Unsigned();
          break;
        case 10:
          $this->afixed64 = $d->readLittleEndianInt64();
          break;
        case 11:
          $this->asfixed32 = $d->readLittleEndianInt32Signed();
          break;
        case 12:
          $this->asfixed64 = $d->readLittleEndianInt64();
          break;
        case 13:
          $this->abool = $d->readBool();
          break;
        case 14:
          $this->astring = $d->readString();
          break;
        case 15:
          $this->abytes = $d->readString();
          break;
        case 20:
          $this->aenum1 = \foo\bar\AEnum1::FromInt($d->readVarint());
          break;
        case 21:
          $this->aenum2 = \foo\bar\example1_AEnum2::FromInt($d->readVarint());
          break;
        case 22:
          $this->aenum22 = \fiz\baz\AEnum2::FromInt($d->readVarint());
          break;
        case 30:
          $this->manystring []= $d->readString();
          break;
        case 31:
          if ($wt == 2) {
            $packed = $d->readDecoder();
            while (!$packed->isEOF()) {
              $this->manyint64 []= $packed->readVarint();
            }
          } else {
            $this->manyint64 []= $d->readVarint();
          }
          break;
        case 40:
          if ($this->aexample2 == null) {
            $this->aexample2 = new \foo\bar\example1_example2();
          }
          $this->aexample2->MergeFrom($d->readDecoder());
          break;
        case 41:
          if ($this->aexample22 == null) {
            $this->aexample22 = new \foo\bar\example2();
          }
          $this->aexample22->MergeFrom($d->readDecoder());
          break;
        case 42:
          if ($this->aexample23 == null) {
            $this->aexample23 = new \fiz\baz\example2();
          }
          $this->aexample23->MergeFrom($d->readDecoder());
          break;
        case 49:
          $this->outoforder = $d->readVarint();
          break;
        case 51:
          $obj = new \foo\bar\example1_AmapEntry();
          $obj->MergeFrom($d->readDecoder());
          $this->amap[$obj->key] = $obj->value;
          break;
        case 52:
          $obj = new \foo\bar\example1_Amap2Entry();
          $obj->MergeFrom($d->readDecoder());
          $this->amap2[$obj->key] = $obj->value;
          break;
        case 60:
          $this->aoneof = new example1_oostring($d->readString());
          break;
        case 61:
          $this->aoneof = new example1_ooint($d->readVarint32Signed());
          break;
        case 80:
          if ($this->anany == null) {
            $this->anany = new \google\protobuf\Any();
          }
          $this->anany->MergeFrom($d->readDecoder());
          break;
        default:
          $d->skipWireType($wt);
      }
    }
  }

  public function WriteTo(\Protobuf\Internal\Encoder $e): void {
    if ($this->adouble !== 0.0) {
      $e->writeTag(1, 1);
      $e->writeDouble($this->adouble);
    }
    if ($this->afloat !== 0.0) {
      $e->writeTag(2, 5);
      $e->writeFloat($this->afloat);
    }
    if ($this->aint32 !== 0) {
      $e->writeTag(3, 0);
      $e->writeVarint($this->aint32);
    }
    if ($this->aint64 !== 0) {
      $e->writeTag(4, 0);
      $e->writeVarint($this->aint64);
    }
    if ($this->auint32 !== 0) {
      $e->writeTag(5, 0);
      $e->writeVarint($this->auint32);
    }
    if ($this->auint64 !== 0) {
      $e->writeTag(6, 0);
      $e->writeVarint($this->auint64);
    }
    if ($this->asint32 !== 0) {
      $e->writeTag(7, 0);
      $e->writeVarintZigZag32($this->asint32);
    }
    if ($this->asint64 !== 0) {
      $e->writeTag(8, 0);
      $e->writeVarintZigZag64($this->asint64);
    }
    if ($this->afixed32 !== 0) {
      $e->writeTag(9, 5);
      $e->writeLittleEndianInt32Unsigned($this->afixed32);
    }
    if ($this->afixed64 !== 0) {
      $e->writeTag(10, 1);
      $e->writeLittleEndianInt64($this->afixed64);
    }
    if ($this->asfixed32 !== 0) {
      $e->writeTag(11, 5);
      $e->writeLittleEndianInt32Signed($this->asfixed32);
    }
    if ($this->asfixed64 !== 0) {
      $e->writeTag(12, 1);
      $e->writeLittleEndianInt64($this->asfixed64);
    }
    if ($this->abool !== false) {
      $e->writeTag(13, 0);
      $e->writeBool($this->abool);
    }
    if ($this->astring !== '') {
      $e->writeTag(14, 2);
      $e->writeString($this->astring);
    }
    if ($this->abytes !== '') {
      $e->writeTag(15, 2);
      $e->writeString($this->abytes);
    }
    if ($this->aenum1 !== \foo\bar\AEnum1::A) {
      $e->writeTag(20, 0);
      $e->writeVarint($this->aenum1);
    }
    if ($this->aenum2 !== \foo\bar\example1_AEnum2::C) {
      $e->writeTag(21, 0);
      $e->writeVarint($this->aenum2);
    }
    if ($this->aenum22 !== \fiz\baz\AEnum2::Z) {
      $e->writeTag(22, 0);
      $e->writeVarint($this->aenum22);
    }
    foreach ($this->manystring as $elem) {
      $e->writeTag(30, 2);
      $e->writeString($elem);
    }
    $packed = new \Protobuf\Internal\Encoder();
    foreach ($this->manyint64 as $elem) {
      $packed->writeVarint($elem);
    }
    $e->writeEncoder($packed, 31);
    $msg = $this->aexample2;
    if ($msg != null) {
      $nested = new \Protobuf\Internal\Encoder();
      $msg->WriteTo($nested);
      $e->writeEncoder($nested, 40);
    }
    $msg = $this->aexample22;
    if ($msg != null) {
      $nested = new \Protobuf\Internal\Encoder();
      $msg->WriteTo($nested);
      $e->writeEncoder($nested, 41);
    }
    $msg = $this->aexample23;
    if ($msg != null) {
      $nested = new \Protobuf\Internal\Encoder();
      $msg->WriteTo($nested);
      $e->writeEncoder($nested, 42);
    }
    if ($this->outoforder !== 0) {
      $e->writeTag(49, 0);
      $e->writeVarint($this->outoforder);
    }
    foreach ($this->amap as $k => $v) {
      $obj = new \foo\bar\example1_AmapEntry();
      $obj->key = $k;
      $obj->value = $v;
      $nested = new \Protobuf\Internal\Encoder();
      $obj->WriteTo($nested);
      $e->writeEncoder($nested, 51);
    }
    foreach ($this->amap2 as $k => $v) {
      $obj = new \foo\bar\example1_Amap2Entry();
      $obj->key = $k;
      $obj->value = $v;
      $nested = new \Protobuf\Internal\Encoder();
      $obj->WriteTo($nested);
      $e->writeEncoder($nested, 52);
    }
    $msg = $this->anany;
    if ($msg != null) {
      $nested = new \Protobuf\Internal\Encoder();
      $msg->WriteTo($nested);
      $e->writeEncoder($nested, 80);
    }
    $this->aoneof->WriteTo($e);
  }
  public function WriteJsonTo(\Protobuf\Internal\JsonEncoder $e): void {
    $e->writeFloat('adouble', 'adouble', $this->adouble, false);
    $e->writeFloat('afloat', 'afloat', $this->afloat, false);
    $e->writeInt32('aint32', 'aint32', $this->aint32, false);
    $e->writeInt64Signed('aint64', 'aint64', $this->aint64, false);
    $e->writeInt32('auint32', 'auint32', $this->auint32, false);
    $e->writeInt32('auint64', 'auint64', $this->auint64, false);
    $e->writeInt32('asint32', 'asint32', $this->asint32, false);
    $e->writeInt64Signed('asint64', 'asint64', $this->asint64, false);
    $e->writeInt32('afixed32', 'afixed32', $this->afixed32, false);
    $e->writeInt64Unsigned('afixed64', 'afixed64', $this->afixed64, false);
    $e->writeInt32('asfixed32', 'asfixed32', $this->asfixed32, false);
    $e->writeInt64Signed('asfixed64', 'asfixed64', $this->asfixed64, false);
    $e->writeBool('abool', 'abool', $this->abool, false);
    $e->writeString('astring', 'astring', $this->astring, false);
    $e->writeString('abytes', 'abytes', $this->abytes, false);
    $e->writeEnum('aenum1', 'aenum1', \foo\bar\AEnum1::NumbersToNames(), $this->aenum1, false);
    $e->writeEnum('aenum2', 'aenum2', \foo\bar\example1_AEnum2::NumbersToNames(), $this->aenum2, false);
    $e->writeEnum('aenum22', 'aenum22', \fiz\baz\AEnum2::NumbersToNames(), $this->aenum22, false);
    $e->writePrimitiveList('manystring', 'manystring', $this->manystring);
    $e->writeInt64SignedList('manyint64', 'manyint64', $this->manyint64);
    $e->writeMessage('aexample2', 'aexample2', $this->aexample2, false);
    $e->writeMessage('aexample22', 'aexample22', $this->aexample22, false);
    $e->writeMessage('aexample23', 'aexample23', $this->aexample23, false);
    $e->writeInt64Signed('outoforder', 'outoforder', $this->outoforder, false);
    $e->writePrimitiveMap('amap', 'amap', $this->amap);
    $e->writeMessageMap('amap2', 'amap2', $this->amap2);
    $e->writeMessage('anany', 'anany', $this->anany, false);
    $this->aoneof->WriteJsonTo($e);
  }
  public function MergeJsonFrom(\Protobuf\Internal\JsonDecoder $d): void {
  }
}

class ExampleServiceClient {
  public function __construct(private \Grpc\ClientConn $cc) {
  }

  public async function OneToTwo(\Grpc\Context $ctx, \foo\bar\example1 $in, \Grpc\CallOption ...$co): Awaitable<\foo\bar\example2> {
    $out = new \foo\bar\example2();
    await $this->cc->Invoke($ctx, '/foo.bar.ExampleService/OneToTwo', $in, $out, ...$co);
    return $out;
  }
}

interface ExampleServiceServer {
  public function OneToTwo(\Grpc\Context $ctx, \foo\bar\example1 $in): \foo\bar\example2;
}

function RegisterExampleServiceServer(\Grpc\Server $server, ExampleServiceServer $service): void {
  $methods = vec[];
  $handler = function(\Grpc\Context $ctx, \Grpc\DecoderFunc $df): \Protobuf\Message use ($service) {
    $in = new \foo\bar\example1();
    $df($in);
    return $service->OneToTwo($ctx, $in);
  };
  $methods []= new \Grpc\MethodDesc('OneToTwo', $handler);
  $server->RegisterService(new \Grpc\ServiceDesc('foo.bar.ExampleService', $methods));
}

class XXX_FileDescriptor_example1__proto implements \Protobuf\Internal\FileDescriptor {
  const string NAME = 'example1.proto';
  const string RAW = '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';
  public function Name(): string {
    return self::NAME;
  }

  public function FileDescriptorProtoBytes(): string {
    return (string)\gzuncompress(\base64_decode(self::RAW));
  }
}
