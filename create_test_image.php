<?php
// Create a dummy image for testing
$width = 100;
$height = 100;
$image = imagecreatetruecolor($width, $height);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
imagefilledrectangle($image, 0, 0, $width, $height, $white);
imagestring($image, 5, 10, 40, 'Test', $black);
imagepng($image, 'test_image.png');
imagedestroy($image);
echo "Test image 'test_image.png' created successfully.";
?>
