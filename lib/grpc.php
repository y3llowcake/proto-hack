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
  }

  class GrpcException extends \Exception {
    public Code $grpc_code;
    public string $grpc_message;
    public function __construct(Code $code, string $message) {
      parent::__construct(
        \sprintf("grpc exception: %d; %s", $code, $message),
        $code,
      );
      $this->grpc_code = $code;
      $this->grpc_message = $message;
    }
  }

  interface CallOption {}

  interface Context {}

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

  type DecoderFunc = (function(Message): void);
  function DefaultDecoderFunc(string $raw): DecoderFunc {
    return function(Message $m): void use ($raw) {
      \Protobuf\Unmarshal($raw, $m);
    };
  }

  type MethodHandler = (function(Context, DecoderFunc): Message);

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

  class Server {
    // A map from service names, to method names, to method handlers.
    protected dict<string, dict<string, MethodHandler>> $services;
    public function __construct() {
      $this->services = dict[];
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
      DecoderFunc $dec,
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
      $method = $service[$method_name];
      return $method($ctx, $dec);
    }
  }
}
// namespace Grpc
