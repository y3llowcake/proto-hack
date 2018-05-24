all: bin

bin: third_party
	cd protoc-gen-hack && go install

typecheck: bin
	$(MAKE) -C test gen
	hh_client
	echo "\033[1mTYPECHECKER PASSED\033[0m"

.PHONY: test
test: typecheck
	for dir in lib test conformance; do \
		$(MAKE) -C $$dir test; \
	done

clean:
	for dir in third_party test; do \
		$(MAKE) -C $$dir clean; \
	done

.PHONY: third_party
third_party:
	for dir in third_party; do \
		$(MAKE) -C $$dir; \
	done
