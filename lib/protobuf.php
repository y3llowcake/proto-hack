<?hh // strict
namespace Protobuf\Internal;

class ProtoException extends \Exception {}

function AssertEndiannessAndIntSize() {
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

	// TODO make this faster.
	public function readFixedInt(bool $is64): int {
		$val = unpack('V', $this->readRaw(4))[1];
		if ($is64){
			$val = ($val << 32) | unpack('V', $this->readRaw(4));
		}
		return $val;
	}

	public function readFloat(): float {
		return unpack('f', $this->readRaw(4))[1];
	}

	public function readDouble(): float {
		return unpack('d', $this->readRaw(8))[1];
	}

	public function readRaw(int $size): string {
		$noff = $this->offset + $size;
		if ($noff > $this->len){
			throw new ProtoException("buffer overrun while reading raw: " . $size);
		}
		$ss = substr($this->buf, $this->offset, $size);
		$this->offset = $noff;
		return $ss;
	}

	public function readDecoder(int $size): Decoder {
		$noff = $this->offset + $size;
		if ($noff > $this->len){
			throw new ProtoException("buffer overrun while reading buffer: " . $size);
		}
		$buf = new Decoder($this->buf, $this->offset, $this->$noff);
		$this->offset = $noff;
		return $buf;
	}

	public function isEOF(): bool {
		return $this->offset >= $this->len;
	}

	public function skip(int $len) {
		$this->offset += $len;
	}
}

function ZigZagDecode(int $i): int {
	return ($i >> 1) ^ - ($i & 1);
}

function KeyToFieldNumber(int $k): int {
	return $k >> 3;
}

function KeyToWireType(int $k): int {
	return $k & 0x7;
}

function Skip(Decoder $d, int $wt) {
	switch ($wt) {
	case 0:
		$d->readVarInt128(); // We could technically optimize this to skip.
		break;
	case 1:
		$d->skip(8);
		break;
	case 2:
		$d->skip($d->readVarInt128());
		break;
	case 5:
		$d->skip(4);
		break;
	default:
		throw new ProtoException("encountered unknown wire type $wt during skip");
	}
}

abstract class Message {
	public abstract function Unmarshal(Decoder $buf);
}

function Unmarshal(string $data, Message $message): void {
	$message->Unmarshal(Decoder::FromString($data));
}
