package main

import (
	"bytes"
	"fmt"
	"github.com/golang/protobuf/proto"
	desc "github.com/golang/protobuf/protoc-gen-go/descriptor"
	ppb "github.com/y3llowcake/proto-hack/third_party/gen-src/github.com/golang/protobuf/protoc-gen-go/plugin"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

const (
	genDebug = false
	libNs    = "\\Protobuf\\Internal"
)

func main() {
	var buf bytes.Buffer
	_, err := buf.ReadFrom(os.Stdin)
	if err != nil {
		panic(fmt.Errorf("error reading from stdin: %v", err))
	}
	out, err := codeGenerator(buf.Bytes())
	if err != nil {
		panic(err)
	}
	os.Stdout.Write(out)
}

func codeGenerator(b []byte) ([]byte, error) {
	req := ppb.CodeGeneratorRequest{}
	err := proto.Unmarshal(b, &req)
	if err != nil {
		return nil, fmt.Errorf("error unmarshaling CodeGeneratorRequest: %v", err)
	}
	resp := gen(&req)
	out, err := proto.Marshal(resp)
	if err != nil {
		return nil, fmt.Errorf("error marshaling CodeGeneratorResponse: %v", err)
	}
	return out, nil
}

func gen(req *ppb.CodeGeneratorRequest) *ppb.CodeGeneratorResponse {
	resp := &ppb.CodeGeneratorResponse{}
	fileToGenerate := map[string]bool{}
	for _, f := range req.FileToGenerate {
		fileToGenerate[f] = true
	}
	rootns := NewEmptyNamespace()
	for _, fdp := range req.ProtoFile {
		rootns.Parse(fdp)
		// panic(rootns.PrettyPrint())

		if !fileToGenerate[*fdp.Name] {
			continue
		}
		f := &ppb.CodeGeneratorResponse_File{}

		fext := filepath.Ext(*fdp.Name)
		fname := strings.TrimSuffix(*fdp.Name, fext) + "_proto.php"
		f.Name = proto.String(fname)

		b := &bytes.Buffer{}
		w := &writer{b, 0}
		writeFile(w, fdp, rootns)
		f.Content = proto.String(b.String())
		resp.File = append(resp.File, f)
	}
	return resp
}

type field struct {
	fd *desc.FieldDescriptorProto
	ns *Namespace
}

func (f field) phpType() string {
	switch t := *f.fd.Type; t {
	case desc.FieldDescriptorProto_TYPE_STRING, desc.FieldDescriptorProto_TYPE_BYTES:
		return "string"
	case desc.FieldDescriptorProto_TYPE_INT64,
		desc.FieldDescriptorProto_TYPE_INT32, desc.FieldDescriptorProto_TYPE_UINT64, desc.FieldDescriptorProto_TYPE_UINT32, desc.FieldDescriptorProto_TYPE_SINT64, desc.FieldDescriptorProto_TYPE_SINT32, desc.FieldDescriptorProto_TYPE_FIXED32, desc.FieldDescriptorProto_TYPE_FIXED64, desc.FieldDescriptorProto_TYPE_SFIXED32, desc.FieldDescriptorProto_TYPE_SFIXED64:
		return "int"
	case desc.FieldDescriptorProto_TYPE_FLOAT, desc.FieldDescriptorProto_TYPE_DOUBLE:
		return "float"
	case desc.FieldDescriptorProto_TYPE_BOOL:
		return "bool"
	case desc.FieldDescriptorProto_TYPE_MESSAGE:
		ns, name := f.ns.Find(*f.fd.TypeName)
		ns = strings.Replace(ns, ".", "\\", -1)
		name = strings.Replace(name, ".", "_", -1)
		return ns + name
	case desc.FieldDescriptorProto_TYPE_ENUM:
		ns, name := f.ns.Find(*f.fd.TypeName)
		ns = strings.Replace(ns, ".", "\\", -1)
		name = strings.Replace(name, ".", "_", -1) + "_EnumType"
		return ns + name
		//return "int"
	default:
		panic(fmt.Errorf("unexpected proto type while converting to php type: %v", t))
	}
}

func (f field) defaultValue() string {
	if f.isRepeated() {
		return "vec[]"
	}
	switch t := *f.fd.Type; t {
	case desc.FieldDescriptorProto_TYPE_STRING, desc.FieldDescriptorProto_TYPE_BYTES:
		return "''"
	case desc.FieldDescriptorProto_TYPE_INT64,
		desc.FieldDescriptorProto_TYPE_INT32, desc.FieldDescriptorProto_TYPE_UINT64, desc.FieldDescriptorProto_TYPE_UINT32, desc.FieldDescriptorProto_TYPE_SINT64, desc.FieldDescriptorProto_TYPE_SINT32, desc.FieldDescriptorProto_TYPE_FIXED32, desc.FieldDescriptorProto_TYPE_FIXED64, desc.FieldDescriptorProto_TYPE_SFIXED32, desc.FieldDescriptorProto_TYPE_SFIXED64:
		return "0"
	case desc.FieldDescriptorProto_TYPE_FLOAT, desc.FieldDescriptorProto_TYPE_DOUBLE:
		return "0.0"
	case desc.FieldDescriptorProto_TYPE_BOOL:
		return "false"
	case desc.FieldDescriptorProto_TYPE_ENUM:
		return "0"
	case desc.FieldDescriptorProto_TYPE_MESSAGE:
		return "null"
	default:
		panic(fmt.Errorf("unexpected proto type while converting to default value: %v", t))
	}
}

func (f field) isRepeated() bool {
	return *f.fd.Label == desc.FieldDescriptorProto_LABEL_REPEATED
}

func (f field) labeledType() string {
	if f.isRepeated() {
		return "vec<" + f.phpType() + ">"
	}
	if *f.fd.Type == desc.FieldDescriptorProto_TYPE_MESSAGE {
		return "?" + f.phpType()
	}
	return f.phpType()
}

func (f field) varName() string {
	return *f.fd.Name
}

// Default is 0
var writeWireType = map[desc.FieldDescriptorProto_Type]int{
	desc.FieldDescriptorProto_TYPE_FLOAT:    5,
	desc.FieldDescriptorProto_TYPE_DOUBLE:   1,
	desc.FieldDescriptorProto_TYPE_FIXED32:  5,
	desc.FieldDescriptorProto_TYPE_SFIXED32: 5,
	desc.FieldDescriptorProto_TYPE_FIXED64:  1,
	desc.FieldDescriptorProto_TYPE_SFIXED64: 1,
	desc.FieldDescriptorProto_TYPE_STRING:   2,
	desc.FieldDescriptorProto_TYPE_BYTES:    2,
	desc.FieldDescriptorProto_TYPE_MESSAGE:  2,
}

var isPackable = map[desc.FieldDescriptorProto_Type]bool{
	desc.FieldDescriptorProto_TYPE_INT64:    true,
	desc.FieldDescriptorProto_TYPE_INT32:    true,
	desc.FieldDescriptorProto_TYPE_UINT64:   true,
	desc.FieldDescriptorProto_TYPE_UINT32:   true,
	desc.FieldDescriptorProto_TYPE_SINT64:   true,
	desc.FieldDescriptorProto_TYPE_SINT32:   true,
	desc.FieldDescriptorProto_TYPE_FLOAT:    true,
	desc.FieldDescriptorProto_TYPE_DOUBLE:   true,
	desc.FieldDescriptorProto_TYPE_FIXED32:  true,
	desc.FieldDescriptorProto_TYPE_SFIXED32: true,
	desc.FieldDescriptorProto_TYPE_FIXED64:  true,
	desc.FieldDescriptorProto_TYPE_SFIXED64: true,
	desc.FieldDescriptorProto_TYPE_BOOL:     true,
	desc.FieldDescriptorProto_TYPE_ENUM:     true,
}

func (f field) writeDecoder(w *writer, dec, wt string) {
	if *f.fd.Type == desc.FieldDescriptorProto_TYPE_MESSAGE {
		// This is different enough we handle it on it's own.
		if f.isRepeated() {
			w.p("$obj = new %s();", f.phpType())
			w.p("$obj->MergeFrom(%s->readDecoder());", dec)
			w.p("$this->%s []= $obj;", f.varName())
		} else {
			w.p("if ($this->%s == null) {", f.varName())
			w.p("$this->%s = new %s();", f.varName(), f.phpType())
			w.p("}")
			w.p("$this->%s->MergeFrom(%s->readDecoder());", f.varName(), dec)
		}
		return
	}

	// TODO should we do wiretype checking here?
	reader := ""
	switch *f.fd.Type {
	case desc.FieldDescriptorProto_TYPE_STRING, desc.FieldDescriptorProto_TYPE_BYTES:
		reader = fmt.Sprintf("%s->readString()", dec)
	case desc.FieldDescriptorProto_TYPE_INT64, desc.FieldDescriptorProto_TYPE_INT32, desc.FieldDescriptorProto_TYPE_UINT64, desc.FieldDescriptorProto_TYPE_UINT32:
		reader = fmt.Sprintf("%s->readVarInt128()", dec)
	case desc.FieldDescriptorProto_TYPE_SINT64, desc.FieldDescriptorProto_TYPE_SINT32:
		reader = fmt.Sprintf("%s->readVarInt128ZigZag()", dec)
	case desc.FieldDescriptorProto_TYPE_FLOAT:
		reader = fmt.Sprintf("%s->readFloat()", dec)
	case desc.FieldDescriptorProto_TYPE_DOUBLE:
		reader = fmt.Sprintf("%s->readDouble()", dec)
	case desc.FieldDescriptorProto_TYPE_FIXED32, desc.FieldDescriptorProto_TYPE_SFIXED32:
		reader = fmt.Sprintf("%s->readLittleEndianInt(4)", dec)
	case desc.FieldDescriptorProto_TYPE_FIXED64, desc.FieldDescriptorProto_TYPE_SFIXED64:
		reader = fmt.Sprintf("%s->readLittleEndianInt(8)", dec)
	case desc.FieldDescriptorProto_TYPE_BOOL:
		reader = fmt.Sprintf("%s->readBool()", dec)
	case desc.FieldDescriptorProto_TYPE_ENUM:
		reader = fmt.Sprintf("%s->readVarInt128()", dec)
	default:
		panic(fmt.Errorf("unknown reader for fd type: %s", *f.fd.Type))
	}
	if !f.isRepeated() {
		w.p("$this->%s = %s;", f.varName(), reader)
		return
	}
	// Repeated
	packable := isPackable[*f.fd.Type]
	if packable {
		w.p("if (%s == 2) {", wt)
		w.p("$packed = %s->readDecoder();", dec)
		w.p("while (!$packed->isEOF()) {")
		if genDebug {
			w.p("echo \"reading packed field\\n\";")
		}
		packedReader := strings.Replace(reader, dec, "$packed", 1) // Heh, kinda hacky.
		w.p("$this->%s []= %s;", f.varName(), packedReader)
		w.p("}")
		w.p("} else {")
	}
	w.p("$this->%s []= %s;", f.varName(), reader)
	if packable {
		w.p("}")
	}
}

func (f field) writeEncoder(w *writer, enc string) {
	if genDebug {
		w.p("echo \"writing field: %d (%s)\\n\";", *f.fd.Number, f.varName())
	}

	if *f.fd.Type == desc.FieldDescriptorProto_TYPE_MESSAGE {
		// This is different enough we handle it on it's own.
		// TODO we could optimize to not to string copies.
		if f.isRepeated() {
			w.p("foreach ($this->%s as $msg) {", f.varName())
		} else {
			w.p("$msg = $this->%s;", f.varName())
			w.p("if ($msg != null) {")
		}
		w.p("$nested = new %s\\Encoder();", libNs)
		w.p("$msg->WriteTo($nested);")
		w.p("%s->writeEncoder($nested, %d);", enc, *f.fd.Number)
		w.p("}")
		return
	}

	writer := ""
	switch *f.fd.Type {
	case desc.FieldDescriptorProto_TYPE_STRING, desc.FieldDescriptorProto_TYPE_BYTES:
		writer = fmt.Sprintf("%s->writeString($this->%s)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_INT64, desc.FieldDescriptorProto_TYPE_INT32, desc.FieldDescriptorProto_TYPE_UINT64, desc.FieldDescriptorProto_TYPE_UINT32:
		writer = fmt.Sprintf("%s->writeVarInt128($this->%s)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_SINT64, desc.FieldDescriptorProto_TYPE_SINT32:
		writer = fmt.Sprintf("%s->writeVarInt128ZigZag($this->%s)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_FLOAT:
		writer = fmt.Sprintf("%s->writeFloat($this->%s)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_DOUBLE:
		writer = fmt.Sprintf("%s->writeDouble($this->%s)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_FIXED32, desc.FieldDescriptorProto_TYPE_SFIXED32:
		writer = fmt.Sprintf("%s->writeLittleEndianInt($this->%s, 4)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_FIXED64, desc.FieldDescriptorProto_TYPE_SFIXED64:
		writer = fmt.Sprintf("%s->writeLittleEndianInt($this->%s, 8)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_BOOL:
		writer = fmt.Sprintf("%s->writeBool($this->%s)", enc, f.varName())
	case desc.FieldDescriptorProto_TYPE_ENUM:
		writer = fmt.Sprintf("%s->writeVarInt128($this->%s)", enc, f.varName())
	default:
		panic(fmt.Errorf("unknown reader for fd type: %s", *f.fd.Type))
	}
	tagWriter := fmt.Sprintf("%s->writeTag(%d, %d);", enc, *f.fd.Number, writeWireType[*f.fd.Type])

	if !f.isRepeated() {
		w.p("if ($this->%s !== %s) {", f.varName(), f.defaultValue())
		w.p(tagWriter)
		w.p("%s;", writer)
		w.p("}")
		return
	}
	// Repeated
	// Heh, kinda hacky.
	repeatWriter := strings.Replace(writer, "$this->"+f.varName(), "$elem", 1)
	if isPackable[*f.fd.Type] {
		// Heh, kinda hacky.
		packedWriter := strings.Replace(repeatWriter, enc, "$packed", 1)
		w.p("$packed = new %s\\Encoder();", libNs)
		w.p("foreach ($this->%s as $elem) {", f.varName())
		if genDebug {
			w.p("echo \"writing packed\\n\";")
		}
		w.p("%s;", packedWriter)
		w.p("}")
		w.p("%s->writeEncoder($packed, %d);", enc, *f.fd.Number)
	} else {
		w.p("foreach ($this->%s as $elem) {", f.varName())
		w.p(tagWriter)
		w.p("%s;", repeatWriter)
		w.p("}")
	}
}

func writeEnum(w *writer, ed *desc.EnumDescriptorProto, prefixNames []string) {
	name := strings.Join(append(prefixNames, *ed.Name), "_")
	typename := name + "_EnumType"
	w.p("newtype %s = int;", typename)
	w.p("class %s {", name)
	for _, v := range ed.Value {
		w.p("const %s %s = %d;", typename, *v.Name, *v.Number)
	}
	w.p("}")
	w.ln()
}

// https://github.com/golang/protobuf/blob/master/protoc-gen-go/descriptor/descriptor.pb.go
func writeDescriptor(w *writer, dp *desc.DescriptorProto, ns *Namespace, prefixNames []string) {
	nextNames := append(prefixNames, *dp.Name)
	name := strings.Join(nextNames, "_")

	// Nested Enums.
	for _, edp := range dp.EnumType {
		writeEnum(w, edp, nextNames)
	}

	// Nested Types.
	for _, ndp := range dp.NestedType {
		writeDescriptor(w, ndp, ns, nextNames)
	}

	w.p("// message %s", *dp.Name)
	w.p("class %s extends %s\\Message {", name, libNs)

	fields := []field{}
	for _, fd := range dp.Field {
		fields = append(fields, field{fd, ns})
	}

	// Members
	for _, f := range fields {
		w.p("// field %s = %d", *f.fd.Name, *f.fd.Number)
		w.p("public %s $%s;", f.labeledType(), f.varName())
	}
	w.ln()

	// Constructor.
	w.p("public function __construct() {")
	for _, f := range fields {
		w.p("$this->%s = %s;", f.varName(), f.defaultValue())
	}
	w.p("}")
	w.ln()

	// Now sort the fields by number.
	sort.Slice(fields, func(i, j int) bool {
		return *fields[i].fd.Number < *fields[j].fd.Number
	})

	// MergeFrom function
	w.p("public function MergeFrom(%s\\Decoder $d): void {", libNs)
	w.p("while (!$d->isEOF()){")
	w.p("list($fn, $wt) = $d->readTag();")
	if genDebug {
		w.p("echo \"unmarshal loop field:$fn wiretype:$wt\\n\";")
	}
	w.p("switch ($fn) {")
	for _, f := range fields {
		w.p("case %d:", *f.fd.Number)
		w.i++
		if genDebug {
			w.p("echo \"reading field %s\\n\";", f.varName())
		}
		f.writeDecoder(w, "$d", "$wt")
		w.p("break;")
		w.i--
	}
	w.p("default:")
	w.i++
	if genDebug {
		w.p("echo \"skipping unknown field:$fn wiretype:$wt\\n\";")
	}
	w.p("$d->skipWireType($wt);")
	w.i--
	w.p("}") // switch
	w.p("}") // while
	w.p("}") // function MergeFrom
	w.ln()

	// WriteTo function
	w.p("public function WriteTo(%s\\Encoder $e): void {", libNs)
	for _, f := range fields {
		f.writeEncoder(w, "$e")
	}
	w.p("}") // WriteToFunction

	w.p("}") // class
	w.ln()
}

type writer struct {
	w io.Writer
	i int
}

func (w *writer) p(format string, a ...interface{}) {
	if strings.HasPrefix(format, "}") {
		w.i--
	}
	indent := strings.Repeat("  ", w.i)
	fmt.Fprintf(w.w, indent+format, a...)
	w.ln()
	if strings.HasSuffix(format, "{") {
		w.i++
	}
}

func (w *writer) ln() {
	fmt.Fprintln(w.w)
}

func writeFile(w *writer, fdp *desc.FileDescriptorProto, rootNs *Namespace) {
	packageParts := strings.Split(*fdp.Package, ".")
	ns := rootNs.get(false, packageParts)
	if ns == nil {
		panic("unable to find namespace for: " + *fdp.Package)
	}

	// File header.
	w.p("<?hh // strict")
	w.p("namespace %s;", strings.Join(packageParts, "\\"))
	w.ln()
	w.p("// Generated by the protocol buffer compiler.  DO NOT EDIT!")
	w.p("// Source: %s", *fdp.Name)
	w.ln()

	// Top level enums.
	for _, edp := range fdp.EnumType {
		writeEnum(w, edp, nil)
	}

	// Messages, recurse.
	for _, dp := range fdp.MessageType {
		writeDescriptor(w, dp, ns, nil)
	}
}
