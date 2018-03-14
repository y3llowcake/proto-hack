<?hh
include "../lib/protobuf.php";
include "./gen-src/foo/proto_types.php";


function a(mixed $got, mixed $exp, string $msg) {
	if ($got != $exp) {
		throw new Exception($msg . "; got:\n" . print_r($got, true) . "\n expected:\n" . print_r($exp, true) . "\ndiff:\n" . diff($got, $exp));
	}
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
			return "property: {$prop->name} got: $gotval expected: $expval";
		}
	}
	return "<empty diff>";
}

function repackFloat(float $f): float {
	return unpack("f", pack("f", $f))[1];
}

$got = new foo\example1();
Protobuf\Internal\Unmarshal(file_get_contents('./gen-data/example1.pb.bin'), $got);
$exp = new foo\example1();
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

a($got, $exp, "example1 mismatch");

