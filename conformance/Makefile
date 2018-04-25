all: test

test: gen
	/home/cy/co/protobuf/conformance/conformance-test-runner conformance.sh
	echo "\033[1mCONFORMANCE TEST PASSED\033[0m"

gen:
	mkdir -p gen-src
	protoc -I ../ --hack_out=plugin=grpc:./gen-src ../third_party/google/protobuf/conformance/conformance.proto
	# protoc -I ../third_party/google/protobuf/src --hack_out=plugin=grpc:./gen-src ../third_party/google/protobuf/src/google/protobuf/test_messages_proto3.proto
	protoc -I ../third_party/google/protobuf/src --hack_out=plugin=grpc:./gen-src ../third_party/google/protobuf/src/google/protobuf/*.proto

clean:
	rm -rfv gen-src
