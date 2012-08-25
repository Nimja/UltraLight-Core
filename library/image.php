<?php
//Loading an image file.
class Library_Image {
	const MODE_RATIO_CROPPING = 0;
	const MODE_RATIO_FITTING = 1;
	const MODE_FORCE_WIDTH = 2;
	const MODE_FORCE_HEIGHT = 3;
	const MODE_RATIO_FITTING_NO_BORDERS = 4;
	

	var $src = NULL;
	var $img = NULL;
	var $size = NULL;
	var $mp = NULL;
	var $error = FALSE;

	function __construct()
	{
		$this->error = FALSE;
	}

	public function load($src)
	{
		$img = null;

		$parts = pathinfo($src);
		$ext = $parts['extension'];
		#Load image
		switch ($ext) {
			case 'png': $img = imagecreatefrompng($src) or Show::error($src, 'PNG could not be loaded, corrupt?');
				break;
			case 'jpg':
			case 'jpeg': $img = imagecreatefromjpeg($src) or Show::error($src, 'JPG could not be loaded, corrupt?');
				break;
			case 'gif': $img = imagecreatefromgif($src) or Show::error($src, 'GIF could not be loaded, corrupt?');
				break;
		}
		$this->src = $src;
		$this->img = $img;
		if (empty($img)) {
			Show::error($src, 'File is not an image.');
			return;
		}
		$size = getimagesize($src);
		$this->size = $size;
		$this->mp = floor($size[0] * $size[1] / 100000) / 10;
	}

	private function fastimagecopyresampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3)
	{
		# Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
		# Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
		# Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
		# Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
		#
		# Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
		# Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
		# 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
		# 2 = Up to 95 times faster.	Images appear a little sharp, some prefer this over a quality of 3.
		# 3 = Up to 60 times faster.	Will give high quality smooth results very close to imagecopyresampled, just faster.
		# 4 = Up to 25 times faster.	Almost identical to imagecopyresampled for most images.
		# 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

		if (empty($src_image) || empty($dst_image) || $quality <= 0) {
			return false;
		}
		if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
			$temp = imagecreatetruecolor($dst_w * $quality + 1, $dst_h * $quality + 1);
			imagecopyresized($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
			imagecopyresampled($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
			imagedestroy($temp);
		} else
			imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
		return true;
	}

	#Resize the loaded image into a different size.
	#Modes:
	# 0 = crop (cut edges)
	# 1 = fit inside the border (fill edges);
	# 2 = fit horizontally (fill edges);
	# 3 = fit vertically (fill edges);
	# 4 = fit (result might be smaller in w/h

	public function resize($src, $dst, $width, $height, $mode = 0, $quality = 75)
	{
		if ($src != $this->src) {
			$this->load($src);
		}
		#Sanity check
		if ($this->error || empty($this->img) || empty($this->size)) {
			Show::error('Image not loaded');
			return;
		}

		#Desired size
		$desired = array('w' => $width, 'h' => $height);
		#original size
		$source = array('x' => 0, 'y' => 0, 'w' => $this->size[0], 'h' => $this->size[1]);

		#Sanity check.
		$fileTo = $dst;
		#Delete if it existed before.
		if (file_exists($fileTo)) {
			return;
		}

		$dest = $source;
		#Cropping or scaling.
		$scaleX = $desired['w'] / $source['w'];
		$scaleY = $desired['h'] / $source['h'];
		$scale = 1;
		switch ($mode) {
			case self::MODE_FORCE_HEIGHT: //Force height
				$scale = $scaleY;
				break;
			case self::MODE_FORCE_WIDTH: //Force width
				$scale = $scaleX;
				break;
			case self::MODE_RATIO_FITTING_NO_BORDERS: //Fit without additional borders.
			case self::MODE_RATIO_FITTING: //Fit
				$scale = ($scaleX < $scaleY) ? $scaleX : $scaleY;
				break;
			default: //Ratio cropping
				$scale = ($scaleX > $scaleY) ? $scaleX : $scaleY;
				break;
		}
		#If the destination is larger than the source.
		if ($scale > 1) {
			imagejpeg($this->img, $fileTo, $quality);
			return;
		}
		$dest['w'] = floor($source['w'] * $scale);
		$dest['h'] = floor($source['h'] * $scale);

		$fill = FALSE;
		if ($mode == 4) {
			$desired['w'] = $dest['w'];
			$desired['h'] = $dest['h'];
		} else {
			//Horizontal cropping
			if ($dest['w'] > $desired['w']) {
				$newW = $desired['w'] / $scale;
				$source['x'] = ($source['w'] - $newW) / 2;
				$source['w'] = $newW;
				$dest['w'] = $desired['w'];
				//Horizontal fitting
			} elseif ($dest['w'] < $desired['w']) {
				$fill = TRUE;
				$dest['x'] = ($desired['w'] - $dest['w']) / 2;
			}

			//Vertical cropping
			if ($dest['h'] > $desired['h']) {
				$newH = $desired['h'] / $scale;
				$source['y'] = ($source['h'] - $newH) / 2;
				$source['h'] = $newH;
				$dest['h'] = $desired['h'];
				//Vertical fitting
			} elseif ($dest['h'] < $desired['h']) {
				$fill = TRUE;
				$dest['y'] = ($desired['h'] - $dest['h']) / 2;
			}
		}


		$dst = imagecreatetruecolor($desired['w'], $desired['h']);
		// Resample the original image into the resized canvas we set up earlier
		$speed = '2';
		$this->fastimagecopyresampled($dst, $this->img, $dest['x'], $dest['y'], $source['x'], $source['y'], $dest['w'], $dest['h'], $source['w'], $source['h'], 2);

		imagejpeg($dst, $fileTo, $quality);
		chmod($fileTo, 0666);
		imagedestroy($dst);
	}

	function clean()
	{
		if (!empty($this->img)) {
			imagedestroy($this->img);
		}
		$this->error = FALSE;
	}

}