<?hh // strict

namespace Grpc {

interface ClientConn {
	// TODO calloptions.
	public function Invoke(string $method, \Protobuf\Internal\Message $in, \Protobuf\Internal\Message $out): Awaitable<void>;
}

} // namespace Grpc
