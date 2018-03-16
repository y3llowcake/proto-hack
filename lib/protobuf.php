<?hh // strict
namespace Protobuf\Internal;

class ProtoException extends \Exception {}

function AssertEndiannessAndIntSize(): void {
	if (PHP_INT_SIZE != 8) {
		throw new ProtoException("unsupported PHP_INT_SIZE size: " . PHP_INT_SIZE);
	}
	// TODO assert endianness...
}

// https://developers.google.com/protocol-buffers/docs/encoding
class Decoder {
	private function __construct(
		private string $buf,
		private int $offset,
		private int $len
	){
	}

	public static function FromString(string $buf): Decoder {
		return new Decoder($buf, 0, strlen($buf));
	}
	
	public function readVarInt128(): int {
		$val = 0;
		$shift = 0;
		while (true) {
			if ($this->isEOF()){
				throw new ProtoException("buffer overrun while reading varint-128");
			}
			$c = ord($this->buf[$this->offset]);
			$val += (($c & 127) << $shift);
			$shift+=7;
			$this->offset++;
			if ($c < 128) {
				break;
			}
		}
		return $val;
	}

	// returns (field number, wire type)
	public function readTag(): (int, int) {
		$k = $this->readVarInt128();		
		return tuple($k >> 3, $k & 0x7);
	}

	public function readLittleEndianInt(int $size): int {
		$noff = $this->offset + $size;
		if ($noff > $this->len){
			throw new ProtoException("buffer overrun while reading little endian int: " . $size);
		}
		$val = 0;
		for ($i = 0; $i < $size; $i++) {
			$val |= ord($this->buf[$this->offset]) << ($i * 8);
			$this->offset++;
		}
		return $val;
	}

	public function readBool(): bool {
		return $this->readVarInt128() != 0;
	}

	public function readFloat(): float {
		return unpack('f', $this->readRaw(4))[1];
	}

	public function readDouble(): float {
		return unpack('d', $this->readRaw(8))[1];
	}

	public function readString(): string {
		return $this->readRaw($this->readVarInt128());
	}

	public function readVarInt128ZigZag(): int {
		$i = $this->readVarInt128();
		return ($i >> 1) ^ - ($i & 1);
	}

	private function readRaw(int $size): string {
		$noff = $this->offset + $size;
		if ($noff > $this->len){
			throw new ProtoException("buffer overrun while reading raw: " . $size);
		}
		$ss = substr($this->buf, $this->offset, $size);
		$this->offset = $noff;
		return $ss;
	}

	public function readDecoder(): Decoder {
		$size = $this->readVarInt128();
		$noff = $this->offset + $size;
		if ($noff > $this->len){
			throw new ProtoException("buffer overrun while reading buffer: " . $size);
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
			$this->readVarInt128(); // We could technically optimize this to skip.
			break;
		case 1:
			$this->skip(8);
			break;
		case 2:
			$this->skip($this->readVarInt128());
			break;
		case 5:
			$this->skip(4);
			break;
		default:
			throw new ProtoException("encountered unknown wire type $wt during skip");
		}
	}

	private function skip(int $len): void {
		$this->offset += $len;
	}
}

class Encoder {
	private string $buf;
	public function __construct() {
		$this->buf = "";
	}

	public function writeVarInt128(int $i): void {
		while (true) {
			$b = $i & 0x7F; /* lower 7 bits */
			$i = $i >> 7;
			if ($i == 0) {
				$this->buf .= chr($b);
				break;
			}
			$this->buf .= chr($b | 0x80); /* set the top bit */
		}
	}

	public function writeTag(int $fn, int $wt): void {
		$this->writeVarInt128(($fn << 3) | $wt);		
	}

	public function writeLittleEndianInt(int $i, int $size): void {
		for ($j = 0; $j < $size; $j++) {
			$this->buf .= chr($i & 0xFF);
			$i = $i >> 8;
		}
	}

	public function writeBool(bool $b): void {
		$this->buf .= $b ? chr(0x01) : chr(0x00);
	}

	public function writeFloat(float $f): void {
		$this->buf .= pack('f', $f);
	}

	public function writeDouble(float $d): void {
		$this->buf .= pack('d', $d);
	}

	public function writeString(string $s): void {
		$this->writeVarInt128(strlen($s));
		$this->buf .= $s;
	}

	public function writeVarInt128ZigZag(int $i): void {
		$this->writeVarInt128(($i << 1) ^ ($i >> 31));
	}

	public function isEmpty(): bool {
		return strlen($this->buf) == 0;
	}

	public function __toString(): string {
		return $this->buf;
	}
}


abstract class Message {
	public abstract function MergeFrom(Decoder $d): void;
	public abstract function WriteTo(Encoder $e): void;
}

function Unmarshal(string $data, Message $message): void {
	$message->MergeFrom(Decoder::FromString($data));
}

function Marshal(Message $message): string {
	$e = new Encoder();
	$message->WriteTo($e);
	return (string)$e;
}
