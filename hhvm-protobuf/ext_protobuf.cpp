#include "hphp/runtime/ext/extension.h"

#include "hphp/util/compatibility.h"

#define LOGGER DLOG(INFO)

namespace HPHP {

String HHVM_FUNCTION(protobuf_zomg, const String& in) {
  return String(in + " world!");
}

struct ProtobufExtension : Extension {
  ProtobufExtension(): Extension("protobuf", "1.0.0") {}

  void moduleInit() override {
    HHVM_FE(protobuf_zomg);
    loadSystemlib();
  }
} s_protobuf_extension;

HHVM_GET_MODULE(protobuf);

} // namespace HPHP
