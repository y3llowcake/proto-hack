load("@io_bazel_rules_go//go:def.bzl", "go_binary")
load(":hh.bzl", "hh_client_test", "hh_test")

package(default_visibility = ["//visibility:private"])

go_binary(
    name = "protoc-gen-hack",
    srcs = [
        "protoc-gen-hack/namespace.go",
        "protoc-gen-hack/plugin.go",
    ],
    out = "protoc-gen-hack",
    visibility = ["//visibility:public"],
    x_defs = {"version": "8.0.0"},
    deps = [
        "@com_github_golang_protobuf//proto:go_default_library",
        "@com_github_golang_protobuf//protoc-gen-go/descriptor:go_default_library",
        "@com_github_golang_protobuf//protoc-gen-go/plugin:go_default_library",
    ],
)

sh_binary(
    name = "gen",
    srcs = ["gen_bin.sh"],
    deps = ["//:gen_lib"],
)

sh_test(
    name = "gen_test",
    srcs = ["gen_test.sh"],
    deps = ["//:gen_lib"],
)

ALL_PHP = []

GEN_PHP = glob(["generated/**/*.php"])

ALL_PHP += GEN_PHP

GEN_PB_BIN = glob(["generated/**/*.pb.bin"])

ALL_GEN = GEN_PHP + GEN_PB_BIN

sh_library(
    name = "gen_lib",
    srcs = ["gen.sh"],
    data = [
        "@com_google_protobuf//:well_known_protos",
        "@com_google_protobuf//:conformance_proto",
        "@com_google_protobuf//:test_messages_proto3_proto",
        "@com_google_protobuf//:test_messages_proto2_proto",
        "@com_google_protobuf//:protoc",
        "//:protoc-gen-hack",
    ] + glob([
        "test/*.proto",
        "test/*.pb.txt",
    ]) + ALL_GEN,
)

LIB_PHP = glob(["lib/**/*.php"])

ALL_PHP += LIB_PHP

LIB_TEST_PHP = glob(["lib_test/**/*.php"])

ALL_PHP += LIB_TEST_PHP

hh_test(
    name = "library_test",
    srcs = LIB_TEST_PHP + LIB_PHP,
    hh_args = "lib_test/test.php",
)

INTEGRATION_PHP = glob(["test/**/*.php"])

ALL_PHP += INTEGRATION_PHP

hh_test(
    name = "integration_test",
    srcs = INTEGRATION_PHP + LIB_PHP + ALL_GEN,
    hh_args = "test/test.php",
)

CONFORMANCE_PHP = glob(["conformance/**/*.php"])

ALL_PHP += CONFORMANCE_PHP

sh_library(
    name = "conformance_lib",
    srcs = ["conformance.sh"],
    data = CONFORMANCE_PHP + LIB_PHP + ALL_GEN + [
        "conformance_hhvm_harness.sh",
        "conformance/failures.txt",
        "@com_google_protobuf//:conformance_test_runner",
    ],
)

sh_test(
    name = "conformance_test",
    srcs = ["conformance_test.sh"],
    deps = [":conformance_lib"],
)

sh_binary(
    name = "conformance_update_failures",
    srcs = ["conformance_bin.sh"],
    deps = [":conformance_lib"],
)

hh_client_test(
    name = "typecheck_test",
    srcs = [".hhconfig"] + ALL_PHP,
)

filegroup(
    name = "hack_library",
    srcs = LIB_PHP + glob(
        include = ["generated/google/protobuf/**/*.php"],
        exclude = ["**/*test_messages*"],
    ),
    visibility = ["//visibility:public"],
)
