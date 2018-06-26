load("@io_bazel_rules_go//go:def.bzl", "go_binary")

package(default_visibility = ["//visibility:public"])

go_binary(
    name = "protoc-gen-hack",
    srcs = [
        "protoc-gen-hack/namespace.go",
        "protoc-gen-hack/plugin.go",
    ],
    deps = [
        "@com_github_golang_protobuf//proto:go_default_library",
        "@com_github_golang_protobuf//protoc-gen-go/descriptor:go_default_library",
        "@com_github_golang_protobuf//protoc-gen-go/plugin:go_default_library",
    ],
)
