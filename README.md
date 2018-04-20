# proto-hack
Hacklang generator for protobuf

# Dependencies
- golang
- hhvm
- protoc

# Installation
`go get -u github.com/y3llowcake/proto-hack/protoc-gen-hack`

`protoc-gen-hack` should now be in your `$PATH`

# Usage
`protoc --hack_out=./gen-src example.proto`

To include gRPC service stubs:
`protoc --hack_out=plugin=grpc:./gen-src example.proto`

In addition to generated code, you will need the library code in `/lib`.

```
  $msg = new ExampleMessage();
  $raw = Protobuf\Marshal($msg);
  $msg2 = new ExampleMessage(); 
  Protobuf\Unmarshal($raw, $msg);
```

# Development
`make test`

# Notes
- Unsigned 64 bit integer types (e.g. uint64, fixed64) are represented using
their signed counterparts, with the top bit simply being stored in the sign bit.
(similar to java).
- Oneofs are only loosely handled. For each oneof the generator creates a
  helper function and enum that can be used to determine which field is set.
  Serialization and deserialization code does *not* attempt to enforce that at
  most one of the fields in a oneof is set. The caller must enforce this
  themselves.
