<?php
$url = "http://localhost/api/v1/gallery?page=1";
$options = array(
  'http' => array(
    'header'  => "Content-type: application/json\r\nAccept: application/json\r\n",
    'method'  => 'GET'
  )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
if ($result === FALSE) { 
    echo "Request failed\n"; 
} else {
    echo mb_substr($result, 0, 2000);
}
