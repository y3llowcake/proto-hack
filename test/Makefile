all: test

test: gen
	hhvm -d hhvm.hack.lang.look_for_typechecker=0 example1.php

gen:
	mkdir -p gen-src
	mkdir -p gen-data
	protoc --hack_out=./gen-src example1.proto
	protoc --encode=foo.example1  example1.proto < example1.pb.txt > gen-data/example1.pb.bin	

clean:
	rm -rfv gen-src
	rm -rfv gen-data
