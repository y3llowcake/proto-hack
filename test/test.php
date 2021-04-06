<?hh // strict

<<__EntryPoint>>
function main(): void {
  //set_time_limit(5);
  //ini_set('memory_limit', '20M');

  require "lib/errors.php";
  require "lib/protobuf.php";
  require "lib/grpc.php";
  require "generated/google/protobuf/any_proto.php";
  require "generated/test/example1_proto.php";
  require "generated/test/example2_proto.php";
  require "generated/test/example4_proto.php";
  require "generated/test/exampleany_proto.php";
  require "generated/google/protobuf/descriptor_proto.php";

  require "test/test_suite.php";

  $argv = HH\global_get('argv') as KeyedContainer<_, _>;
  if (count($argv) > 1 && $argv[1] == 'bench') {
    bench();
    exit(1);
  }

  // PROTO
  $raw = file_get_contents('generated/test/example1.pb.bin');
  $got = new foo\bar\example1();
  check(Protobuf\Unmarshal($raw, $got));
  testExample1($got, "test example1: file");

  $remarsh = Protobuf\Marshal($got);
  araw($remarsh, $raw, "hack marshal does not match protoc marshal");
  $got = new foo\bar\example1();
  check(Protobuf\Unmarshal($remarsh, $got));
  testExample1($got, "test example1: remarshal");
  $copy = new foo\bar\example1();
  $copy->CopyFrom($got);
  testExample1($copy, "test example1: deep copy");

  // JSON
  // TODO: hmmm, something weird happened and now this is throwing memory
  // errors.
  /*$jraw = Protobuf\MarshalJson($got, Protobuf\JsonEncode::PRETTY_PRINT);
  file_put_contents('./gen-data/example1.pb.json', $jraw);
  $got = new foo\bar\example1();
  check(Protobuf\UnmarshalJson($jraw, $got));
  	testExample1($got, "test example1: json unmarshal");*/

  // Reflection
  testDescriptorReflection();

  /*for ($i = 0; $i < 10000; $i++){
   $start = microtime_as_int();
   testExample1($raw, "blarg");
   $start = microtime_as_int() - $start;
   echo "elapsed: " . $start . "\n";
   }*/

  // Reserved class names
  testReservedClassNames();

  // Any
  testAny();

  // Service
  testLoopbackService();
}
