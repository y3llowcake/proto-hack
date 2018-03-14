<?hh
include "../lib/protobuf.php";
include "./gen-src/foo/proto_types.php";

$data = file_get_contents("./gen-data/example1.pb.bin");
$e = new foo\example1();
Protobuf\Internal\Unmarshal($data, $e);
print_r($e);
