all: test

test: gen
	hhvm -d hhvm.hack.lang.look_for_typechecker=0 example.php

gen:
	mkdir -p gen-src
	mkdir -p gen-data
	protoc --hack_out=./gen-src example.proto
	protoc --encode=foo.ExampleOne example.proto < example.pb.txt > gen-data/example.pb.bin	

clean:
	rm -rfv gen-src
	rm -rfv gen-data
