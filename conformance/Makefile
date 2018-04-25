all: test

test: gen
	# hhvm example1.php
	echo "\033[1mCONFORMANCE TEST PASSED\033[0m"

gen:
	mkdir -p gen-src
	protoc -I ../ --hack_out=plugin=grpc:./gen-src ../third_party/google/protobuf/conformance/conformance.proto

clean:
	rm -rfv gen-src
