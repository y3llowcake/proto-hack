<?hh // strict

namespace Errors {
  interface Error extends \Stringish {
    public function Ok(): bool;
    public function Error(): string;
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

  interface Result<Tv> {
    public function Ok(): bool;
    public function Value(): ?Tv;
    public function MustValue(): Tv;
    public function Error(): \Errors\Error;

    // TODO Consider abandoning this in favor of type constraints on generics?
    public function As<Tvv super Tv>(): Result<Tvv>;
  }

  function ResultV<Tvv>(Tvv $v): Result<Tvv> {
    return new \Result<Tvv>($v, Ok());
  }

  function ResultE<Tvv>(\Errors\Error $e): Result<Tvv> {
    invariant(!$e->Ok(), "ResultE called with ok error");
    return new \Result<Tvv>(null, $e);
  }
}

namespace {
  class Ok implements Errors\Error {
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

  class Err implements Errors\Error {
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

  class Result<Tv> implements Errors\Result<Tv> {
    public function __construct(
      private ?Tv $value,
      private Errors\Error $error,
    ) {}
    public function Ok(): bool {
      return $this->error->Ok();
    }
    public function MustValue(): Tv {
      invariant(
        $this->value !== null,
        "result is null; error: '%s'",
        $this->error,
      );
      return $this->value;
    }
    public function Value(): ?Tv {
      return $this->value;
    }
    public function Error(): Errors\Error {
      return $this->error;
    }
    public function As<Tvv super Tv>(): Result<Tvv> {
      return new Result($this->value, $this->error);
    }
  }
}
