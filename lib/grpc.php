<?hh // strict

namespace Grpc {

interface ClientConn {
	// TODO calloptions.
	public function Invoke(string $method, \Protobuf\Internal\Message $in, \Protobuf\Internal\Message $out): Awaitable<void>;
}

type Handler = (function(\Protobuf\Internal\Message): \Protobuf\Internal\Message);

interface Server {
	public function RegisterService(
		string $name,
		dict<string, (function(\Protobuf\Internal\Message): \Protobuf\Internal\Message)> $handlers
	): void;
}

} // namespace Grpc
