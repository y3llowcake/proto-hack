<?hh // strict

function check(\Errors\Error $err): void {
  if (!$err->Ok()) {
    throw new Exception($err->Error());
  }
}

function a(mixed $got, mixed $exp, string $msg): void {
  if ($got != $exp) {
    throw new Exception(
      $msg.
      "; got:\n".
      print_r($got, true).
      "\n expected:\n".
      print_r($exp, true).
      "\ndiff:\n".
      diff($got, $exp),
    );
  }
}

function araw(string $got, string $exp, string $msg): void {
  if ($got === $exp) {
    return;
  }
  for ($i = 0; $i < min(strlen($got), strlen($exp)); $i++) {
    if ($got[$i] !== $exp[$i]) {
      //echo sprintf("first diff at offset:%d got:%d exp:%d\n", $i, ord($got[$i]), ord($exp[$i]));
      echo sprintf(
        "first diff at offset:%d got:%s exp:%s\n",
        $i,
        ord($got[$i]),
        ord($exp[$i]),
      );
      break;
    }
  }
  echo sprintf("length got: %d expected: %d\n", strlen($got), strlen($exp));

  $gdec = Protobuf\Internal\Decoder::FromString($got);
  $edec = Protobuf\Internal\Decoder::FromString($exp);
  while (!$gdec->isEOF() && !$edec->isEOF()) {
    list($gfn, $gwt) = $gdec->readTag();
    list($efn, $ewt) = $edec->readTag();
    echo sprintf("got fn:%d wt:%d\n", $gfn, $gwt);
    echo sprintf("exp fn:%d wt:%d\n", $efn, $ewt);
    if ($gfn != $efn || $gwt != $ewt) {
      echo "^^ mismatch ^^\n";
    }
    $gdec->skip($gfn, $gwt);
    $edec->skip($efn, $ewt);
  }
  $tmpf = tempnam('', 'proto-test-got');
  $msg .= " writing to got to $tmpf";
  file_put_contents($tmpf, $got);
  throw new Exception($msg);
}

function diff(mixed $got, mixed $exp): string {
  if (
    !is_object($got) || !is_object($exp) || get_class($got) != get_class($exp)
  ) {
    return "<not diffable>";
  }
  $rexp = new ReflectionClass($exp);
  $rgot = new ReflectionClass($got);
  foreach ($rexp->getProperties() as $prop) {
    $gotval = $prop->getValue($got);
    $expval = $rexp->getProperty($prop->name)->getValue($exp);
    if ($gotval != $expval) {
      return sprintf(
        "property: %s got: %s expected: %s",
        $prop->name,
        print_r($gotval, true),
        print_r($expval, true),
      );
    }
  }
  return "<empty diff>";
}

function repackFloat(float $f): float {
  return unpack("f", pack("f", $f))[1];
}

function testExample1(foo\bar\example1 $got, string $failmsg): void {
  $exp = new foo\bar\example1(shape(
    'adouble' => 13.37,
  ));
  $exp->afloat = repackFloat(100.1);
  $exp->aint32 = 1;
  $exp->aint64 = 12;
  $exp->auint32 = 123;
  $exp->auint64 = 1234;
  $exp->asint32 = 12345;
  $exp->asint64 = 123456;
  $exp->afixed32 = 1234567;
  $exp->afixed64 = 12345678;
  $exp->asfixed32 = 123456789;
  $exp->asfixed64 = 1234567890;
  $exp->abool = true;
  $exp->astring = "foobar";
  $exp->abytes = "hello world";

  $exp->aenum1 = foo\bar\AEnum1::B;
  $exp->aenum2 = foo\bar\example1_AEnum2::D;
  $exp->aenum22 = fiz\baz\AEnum2::Z;

  $exp->manystring[] = "ms1";
  $exp->manystring[] = "ms2";
  $exp->manystring[] = "ms3";

  $exp->manyint64[] = 1;
  $exp->manyint64[] = 2;
  $exp->manyint64[] = 3;

  $e2 = new foo\bar\example1_example2();
  $exp->aexample2 = $e2;
  $e2->astring = "zomg";

  $e22 = new foo\bar\example2();
  $exp->aexample22 = $e22;
  $e22->aint32 = 123;

  $e23 = new fiz\baz\example2();
  $exp->aexample23 = $e23;
  $e23->zomg = -12;

  $exp->amap["k1"] = "v1";
  $exp->amap["k2"] = "v2";

  $exp->outoforder = 1;

  $exp->aoneof = new \foo\bar\example1_oostring("oneofstring");

  a($got, $exp, $failmsg);
}

function microtime_as_int(): int {
  $gtod = gettimeofday();
  return ($gtod['sec'] * 1000000) + $gtod['usec'];
}

function testDescriptorReflection(): void {
  $fds = Protobuf\Internal\LoadedFileDescriptors();
  $names = dict[];
  foreach ($fds as $fd) {
    $raw = $fd->FileDescriptorProtoBytes();
    if ($raw == false) {
      throw new \Exception('descriptor decode failed');
    }
    $dp = new google\protobuf\FileDescriptorProto();
    check(Protobuf\Unmarshal($raw, $dp));
    // print_r($dp);
    $names[$fd->Name()] = $raw;
  }
  if ($names['test/example1.proto'] == '') {
    throw new \Exception('missing file descriptor for example1');
  }
  $dp = new google\protobuf\FileDescriptorProto();
  check(Protobuf\Unmarshal($names['test/example1.proto'], $dp));
  if ($dp->package != 'foo.bar') {
    throw new \Exception(
      'descriptor proto for example1.proto has unexpected package: '.
      $dp->package,
    );
  }
}

function testReservedClassNames(): void {
  // This should run without errors.
  $c = new pb_Class();
  $i = new pb_Interface();
  $i->class = $c;
  $n = new NotClass();
}

function assert(bool $b): void {
  if (!$b) {
    throw new \Exception('assertion failed');
  }
}

function testAny(): void {
  // This should run without errors.
  $e1 = new foo\bar\example1();
  $e1->astring = "Hello World!";
  $t1 = new AnyTest();

  // Test marshaling.
  $t1->any = Protobuf\MarshalAny($e1);
  $any = $t1->any;
  invariant($any != null, "");

  \assert($any->type_url === 'type.googleapis.com/foo.bar.example1');
  \assert($any->value === Protobuf\Marshal($e1));

  // Test serde.
  $str = Protobuf\Marshal($t1);
  $t2 = new AnyTest();
  check(Protobuf\Unmarshal($str, $t2));
  $any2 = $t2->any;
  invariant($any2 != null, "");
  \assert($any2->type_url === $any->type_url);
  \assert($any2->value === $any->value);

  // Test unmarshaling
  $e2 = new foo\bar\example1();
  check(Protobuf\UnmarshalAny($any2, $e2));
  \assert($e2->astring === "Hello World!");
}

class ServerImpl implements foo\bar\ExampleServiceServer {
  public async function OneToTwo(
    \Grpc\Context $ctx,
    \foo\bar\example1 $in,
  ): Awaitable<\Errors\Result<\foo\bar\example2>> {
    if ($in->astring !== "hello") {
      throw new Exception('fail!');
    }
    return Errors\ResultV(new \foo\bar\example2(shape(
      'aint32' => 1337,
    )));
  }
}

class Context implements \Grpc\Context {
  public function IncomingMetadata(): \Grpc\Metadata {
    return \Grpc\Metadata::Empty();
  }
  public function WithTimeoutMicros(int $to): \Grpc\Context {
    return $this;
  }
  public function WithOutgoingMetadata(\Grpc\Metadata $m): \Grpc\Context {
    return $this;
  }
}

function testLoopbackService(): void {
  $cli = new \foo\bar\ExampleServiceClient(new Grpc\LoopbackInvoker(
    \foo\bar\ExampleServiceServiceDescriptor(new ServerImpl()),
  ));
  $in = new \foo\bar\example1(shape(
    'astring' => 'hello',
  ));
  $out = HH\Asio\join($cli->OneToTwo(new Context(), $in));
  if ($out->MustValue()->aint32 !== 1337) {
    throw new Exception('loopback service test failed');
  }
}

function bench(): void {
  $raw = file_get_contents('generated/test/example1.pb.bin');
  $iter = 100000;
  while (true) {
    $duration = clock_gettime_ns(CLOCK_REALTIME);
    for ($i = 0; $i < $iter; $i++) {
      $message = new foo\bar\example1();
      check(Protobuf\Unmarshal($raw, $message));
      Protobuf\Marshal($message);
    }
    $duration = (clock_gettime_ns(CLOCK_REALTIME) - $duration) / 1000000000;
    echo "$iter iterations in $duration (s)\n";
  }
}
