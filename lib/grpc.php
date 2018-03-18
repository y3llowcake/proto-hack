<?hh // strict

namespace Grpc {

use \Protobuf\Message;

interface ClientConn {
	// TODO calloptions.
	public function Invoke(string $method, Message $in, Message $out): Awaitable<void>;
}

//type Handler = (function(\Protobuf\Message): \Protobuf\Message);

interface Server {
	public function RegisterService(
		string $name,
		dict<string, (function(Message): Message)> $handlers
	): void;
}

} // namespace Grpc
