all: bin

bin: deps
	cd bin/protoc-gen-hack && go build

dev: bin example
	echo; \
	echo; \
	echo output:; \
	find ./example/gen-src -type f -regex '.*\.php' -printf "\n%p\n" -exec cat {} \;

run:
	cd bin/protoc-gen-hack && go run main.go

.PHONY: example
example: test
	$(MAKE) -C example; \

test:
	for dir in lib; do \
		$(MAKE) -C $$dir test; \
	done

clean:
	for dir in third_party example; do \
		$(MAKE) -C $$dir clean; \
	done

deps:
	for dir in third_party; do \
		$(MAKE) -C $$dir; \
	done
