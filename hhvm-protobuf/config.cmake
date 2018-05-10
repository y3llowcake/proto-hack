# To use system defaults:
#find_package(Protobuf REQUIRED)

# To use a custom directory:
set(PROTOBUF_FOLDER ${CMAKE_CURRENT_SOURCE_DIR}/third_party/protobuf)
set(PROTOBUF_INCLUDE_DIR ${PROTOBUF_FOLDER}/src)
set(PROTOBUF_LIBRARIES ${PROTOBUF_FOLDER}/src/.libs/libprotobuf.so)

include_directories(${PROTOBUF_INCLUDE_DIR})
HHVM_EXTENSION(protobuf ext_protobuf.cpp)
target_link_libraries(protobuf ${PROTOBUF_LIBRARIES})
HHVM_SYSTEMLIB(protobuf ext_protobuf.php)
