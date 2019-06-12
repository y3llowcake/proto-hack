<?hh // strict

namespace Grpc {
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
      }
      return "Unknown";
    }

    public static function FromInt(int $code): Code {
      if ($code < 0 || $code > 16) {
        return \Grpc\Codes::Unknown;
      }
      return $code;
    }
  }

  class GrpcException extends \Exception {
    public Code $grpc_code;
    public string $grpc_message;
    public function __construct(Code $code, string $message) {
      parent::__construct(
        \sprintf(
          "grpc exception: %s (%d); %s",
          Codes::Name($code),
          $code,
          $message,
        ),
        $code,
      );
      $this->grpc_code = $code;
      $this->grpc_message = $message;
    }
  }

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
      /*
      $out = dict[];
      foreach ($this->m as $k => $vs) {
        $nvs = vec[];
        foreach ($vs as $v) {
          $nvs[] = $v;
        }
        $out[$k] = $nvs;
      }
      return new Metadata($out);
			 */
      return new Metadata($this->m);
    }
  }

  interface Context { // Copy-on-write immutable.
    public function IncomingMetadata(): Metadata;
    public function WithTimeoutMicros(int $to): Context;
    public function WithOutgoingMetadata(Metadata $m): Context;
	}

  interface CallOption {}

  use \Protobuf\Message;

  interface ClientConn {
    public function Invoke(
      Context $ctx,
      string $method,
      Message $in,
      Message $out,
      CallOption ...$co
    ): Awaitable<void>;
  }

  interface Unmarshaller {
    public function Unmarshal(Message $into): void;
  }

  class BinaryUnmarshaller implements Unmarshaller {
    public function __construct(private string $raw) {}
    public function Unmarshal(Message $into): void {
      \Protobuf\Unmarshal($this->raw, $into);
    }
  }

  class JsonUnmarshaller implements Unmarshaller {
    public function __construct(private string $raw) {}
    public function Unmarshal(Message $into): void {
      \Protobuf\UnmarshalJson($this->raw, $into);
    }
  }

  type MethodHandler = (function(Context, Unmarshaller): Message);

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
    ): Message;
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

    public function RegisterService(ServiceDesc $sd): void {
      if (\array_key_exists($sd->name, $this->services)) {
        throw new \Exception(
          \sprintf("duplicate gRPC service entry for: %s", $sd->name),
        );
      }
      $methods = dict[];
      foreach ($sd->methods as $m) {
        $methods[$m->name] = $m->handler;
      }
      $this->services[$sd->name] = $methods;
    }

    private static function SplitFQMethod(string $fq): (string, string) {
      // Strip leading slash, if any.
      $fq = \ltrim($fq, '/');
      $parts = \explode('/', $fq, 2);
      if (\count($parts) < 2) {
        throw new \Grpc\GrpcException(
          \Grpc\Codes::InvalidArgument,
          \sprintf("invalid fully qualified gRPC method name: '%s'", $fq),
        );
      }
      return tuple($parts[0], $parts[1]);
    }

    public function Dispatch(
      Context $ctx,
      string $fqmethod,
      Unmarshaller $unm,
    ): Message {
      list($service_name, $method_name) = Server::SplitFQMethod($fqmethod);
      if (!\array_key_exists($service_name, $this->services)) {
        throw new \Grpc\GrpcException(
          \Grpc\Codes::Unimplemented,
          \sprintf(
            "service not implemented: '%s' for '%s'",
            $service_name,
            $fqmethod,
          ),
        );
      }
      $service = $this->services[$service_name];
      if (!\array_key_exists($method_name, $service)) {
        throw new \Grpc\GrpcException(
          \Grpc\Codes::Unimplemented,
          \sprintf(
            "method not implemented: '%s' for '%s'",
            $method_name,
            $fqmethod,
          ),
        );
      }
      $handler = $service[$method_name];
      if ($this->interceptor !== null) {
        return $this->interceptor
          ->ServerIntercept($ctx, $service_name, $method_name, $unm, $handler);
      }
      return $handler($ctx, $unm);
    }
  }
}
// namespace Grpc
