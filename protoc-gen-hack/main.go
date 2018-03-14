package main

import (
	"bytes"
	"fmt"
	"os"
)

func main() {
	var buf bytes.Buffer
	_, err := buf.ReadFrom(os.Stdin)
	if err != nil {
		panic(fmt.Errorf("error reading from stdin: %v", err))
	}
	out, err := CodeGenerator(buf.Bytes())
	if err != nil {
		panic(err)
	}
	os.Stdout.Write(out)
}
