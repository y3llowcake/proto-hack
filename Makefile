all: bin

bin: third_party
	cd bin/protoc-gen-hack && go install

.PHONY: test
test:
	for dir in lib test; do \
		$(MAKE) -C $$dir test; \
	done

clean:
	for dir in third_party test; do \
		$(MAKE) -C $$dir clean; \
	done

third_party:
	for dir in third_party; do \
		$(MAKE) -C $$dir; \
	done
