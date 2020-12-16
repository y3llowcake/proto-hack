<?hh // strict

namespace Errors {
  interface Error extends \Stringish {
    public function Ok(): bool;
    public function MustOk(): void;
    public function Error(): string;
  }

  <<__Memoize>>
  function Ok(): Error {
    return new \OkImpl();
  }

  function Error(string $s): Error {
    return new \ErrImpl($s);
  }

  function Errorf(\HH\FormatString<\PlainSprintf> $f, mixed ...$v): Error {
    return Error(\vsprintf($f, $v));
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
    return new \ResultImpl<Tvv>($v, Ok());
  }

  function ResultE<Tvv>(\Errors\Error $e): Result<Tvv> {
    invariant(!$e->Ok(), "ResultE called with ok error");
    return new \ResultImpl<Tvv>(null, $e);
  }
}

namespace {
  class OkImpl implements Errors\Error {
    public function __construct() {}
    public function Ok(): bool {
      return true;
    }
    public function MustOk(): void {}
    public function Error(): string {
      return "OK";
    }
    public function __toString(): string {
      return "OK";
    }
  }

  class ErrImpl implements Errors\Error {
    public function __construct(private string $err) {}
    public function Ok(): bool {
      return false;
    }
    public function MustOk(): void {
      throw new Exception(\sprintf('error not ok: %s', $this->err));
    }
    public function Error(): string {
      return $this->err;
    }
    public function __toString(): string {
      return $this->err;
    }
  }

  class ResultImpl<Tv> implements Errors\Result<Tv> {
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
        $this->error->Error(),
      );
      return $this->value;
    }
    public function Value(): ?Tv {
      return $this->value;
    }
    public function Error(): Errors\Error {
      return $this->error;
    }
    public function As<Tvv super Tv>(): Errors\Result<Tvv> {
      return new ResultImpl<Tvv>($this->value, $this->error);
    }
  }
}
