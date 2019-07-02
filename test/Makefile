all: test

test: gen
	hhvm test.php
	echo "\033[1mINTEGRATION TEST PASSED\033[0m"

bench: gen
	hhvm -d hhvm.perf_pid_map=1 test.php bench

gen:
	mkdir -p gen-src
	mkdir -p gen-data
	protoc --hack_out=plugin=grpc:./gen-src example1.proto example2.proto example3.proto example4.proto exampleany.proto
	protoc --hack_out=allow_proto2_dangerous:./gen-src descriptor.proto
	# protoc --go_out=./gen-src --cpp_out=./gen-src example1.proto
	protoc --encode=foo.bar.example1  example1.proto < example1.pb.txt > gen-data/example1.pb.bin

clean:
	rm -rfv gen-src
	rm -rfv gen-data
