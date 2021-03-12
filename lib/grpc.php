<?hh // strict

namespace Grpc {
  use type \Errors\{Error, Result};
  use function \Errors\{ResultE, ResultV};

  use \Protobuf\Message;


  newtype Code as int = int;
  abstract class Codes {
    const Code OK = 0;
    const Code Canceled = 1;
    const Code Unknown = 2;
    const Code InvalidArgument = 3;
    const Code DeadlineExceeded = 4;
    const Code NotFound = 5;
    const Code AlreadyExists = 6;
    const Code PermissionDenied = 7;
    const Code ResourceExhausted = 8;
    const Code FailedPrecondition = 9;
    const Code Aborted = 10;
    const Code OutOfRange = 11;
    const Code Unimplemented = 12;
    const Code Internal = 13;
    const Code Unavailable = 14;
    const Code DataLoss = 15;
    const Code Unauthenticated = 16;

    public static function Name(Code $code): string {
      switch ($code) {
        case Codes::OK:
          return "OK";
        case Codes::Canceled:
          return "Canceled";
        case Codes::InvalidArgument:
          return "InvalidArgument";
        case Codes::DeadlineExceeded:
          return "DeadlineExceeded";
        case Codes::NotFound:
          return "NotFound";
        case Codes::AlreadyExists:
          return "AlreadyExists";
        case Codes::ResourceExhausted:
          return "ResourceExhausted";
        case Codes::PermissionDenied:
          return "PermissionDenied";
        case Codes::FailedPrecondition:
          return "FailedPrecondition";
        case Codes::Aborted:
          return "Aborted";
        case Codes::OutOfRange:
          return "OutOfRange";
        case Codes::Unimplemented:
          return "Unimplemented";
        case Codes::Internal:
          return "Internal";
        case Codes::Unavailable:
          return "Unavailable";
        case Codes::DataLoss:
          return "DataLoss";
        case Codes::Unauthenticated:
          return "Unauthenticated";
        default:
          return "Unknown";
      }
    }

    public static function FromInt(int $code): Code {
      if ($code < 0 || $code > 16) {
        return \Grpc\Codes::Unknown;
      }
      return $code;
    }
  }

  namespace Status {
    interface Status extends \Errors\Error {
      public function Code(): \Grpc\Code;
      public function Message(): string;
      public function Details(): vec<\google\protobuf\Any>;
      public function WithDetails(\google\protobuf\Any ...$details): Status;
    }

    <<__Memoize>>
    function Ok(): Status {
      return new \GrpcStatus(\Grpc\Codes::OK, "OK");
    }

    function Error(\Grpc\Code $code, mixed $s): Status {
      if ($s is string) {
        return new \GrpcStatus($code, $s);
      }
      if ($s is \Errors\Error) {
        return new \GrpcStatus($code, $s->Error());
      }
      return new \GrpcStatus($code, (string)$s);
    }

    function Errorf(
      \Grpc\Code $code,
      \HH\FormatString<\PlainSprintf> $f,
      mixed ...$v
    ): Status {
      return Error($code, \vsprintf($f, $v));
    }

    function FromError(\Errors\Error $err): Status {
      if ($err is Status) {
        return $err;
      }
      return Error(\Grpc\Codes::Unknown, $err->Error());
    }
  }

  // Only the following ASCII characters are allowed in keys:
  //  - digits: 0-9
  //  - uppercase letters: A-Z (normalized to lower)
  //  - lowercase letters: a-z
  //  - special characters: -_.
  // Uppercase letters are automatically converted to lowercase.
  //
  // Keys beginning with "grpc-" are reserved for grpc-internal use only and may
  // result in errors if set in metadata.
  class Metadata { // Copy-on-write immutable
    private function __construct( // Does not sanitize keys.
      private dict<string, vec<string>> $m,
    ) {}

    public static function Empty(): Metadata {
      return new Metadata(dict[]);
    }

    public static function FromDict(dict<string, vec<string>> $d): Metadata {
      $nd = dict[];
      foreach ($d as $k => $vs) {
        $nd[\strtolower($k)] = $vs;
      }
      return new Metadata($nd);
    }

    public static function FromPair(string $k, string $v): Metadata {
      $d = dict[\strtolower($k) => vec[$v]];
      return new Metadata($d);
    }

    public static function Merge(Metadata $a, Metadata $b): Metadata {
      $c = $a->copy();
      foreach ($b->m as $k => $vs) {
        $nvs = $c->m[$k] ?? vec[];
        foreach ($vs as $v) {
          $nvs[] = $v;
        }
        $c->m[$k] = $nvs;
      }
      return $c;
    }

    public function GetFirst(string $k): ?string {
      $v = $this->m[\strtolower($k)] ?? vec[];
      if (\count($v) > 0) {
        return $v[0];
      }
      return null;
    }

    public function ToDict(): dict<string, vec<string>> {
      return $this->m;
    }

    private function copy(): Metadata {
      return new Metadata($this->m);
    }
  }

  interface Context { // Copy-on-write immutable.
    public function IncomingMetadata(): Metadata;
    public function WithTimeoutMicros(int $to): Context;
    public function WithOutgoingMetadata(Metadata $m): Context;
  }

  interface CallOption {}

  interface ClientInterceptor {
    public function ClientIntercept(
      Context $ctx,
      string $method,
      Message $in,
      Message $out,
      Invoker $invoker,
      CallOption ...$co
    ): Awaitable<Error>;
  }

  interface Invoker {
    public function Invoke(
      Context $ctx,
      string $method,
      Message $in,
      Message $out,
      CallOption ...$co
    ): Awaitable<Error>;
  }

  class ChainedClientInterceptor implements Invoker {
    public function __construct(
      private ClientInterceptor $intercept,
      private Invoker $invoke,
    ) {}
    public function Invoke(
      Context $ctx,
      string $method,
      Message $in,
      Message $out,
      CallOption ...$co
    ): Awaitable<Error> {
      return $this->intercept
        ->ClientIntercept($ctx, $method, $in, $out, $this->invoke, ...$co);
    }
  }

  interface Unmarshaller {
    public function Unmarshal(Message $into): Error;
  }

  class BinaryUnmarshaller implements Unmarshaller {
    public function __construct(private string $raw) {}
    public function Unmarshal(Message $into): Error {
      return \Protobuf\Unmarshal($this->raw, $into);
    }
  }

  class JsonUnmarshaller implements Unmarshaller {
    public function __construct(private string $raw) {}
    public function Unmarshal(Message $into): Error {
      return \Protobuf\UnmarshalJson($this->raw, $into);
    }
  }

  class CopyUnmarshaller implements Unmarshaller {
    public function __construct(private Message $from) {}
    public function Unmarshal(Message $into): Error {
      return \Protobuf\UnmarshalCopy($this->from, $into);
    }
  }

  type MethodHandler = (function(
    Context,
    Unmarshaller,
  ): Awaitable<Result<Message>>);

  class MethodDesc {
    public function __construct(
      public string $name,
      public MethodHandler $handler,
    ) {}
  }

  class ServiceDesc {
    public function __construct(
      public string $name,
      public vec<MethodDesc> $methods,
    ) {}
  }

  // TODO make this look more like standard APIs. (support chaining?)
  // Interceptors are responsible for invoking and returning the result of:
  // $handler($unmarshaller, $handler)
  interface ServerInterceptor {
    public function ServerIntercept(
      Context $ctx,
      string $service_name,
      string $method_name,
      Unmarshaller $unmarshaller,
      MethodHandler $handler,
    ): Awaitable<Result<Message>>;
  }

  use function \Errors\{Errorf, Ok};

  function SplitFQMethod(string $fq): Result<(string, string)> {
    // Strip leading slash, if any.
    $fq = \ltrim($fq, '/');
    $parts = \explode('/', $fq, 2);
    if (\count($parts) < 2) {
      return ResultE(
        Errorf("invalid fully qualified gRPC method name '%s'", $fq),
      );
    }
    return ResultV(tuple($parts[0], $parts[1]));
  }

  class Server {
    // A map from service names, to method names, to method handlers.
    protected dict<string, dict<string, MethodHandler>> $services;
    protected ?ServerInterceptor $interceptor;

    public function __construct() {
      $this->services = dict[];
      $this->interceptor = null;
    }

    public function SetInterceptor(ServerInterceptor $i): void {
      $this->interceptor = $i;
    }

    public function RegisterService(ServiceDesc $sd): Error {
      if (\array_key_exists($sd->name, $this->services)) {
        return Errorf("duplicate gRPC service entry: %s", $sd->name);
      }
      $methods = dict[];
      foreach ($sd->methods as $m) {
        $methods[$m->name] = $m->handler;
      }
      $this->services[$sd->name] = $methods;
      return Ok();
    }

    public async function Dispatch(
      Context $ctx,
      string $fqmethod,
      Unmarshaller $unm,
    ): Awaitable<Result<Message>> {
      $sm = SplitFQMethod($fqmethod);
      if (!$sm->Ok()) {
        return ResultE(Status\Error(Codes::InvalidArgument, $sm->Error()));
      }
      list($service_name, $method_name) = $sm->MustValue();

      if (!\array_key_exists($service_name, $this->services)) {
        return ResultE(Status\Errorf(
          Codes::Unimplemented,
          "service not implemented: '%s' for '%s'",
          $service_name,
          $fqmethod,
        ));
      }
      $service = $this->services[$service_name];
      if (!\array_key_exists($method_name, $service)) {
        return ResultE(Status\Errorf(
          Codes::Unimplemented,
          "method not implemented: '%s' for '%s'",
          $method_name,
          $fqmethod,
        ));
      }
      $handler = $service[$method_name];
      if ($this->interceptor !== null) {
        return await $this->interceptor
          ->ServerIntercept($ctx, $service_name, $method_name, $unm, $handler);
      }
      return await $handler($ctx, $unm);
    }
  }

  class LoopbackInvoker implements Invoker {
    public function __construct(private ServiceDesc $sd) {}
    public async function Invoke(
      Context $ctx,
      string $fqmethod,
      Message $in,
      Message $out,
      CallOption ...$co
    ): Awaitable<Error> {
      $sm = SplitFQMethod($fqmethod);
      if (!$sm->Ok()) {
        return $sm->Error();
      }
      list($service_name, $method_name) = $sm->MustValue();
      if ($service_name !== $this->sd->name) {
        return Status\Errorf(
          Codes::Unimplemented,
          "loopback service not implemented: '%s'",
          $service_name,
        );
      }
      foreach ($this->sd->methods as $m) {
        if ($m->name === $method_name) {
          // TODO: ctx needs to be rebuilt correctly.
          $handler = $m->handler;
          try {
            $ret = await $handler($ctx, new CopyUnmarshaller($in));
            if (!$ret->Ok()) {
              return $ret->Error();
            }
          } catch (\Throwable $t) {
            return Status\Errorf(
              Codes::Internal,
              "loopback invocation threw: '%s';\n%s",
              $t->getMessage(),
              $t->getTraceAsString(),
            );
          }
          return $out->CopyFrom($ret->MustValue());
        }
      }
      return Errorf("loopback method not implemented: '%s'", $method_name);
    }
  }
}
// namespace Grpc

namespace {
  class GrpcStatus implements \Grpc\Status\Status {
    private \Grpc\Code $code;
    private string $msg;
    private vec<\google\protobuf\Any> $details;

    public function __construct(\Grpc\Code $code, string $msg) {
      $this->code = $code;
      $this->msg = $msg;
      $this->details = vec[];
    }
    public function Ok(): bool {
      return $this->code == \Grpc\Codes::OK;
    }
    public function MustOk(): void {
      if (!$this->Ok()) {
        throw new Exception('error not ok: '.$this->Error());
      }
    }
    public function Error(): string {
      return \sprintf(
        "%s(%d): %s",
        \Grpc\Codes::Name($this->code),
        $this->code,
        $this->msg,
      );
    }
    public function Details(): vec<\google\protobuf\Any> {
      return $this->details;
    }
    public function WithDetails(\google\protobuf\Any ...$details): GrpcStatus {
      $o = new GrpcStatus($this->code, $this->msg);
      $o->details = $this->details;
      foreach ($details as $d) {
        $o->details[] = $d;
      }
      return $o;
    }
    public function __toString(): string {
      return $this->Error();
    }
    public function Message(): string {
      return $this->msg;
    }
    public function Code(): \Grpc\Code {
      return $this->code;
    }
  }
}
