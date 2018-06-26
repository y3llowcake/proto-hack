#
# Golang.
#
http_archive(
    name = "io_bazel_rules_go",
    urls = ["https://github.com/bazelbuild/rules_go/releases/download/0.12.1/rules_go-0.12.1.tar.gz"],
    sha256 = "8b68d0630d63d95dacc0016c3bb4b76154fe34fca93efd65d1c366de3fcb4294",
)

load(
	"@io_bazel_rules_go//go:def.bzl",
	"go_rules_dependencies",
	"go_register_toolchains",
)

go_rules_dependencies()
go_register_toolchains()

#
# Golang + Protobuf.
#

load(
    "@io_bazel_rules_go//proto:def.bzl",
    "go_proto_library",
)
