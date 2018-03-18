<?hh // partial
include "../lib/protobuf.php";
include "../lib/grpc.php";
include "./gen-src/example1_proto.php";


function a(mixed $got, mixed $exp, string $msg): void {
	if ($got != $exp) {
		throw new Exception($msg . "; got:\n" . print_r($got, true) . "\n expected:\n" . print_r($exp, true) . "\ndiff:\n" . diff($got, $exp));
	}
}

function araw(string $got, string $exp, string $msg): void {
	if ($got === $exp) {
		return;
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
		$gdec->skipWireType($gwt);
		$edec->skipWireType($ewt);
	}
	throw new Exception($msg);
}

function diff(mixed $got, mixed $exp): string {
	if (!is_object($got) || !is_object($exp) || get_class($got) != get_class($exp)) {
		return "<not diffable>";
	}
	$rexp = new ReflectionClass($exp);
	$rgot = new ReflectionClass($got);
	foreach ($rexp->getProperties() as $prop) {
		$gotval = $prop->getValue($got);
		$expval = $rexp->getProperty($prop->name)->getValue($exp);
		if ($gotval != $expval) {
			return sprintf("property: %s got: %s expected: %s", $prop->name, print_r($gotval, true), print_r($expval, true));
		}
	}
	return "<empty diff>";
}

function repackFloat(float $f): float {
	return unpack("f", pack("f", $f))[1];
}

function testExample1($raw, $failmsg): string {
	$got = new foo\bar\example1();
	Protobuf\Unmarshal($raw, $got);
	$exp = new foo\bar\example1();
	$exp->adouble = 13.37;
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

	$exp->manystring []= "ms1";
	$exp->manystring []= "ms2";
	$exp->manystring []= "ms3";


	$exp->manyint64 []= 1;
	$exp->manyint64 []= 2;
	$exp->manyint64 []= 3;

	$e2 = new foo\bar\example1_example2();
	$exp->aexample2 = $e2;
	$e2->astring = "zomg";

	$exp->amap["k1"] = "v1";
	$exp->amap["k2"] = "v2";

	$exp->outoforder = 1;

	a($got, $exp, $failmsg);
	return Protobuf\Marshal($got);
}

function microtime_as_int(): int {
	$gtod = gettimeofday();
 	return ($gtod['sec'] * 1000000) + $gtod['usec'];
}

function test(): void {
	$raw = file_get_contents('./gen-data/example1.pb.bin');
	$res = testExample1($raw, "test example1: file");
	araw($res, $raw, "hack marshal does not match protoc marshal");
	testExample1($res, "test example1: remarshal");

	/*for ($i = 0; $i < 1000; $i++){
		$start = microtime_as_int();
		testExample1($raw, "blarg");
		$start = microtime_as_int() - $start;
		echo "elapsed: " . $start . "\n";	
	}*/
}

test();
