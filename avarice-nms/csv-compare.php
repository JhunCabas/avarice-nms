<?php

if (empty($argv[1]) or !is_file($argv[1])) {
  exit("You must provide a file to sort: php csv-compare.php \\path\\to\\file1.csv \\path\\to\\file2.csv\n");
} else {
  $filename1 = $argv[1];
};

if (empty($argv[2]) or !is_file($argv[2])) {
  exit("You must provide a file to sort: php csv-compare.php \\path\\to\\file1.csv \\path\\to\\file2.csv\n");
} else {
  $filename2 = $argv[2];
};

$file1 = file($filename1);
$file2 = file($filename2);

$matches = array_intersect($file1, $file2);
$diffs   = array_diff($file1, $file2);

if (($handle = fopen("csv-compare.matches.csv", "w")) !== FALSE) {
  foreach ($matches as $line) {
    fwrite($handle, $line . "\n");
  };
};
fclose($handle);
if (($handle = fopen("csv-compare.diffs.csv", "w")) !== FALSE) {
  fwrite($handle, $file[0] . "\n");
  foreach ($diffs as $line) {
    fwrite($handle, $line . "\n");
  };
};
fclose($handle);

?>