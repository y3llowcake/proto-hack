<?hh // strict

namespace Protobuf {

	function AnyUnmarshal<T as Message>(\google\protobuf\Any $any, T $message): void {
		Unmarshal($any->value, $message);
	}

	function AnyMarshal<T as Message>(T $message, string $type_url_prefix='type.googleapis.com/'): \google\protobuf\Any {
		$any = new \google\protobuf\Any();
		$any->type_url = $type_url_prefix.MessageName($message);
		$any->value = Marshal($message);
		return $any;
	}

	function AnyMessageName(\google\protobuf\Any $any): ?string {
		$parts = explode('/', $any->type_url);
		if (count($parts) != 2) {
			return null;
		}
		return $parts[1];
	}

	function AnyIs<T as Message>(\google\protobuf\Any $any, classname<T> $cls): bool {
		return AnyMessageName($any) === ClassNameToMessageName($cls);
	}

} // namespace Protobuf

namespace {

	function ClassNameToMessageName(classname<T> $cls): string {
		return str_replace('\\', '.', $cls);
	}

	function MessageName<T as Message>(T $message): string {
		return ClassNameToMessageName(get_class($message));
	}

} // namespace
