#include "hphp/runtime/ext/extension.h"

#include "hphp/util/compatibility.h"

#include <google/protobuf/stubs/common.h>

#define LOGGER DLOG(INFO)

using google::protobuf::internal::VersionString;

namespace HPHP {

String HHVM_FUNCTION(protobuf_library_version) {
  google::protobuf::ShutdownProtobufLibrary();
  return String(VersionString(GOOGLE_PROTOBUF_VERSION));
}

struct ProtobufExtension : Extension {
  ProtobufExtension(): Extension("protobuf", "1.0.0") {}

  void moduleInit() override {
    HHVM_FE(protobuf_library_version);
    loadSystemlib();
  }
} s_protobuf_extension;

HHVM_GET_MODULE(protobuf);

} // namespace HPHP
