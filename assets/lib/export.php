<?php

$db = new ARCJudgDB;

// set headers
$headers = array();
// $headers[] = "Content-Description: File Transfer";
$headers[] = "Content-Disposition: attachment; filename=\"competency_csv_data.csv\"";
$headers[] = "Content-Type: text/csv";
$headers[] = "Cache-Control: max-age=0, no-cache, no-store";
$headers[] = "Pragma: no-cache";
$headers[] = "Connection: close";

// set default csv headers
$columns = $db->get_columns();
$csv_headers = "";
foreach($columns as $column) {
    $csv_headers .= $column->Key == "PRI" ? $column->Field : ("," . $column->Field);
}
$csv_headers .= "\n";

// get data from db
$all_rows = $db->get_all();
$csv_rows = "";
foreach($all_rows as $row) {
    foreach($row as $key => $cell) {
        // differentiate between true null and the empty string, convert from UTF-8 encoding to ANSI
        $cell_data = $cell === null ? "null" : ('"' . iconv("UTF-8", "WINDOWS-1252", $cell) . '"');
        $csv_rows .= $key == "judg_id" ? $cell_data : ("," . $cell_data);
    }
    $csv_rows .= "\n";
}

// create temp dir/file
$tmp_dir = sys_get_temp_dir();
$filename = tempnam( $tmp_dir, 'gcac_data_');

// open file for appending
$csv_file = fopen($filename, 'a');

// write csv header, rows to file
fprintf($csv_file, '%s', $csv_headers);
fprintf($csv_file, '%s', $csv_rows);

// send data
gcac_send_data($csv_file,$filename,$headers);

function gcac_send_data($csv_file,$filename,$headers) {
    // close temp file
    fclose($csv_file);

    if (version_compare(phpversion(), '5.3.0', '>')) {
        //make sure we get the right file size
        clearstatcache( true, $filename );
    } else {
        // for any PHP version prior to v5.3.0
        clearstatcache();
    }

    // set download size
    $headers[] = "Content-Length: " . filesize($filename);

    // make sure headers have not been sent already
    if(headers_sent()) {
        $response['type'] = 'error: headers already sent';
        $response = json_encode($response);
        die;
    }

    // send headers
    foreach($headers as $header) {
        header($header . "\r\n");
    }

    // disable compression for the duration of file download
    if(ini_get('zlib.output_compression')){
        ini_set('zlib.output_compression', 'Off');
    }

    // read the file to output - if using on flywheel site, use readfile instead
    if(function_exists('fpassthru')) {
        $fp = fopen($filename, 'rb');
        fpassthru($fp);
        fclose($fp);
    } else {
        readfile($filename);
    }

    // remove temp file
    unlink($filename);

    // exit
    exit;
}