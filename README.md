# proto-hack
Hacklang generator for protobuf

# Installation
`go get -u github.com/y3llowcake/proto-hack/protoc-gen-hack`

# Usage
`protoc --hack_out=./gen-src example.proto`

# Development
`make test`

# Notes
Unsigned 64 bit integer types (uint64, fixed64) are represented using their
signed counterparts, with the top bit simply being stored in the sign bit.
(similar to java).
