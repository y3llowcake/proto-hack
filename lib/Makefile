gen:
	for proto in any duration field_mask struct timestamp wrappers empty; do \
		protoc -I ../third_party/google/protobuf/src/google/protobuf \
		--hack_out=./wellknowntype \
		../third_party/google/protobuf/src/google/protobuf/$$proto.proto; \
	done

test:
	hhvm protobuf_test.php
	echo "\033[1mLIBRARY TEST PASSED\033[0m"
