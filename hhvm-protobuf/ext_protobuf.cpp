#include <folly/Chrono.h>
#include <folly/io/async/AsyncSocket.h>
#include <folly/io/IOBuf.h>
#include <folly/io/IOBufQueue.h>

#include "hphp/runtime/ext/asio/asio-external-thread-event.h"
#include "hphp/runtime/ext/asio/socket-event.h"
#include "hphp/runtime/ext/extension.h"

#include "hphp/util/compatibility.h"

using std::shared_ptr;
using folly::AsyncSocket;
using folly::IOBuf;
using folly::IOBufQueue;
using folly::SocketAddress;
using folly::stringPrintf;

#define EH_HEADER_SIZE 8
#define EH_MAX_MSG_SIZE 0xFFFFFFFF
#define LOGGER DLOG(INFO)

namespace HPHP {

String HHVM_FUNCTION(zomg, const String& hi) {
  auto event = new EscapeHatchEvent(dest, opt);
  return String(in + " world!");
}

struct EscapeHatchExtension : Extension {
  EscapeHatchExtension(): Extension("protobuf", "1.0.0") {}

  void moduleInit() override {
    HHVM_FE(protobuf);
    loadSystemlib();
  }
} s_protobuf_extension;

HHVM_GET_MODULE(protobuf);

} // namespace HPHP
