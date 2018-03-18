all: test

test: gen
	hhvm example1.php

gen:
	mkdir -p gen-src
	mkdir -p gen-data
	protoc --hack_out=plugin=grpcx=y:./gen-src example1.proto
	# protoc --go_out=./gen-src --cpp_out=./gen-src example1.proto
	protoc --encode=foo.bar.example1  example1.proto < example1.pb.txt > gen-data/example1.pb.bin	

clean:
	rm -rfv gen-src
	rm -rfv gen-data
