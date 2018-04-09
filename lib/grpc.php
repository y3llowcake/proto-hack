<?hh // strict

namespace Grpc {

  class GrpcException extends \Exception {}

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

  interface ServerDispatch {
    public function Name(): string;
    public function Dispatch(string $method, string $rawin): string;
  }

}
// namespace Grpc
