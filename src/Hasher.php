<?php

namespace Nbsbbs\Phash;

class Hasher
{
    // compare hash strings (no rotation)
    // this assumes the strings will be the same length, which they will be
    // as hashes.
    public function getSimilarityHamming($hash1, $hash2, $precision = 1): float
    {
        if (is_array($hash1)) {
            $similarity = count($hash1);

            // take the hamming distance between the hashes.
            foreach ($hash1 as $key => $val) {
                if ($hash1[$key] != $hash2[$key]) {
                    $similarity--;
                }
            }
            $percentage = round(($similarity / count($hash1) * 100), $precision);
            return $percentage;
        }
        if (is_string($hash1)) {
            $similarity = strlen($hash1);

            // take the hamming distance between the strings.
            for ($i = 0; $i < strlen($hash1); $i++) {
                if ($hash1[$i] != $hash2[$i]) {
                    $similarity--;
                }
            }

            $percentage = round(($similarity / strlen($hash1) * 100), $precision);
            return $percentage;
        }
    }

    /* return a perceptual hash as a string. Hex or binary. */
    public function hashAsString(array $hash, $hex = true)
    {
        $i = 0;
        $bucket = null;
        $result = null;
        if ($hex == true) {
            foreach ($hash as $bit) {
                $i++;
                $bucket .= $bit;
                if ($i == 4) {
                    $result .= dechex(bindec($bucket));
                    $i = 0;
                    $bucket = null;
                }
            }
            return $result;
        }
        return implode($hash);
    }

    /**
     * |----------------------------------------------------------------------
     * | Creates a thumbnail version of source image in memory.
     * | Please note that this method returns an image object
     * |----------------------------------------------------------------------
     */
    public function makeThumbnail($img, int $thumbwidth, int $thumbheight, int $width, int $height)
    {
        if ($width >= $height) {
            $newheight = $thumbheight;
            $divisor = $height / $thumbheight;
            $newwidth = floor($width / $divisor);
        } else {
            $newwidth = $thumbwidth;
            $divisor = $width / $thumbwidth;
            $newheight = floor($height / $divisor);
        }

        // Create the image in memory.
        $finalimg = imagecreatetruecolor($newwidth, $newheight);

        // Fast copy and resize old image into new image.
        $this->fastimagecopyresampled($finalimg, $img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        // release the source object
        imagedestroy($img);

        return $finalimg;
    }

    /**
     * |---------------------------------------------------------------------
     * | PHP implementation of the AverageHash algorithm for dct based phash
     * | Accepts PNG or JPEG images
     * |---------------------------------------------------------------------
     *
     * @param string $filepath full path to the file
     * @param int $scale
     * @return array
     */
    public function getHash(string $filepath, int $scale = 16): array
    {
        $product = $scale * $scale;
        $img = file_get_contents($filepath);
        if (!$img) {
            throw new \RuntimeException('File cannot be read');
        }
        $img = imagecreatefromstring($img);
        if (!$img) {
            throw new \RuntimeException('Image cannot be read');
        }

        //test image for same size
        $width = imagesx($img);
        $height = imagesy($img);

        if ($width != $scale || $height != $scale) {
            //stretch resize to ensure better/accurate comparison
            $img = $this->makeThumbnail($img, $scale, $scale, $width, $height);
        }

        $averageValue = 0;
        for ($y = 0; $y < $scale; $y++) {
            for ($x = 0; $x < $scale; $x++) {
                // get the rgb value for current pixel
                $rgb = ImageColorAt($img, $x, $y);
                // extract each value for r, g, b
                $red = ($rgb & 0xFF0000) >> 16;
                $green = ($rgb & 0x00FF00) >> 8;
                $blue = ($rgb & 0x0000FF);
                $gray = $red + $blue + $green;
                $gray /= 12;
                $gray = floor($gray);
                $grayscale[$x + ($y * $scale)] = $gray;
                $averageValue += $gray;
            }
        }
        $averageValue /= $product;
        $averageValue = floor($averageValue);

        $phash = array_fill(0, $product, 0);
        for ($i = 0; $i < $product; $i++) {
            if ($grayscale[$i] >= $averageValue) {
                $phash[$i] = 1;
            }
        }

        #free memory
        imagedestroy($img);
        return $phash;
    }

    public function fastimagecopyresampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3)
    {
        // Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
        // Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
        // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
        // Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
        //
        // Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
        // Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
        // 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
        // 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
        // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
        // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
        // 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

        if (empty($src_image) || empty($dst_image) || $quality <= 0) {
            return false;
        }
        if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
            $temp = imagecreatetruecolor($dst_w * $quality + 1, $dst_h * $quality + 1);
            imagecopyresized($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
            imagecopyresampled($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
            imagedestroy($temp);
        } else {
            imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
        }
        return true;
    }
}
