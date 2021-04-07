load("@bazel_tools//tools/build_defs/repo:http.bzl", "http_archive")

#
# Golang.
#
http_archive(
    name = "io_bazel_rules_go",
    sha256 = "69de5c704a05ff37862f7e0f5534d4f479418afc21806c887db544a316f3cb6b",
    urls = [
        "https://mirror.bazel.build/github.com/bazelbuild/rules_go/releases/download/v0.27.0/rules_go-v0.27.0.tar.gz",
        "https://github.com/bazelbuild/rules_go/releases/download/v0.27.0/rules_go-v0.27.0.tar.gz",
    ],
)

load("@io_bazel_rules_go//go:deps.bzl", "go_register_toolchains", "go_rules_dependencies")

go_rules_dependencies()

go_register_toolchains(version = "1.16.2")

PBV = "3.15.7"

http_archive(
    name = "com_google_protobuf",
    sha256 = "feeeb3a866834bd46be16a20d3ff74c475b27e1c0d4441173b6dfd806bc2f136",
    strip_prefix = "protobuf-" + PBV,
    urls = ["https://github.com/protocolbuffers/protobuf/archive/v" + PBV + ".zip"],
)

load("@com_google_protobuf//:protobuf_deps.bzl", "protobuf_deps")

protobuf_deps()
