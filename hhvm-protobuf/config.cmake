find_package(Protobuf REQUIRED)
include_directories(${PROTOBUF_INCLUDE_DIR})

HHVM_EXTENSION(protobuf ext_protobuf.cpp)
target_link_libraries(protobuf ${PROTOBUF_LIBRARIES})
HHVM_SYSTEMLIB(protobuf ext_protobuf.php)
