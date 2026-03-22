<?php
$img = imagecreatetruecolor(400, 400);
$red = imagecolorallocate($img, 255, 0, 0);
imagefill($img, 0, 0, $red);
imagejpeg($img, 'test_red_block.jpg', 90);
imagedestroy($img);
echo "Created test_red_block.jpg\n";
