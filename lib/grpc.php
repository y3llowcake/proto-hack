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
        sprintf("grpc exception: %d; %s", $code, $message),
        $code,
      );
      $this->grpc_code = $code;
      $this->grpc_message = $message;
    }
  }

  interface Codec {
    public function Marshal(\Protobuf\Message $m): string;
    public function Unmarshal(string $s, \Protobuf\Message $m): void;
  }

  final class DefaultCodec implements Codec {
    public function Marshal(\Protobuf\Message $m): string {
      return \Protobuf\Marshal($m);
    }
    public function Unmarshal(string $s, \Protobuf\Message $m): void {
      \Protobuf\Unmarshal($s, $m);
    }
  }

  use \Protobuf\Message;

  interface CallOption {}

  interface Context {}

  interface ClientConn {
    public function Invoke(
      Context $ctx,
      string $method,
      Message $in,
      Message $out,
      CallOption ...$co
    ): Awaitable<void>;
  }

  interface MethodDispatch {
    public function ServiceName(): string;
    public function DispatchMethod(
      Context $ctx,
      Codec $codec,
      string $method,
      string $rawin,
    ): string;
  }

}
// namespace Grpc
