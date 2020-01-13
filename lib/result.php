<?hh // strict

namespace Result {
  interface Error extends \Stringish {
    public function Ok(): bool;
    public function Error(
    ): string; // Maybe remove this if stringish_cast is no longer nessecary.
  }

  <<__Memoize>>
  function Ok(): Error {
    return new \Ok();
  }

  function Error(string $s): Error {
    return new \Err($s);
  }

  function Errorf(\HH\FormatString<\PlainSprintf> $f, mixed ...$v): Error {
    /* HH_IGNORE_ERROR[4027] */
    return Error(\sprintf($f, ...$v));
  }

  final class Result<Tv> {
    private function __construct(public ?Tv $value, public Error $error) {}
    public function MustValue(): Tv {
      invariant(
        $this->value !== null,
        "result is null; error: '%s'",
        $this->error,
      );
      return $this->value;
    }
    public static function Value<Tvv>(Tvv $v): Result<Tvv> {
      return new Result<Tvv>($v, Ok());
    }
    public static function Error<Tvv>(string $s): Result<Tvv> {
      return new Result<Tvv>(null, Error($s));
    }
    public static function Errorf<Tvv>(
      \HH\FormatString<\PlainSprintf> $f,
      mixed ...$v
    ): Result<Tvv> {
      /* HH_IGNORE_ERROR[4027] */
      return self::Error<Tvv>(\sprintf($f, ...$v));
    }
  }
}

namespace {
  class Ok implements \Result\Error {
    public function __construct() {}
    public function Ok(): bool {
      return true;
    }
    public function Error(): string {
      return "OK";
    }
    public function __toString(): string {
      return "OK";
    }
  }

  class Err implements \Result\Error {
    public function __construct(private string $err) {}
    public function Ok(): bool {
      return false;
    }
    public function Error(): string {
      return $this->err;
    }
    public function __toString(): string {
      return $this->err;
    }
  }
}
