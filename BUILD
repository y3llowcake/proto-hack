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
  name = "gen",
  srcs = ["gen_bin.sh"],
  deps = [ "//:gen_lib" ],
)

sh_test(
  name = "gen_test",
  srcs = ["gen_test.sh"],
  deps = [ "//:gen_lib" ],
)

sh_library(
  name = "gen_lib",
  srcs = ["gen.sh"],
  data = [
      "@com_google_protobuf//:well_known_protos",
      "@com_google_protobuf//:protoc",
      "//:protoc-gen-hack",
  ] + glob([
      "test/*.proto",
      "test/*.pb.txt",
      "generated/**/*.php",
      ]),
)

filegroup(
    name = "hack_library",
    srcs = glob([
        "lib/**/*.php",
    ]),
)
