<?hh // strict

namespace Protobuf {

  function AnyUnmarshal<T as Message>(
    \google\protobuf\Any $any,
    T $message,
  ): void {
    Unmarshal($any->value, $message);
  }

  function AnyMarshal<T as Message>(
    T $message,
    string $type_url_prefix = 'type.googleapis.com/',
  ): \google\protobuf\Any {
    $any = new \google\protobuf\Any();
    $any->type_url = $type_url_prefix . \MessageName($message);
    $any->value = Marshal($message);
    return $any;
  }

	function AnyMessageName(?\google\protobuf\Any $any): string {
		if ($any === null) {
			return "";
		}
    $parts = \explode('/', $any->type_url);
    if (\count($parts) != 2) {
      return "";
    }
    return $parts[1];
  }

  function AnyIs<T as Message>(
    ?\google\protobuf\Any $any,
    classname<T> $cls,
  ): bool {
    return AnyMessageName($any) === \ClassNameToMessageName($cls);
  }

} // namespace Protobuf

namespace {

  function ClassNameToMessageName<T as \Protobuf\Message>(classname<T> $cls): string {
    return \str_replace('\\', '.', $cls);
  }

  function MessageName<T as \Protobuf\Message>(T $message): string {
    return ClassNameToMessageName(\get_class($message));
  }

} // namespace
