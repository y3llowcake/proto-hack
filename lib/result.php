<?hh // strict

namespace Result {
  interface Error extends \Stringish {
    public function Ok(): bool;
    public function Error(
    ): string; // Maybe remove this if stringish_cast is no longer nessecary.
  }
  interface GenericResult<Tv, Te as Error> extends Error {
    public function Value(): ?Tv;
    public function MustValue(): Tv;
  }
  type Result<Tv> = GenericResult<Tv, Error>;
  <<__Memoize>>
  function Ok(): Result<void> {
    return new BaseResult<void, Error>(null, null);
  }

  function Error<Tv>(string $s): Result<Tv> {
    return new BaseResult(null, $s);
  }
  function Errorf<Tv>(
    \HH\FormatString<\PlainSprintf> $f,
    mixed ...$v
  ): Result<Tv> {
    /* HH_IGNORE_ERROR[4027] */
    return Error<Tv>(\sprintf($f, ...$v));
  }
  function Value<Tv>(Tv $v): Result<Tv> {
    return new BaseResult($v, null);
  }

  class BaseResult<Tv, Te as Error> implements GenericResult<Tv, Te> {
    public function __construct(private ?Tv $v, private ?string $e) {}
    final public function Ok(): bool {
      return $this->e === null;
    }
    final public function __toString(): string {
      return $this->Error();
    }
    final public function Error(): string {
      return $this->e === null ? "OK" : $this->e;
    }
    final public function Value(): ?Tv {
      return $this->v;
    }
    final public function MustValue(): Tv {
      invariant($this->v !== null, "result is null; error: '%s'", $this);
      return $this->v;
    }
  }
}
