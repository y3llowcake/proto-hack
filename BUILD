load("@io_bazel_rules_go//go:def.bzl", "go_binary")

package(default_visibility = ["//visibility:public"])

go_binary(
    name = "protoc-gen-hack",
    srcs = [
        "protoc-gen-hack/namespace.go",
        "protoc-gen-hack/plugin.go",
    ],
    x_defs = {"version": "1.0"},
    deps = [
        "@com_github_golang_protobuf//proto:go_default_library",
        "@com_github_golang_protobuf//protoc-gen-go/descriptor:go_default_library",
        "@com_github_golang_protobuf//protoc-gen-go/plugin:go_default_library",
    ],
    out = 'protoc-gen-hack',
)

sh_binary(
  name = "wellknown",
  srcs = ["wellknown_bin.sh"],
  deps = [ "//:wellknown_lib" ],
)

sh_test(
  name = "wellknown_test",
  srcs = ["wellknown_test.sh"],
  deps = [ "//:wellknown_lib" ],
)

sh_library(
  name = "wellknown_lib",
  srcs = ["wellknown.sh"],
  data = [
      "@com_google_protobuf//:well_known_protos",
      "@com_google_protobuf//:protoc",
      "//:protoc-gen-hack",
  ] + glob(["lib/wellknowntype/**/*.php"]),
)

filegroup(
    name = "hack_library",
    srcs = glob([
        "lib/**/*.php",
    ]),
)
