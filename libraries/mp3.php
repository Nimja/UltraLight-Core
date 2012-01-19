<?php

/*
  //Merge two files
  $path = 'path.mp3';
  $path1 = 'path1.mp3';
  $mp3 = new mp3($path);

  $newpath = 'path.mp3';
  $mp3->striptags();

  $mp3_1 = new mp3($path1);
  $mp3->mergeBehind($mp3_1);
  $mp3->striptags();
  $mp3->setIdv3_2('01','Track Title','Artist','Album','Year','Genre','Comments','Composer','OrigArtist',
  'Copyright','url','encodedBy');
  $mp3->save($newpath);


  //Extract 30 seconds starting after 10 seconds.
  $path = 'path.mp3';
  $mp3 = new mp3($path);
  $mp3_1 = $mp3->extract(10,30);
  $mp3_1->save('newpath.mp3');

  //Extract the exact length of time
  $path = 'path.mp3';
  $mp3 = new mp3($path);
  $mp3->setFileInfoExact();
  echo $mp3->time;
  //note that this is the exact length!
 */

class MP3 {

	protected $_str = NULL;
	protected $_time;
	protected $_frames;

	/**
	 * Create MP3 class with a filename.
	 * @param type $file 
	 */
	function __construct($file = NULL)
	{
		ini_set('memory_limit', '2048M');
		if (!empty($file) && file_exists($file)) {
			$this->_str = file_get_contents($file);
		}
	}

	/**
	 * 
	 * 
	 * @param type $str 
	 */
	public function setStr($str)
	{
		$this->_str = $str;
	}

	public function getStr()
	{
		return $this->_str;
	}

	/**
	 * Parse the string to get the contents.
	 * 
	 * @return boolean Success. 
	 */
	public function setFileInfoExact()
	{
		$maxStrLen = strlen($this->_str);
		$currentStrPos = strpos($this->_str, chr(255));
		$framesCount = 0;
		$time = 0;
		while ($currentStrPos < $maxStrLen) {
			$str = substr($this->_str, $currentStrPos, 4);
			$strlen = strlen($str);
			$parts = array();
			for ($i = 0; $i < $strlen; $i++) {
				$parts[] = $this->decbinFill(ord($str[$i]), 8);
			}
			if ($parts[0] != "11111111") {
				if (($maxStrLen - 128) > $currentStrPos) {
					return FALSE;
				} else {
					$this->_time = $time;
					$this->_frames = $framesCount;
					return TRUE;
				}
			}
			$a = $this->doFrameStuff($parts);
			$currentStrPos += $a[0];
			$time += $a[1];
			$framesCount++;
		}
		$this->_time = $time;
		$this->_frames = $framesCount;
		return TRUE;
	}

	/**
	 * Extract part from the MP3, based on seconds.
	 * 
	 * @param int $start Offset.
	 * @param int $length Length.
	 * @return MP3 The MP3 object with the extract.
	 */
	public function extract($start, $length)
	{
		$maxStrLen = strlen($this->_str);
		$currentStrPos = strpos($this->_str, chr(255));
		$framesCount = 0;
		$time = 0;
		$startCount = -1;
		$endCount = -1;
		while ($currentStrPos < $maxStrLen) {
			if ($startCount == -1 && $time >= $start) {
				$startCount = $currentStrPos;
			}
			if ($endCount == -1 && $time >= ($start + $length)) {
				$endCount = $currentStrPos - $startCount;
			}
			$doFrame = true;
			$str = substr($this->_str, $currentStrPos, 4);
			$strlen = strlen($str);
			$parts = array();
			for ($i = 0; $i < $strlen; $i++) {
				$parts[] = $this->decbinFill(ord($str[$i]), 8);
			}
			if ($parts[0] != "11111111") {
				if (($maxStrLen - 128) > $currentStrPos) {
					$doFrame = false;
				} else {
					$doFrame = false;
				}
			}
			if ($doFrame) {
				$a = $this->doFrameStuff($parts);
				$currentStrPos += $a[0];
				$time += $a[1];
				$framesCount++;
			} else {
				break;
			}
		}
		$mp3 = new MP3();
		if ($endCount == -1) {
			$endCount = $maxStrLen - $startCount;
		}
		if ($startCount != -1 && $endCount != -1) {
			$mp3->setStr(substr($this->_str, $startCount, $endCount));
		}
		return $mp3;
	}

	function decbinFill($dec, $length = 0)
	{
		$str = decbin($dec);
		return str_pad($str, $length, '0', STR_PAD_LEFT);
		/*
		  $nulls = $length - strlen($str);
		  if ($nulls > 0) {
		  for ($i = 0; $i < $nulls; $i++) {
		  $str = '0' . $str;
		  }
		  }
		  return $str;
		 */
	}

	function doFrameStuff($parts)
	{
		//Get Audio Version 
		$errors = array();
		switch (substr($parts[1], 3, 2)) {
			case '01':
				$errors[] = 'Reserved audio version';
				break;
			case '00':
				$audio = 2.5;
				break;
			case '10':
				$audio = 2;
				break;
			case '11':
				$audio = 1;
				break;
		}
		//Get Layer 
		switch (substr($parts[1], 5, 2)) {
			case '01':
				$layer = 3;
				break;
			case '00':
				$errors[] = 'Reserved layer';
				break;
			case '10':
				$layer = 2;
				break;
			case '11':
				$layer = 1;
				break;
		}
		//Get Bitrate 
		$bitFlag = substr($parts[2], 0, 4);
		$bitArray = array(
			'0000' => array(free, free, free, free, free),
			'0001' => array(32, 32, 32, 32, 8),
			'0010' => array(64, 48, 40, 48, 16),
			'0011' => array(96, 56, 48, 56, 24),
			'0100' => array(128, 64, 56, 64, 32),
			'0101' => array(160, 80, 64, 80, 40),
			'0110' => array(192, 96, 80, 96, 48),
			'0111' => array(224, 112, 96, 112, 56),
			'1000' => array(256, 128, 112, 128, 64),
			'1001' => array(288, 160, 128, 144, 80),
			'1010' => array(320, 192, 160, 160, 96),
			'1011' => array(352, 224, 192, 176, 112),
			'1100' => array(384, 256, 224, 192, 128),
			'1101' => array(416, 320, 256, 224, 144),
			'1110' => array(448, 384, 320, 256, 160),
			'1111' => array(bad, bad, bad, bad, bad)
		);
		$bitPart = $bitArray[$bitFlag];
		$bitArrayNumber;
		if ($audio == 1) {
			switch ($layer) {
				case 1:
					$bitArrayNumber = 0;
					break;
				case 2:
					$bitArrayNumber = 1;
					break;
				case 3:
					$bitArrayNumber = 2;
					break;
			}
		} else {
			switch ($layer) {
				case 1:
					$bitArrayNumber = 3;
					break;
				case 2:
					$bitArrayNumber = 4;
					break;
				case 3:
					$bitArrayNumber = 4;
					break;
			}
		}
		$bitRate = $bitPart[$bitArrayNumber];
		//Get Frequency 
		$frequencies = array(
			1 => array('00' => 44100,
				'01' => 48000,
				'10' => 32000,
				'11' => 'reserved'),
			2 => array(),
			2.5 => array());
		$freq = $frequencies[$audio][substr($parts[2], 4, 2)];
		//IsPadded? 
		$padding = substr($parts[2], 6, 1);
		if ($layer == 3 || $layer == 2) {
			//FrameLengthInBytes = 144 * BitRate / SampleRate + Padding 
			$frameLength = 144 * $bitRate * 1000 / $freq + $padding;
		}
		$frameLength = floor($frameLength);
		$seconds += $frameLength * 8 / ($bitRate * 1000);
		return array($frameLength, $seconds);
		//Calculate next when next frame starts. 
		//Capture next frame.     
	}

	/**
	 * Set IDv3 2 tags.
	 * 
	 * @param array $id3info - Array with one of the following; 
	 * track, title, artist, album, year, genre, comments, composer, origArtist, copyright, url, encodedBy
	 * @return boolean Success.
	 */
	public function setIdv3_2($id3info)
	{
		if (empty($id3info))
			return FALSE;

		$parts = array('track', 'title', 'artist', 'album', 'year', 'genre', 'comments', 'composer', 'origArtist', 'copyright', 'url', 'encodedBy');
		foreach ($parts as $part) {
			$$part = !empty($id3info[$part]) ? $id3info[$part] : '';
		}

		$this->striptags();

		$urlLength = (int) (strlen($url) + 2);
		$copyrightLength = (int) (strlen($copyright) + 1);
		$origArtistLength = (int) (strlen($origArtist) + 1);
		$composerLength = (int) (strlen($composer) + 1);
		$commentsLength = (int) strlen($comments) + 5;
		$titleLength = (int) strlen($title) + 1;
		$artistLength = (int) strlen($artist) + 1;
		$albumLength = (int) strlen($album) + 1;
		$genreLength = (int) strlen($genre) + 1;
		$encodedByLength = (int) (strlen($encodedBy) + 1);
		$trackLength = (int) strlen($track) + 1;
		$yearLength = (int) strlen($year) + 1;
		$str .= chr(73); //I 
		$str .= chr(68); //D 
		$str .= chr(51); //3 
		$str .= chr(3); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(8); // 
		$str .= chr(53); //5 
		$str .= chr(84); //T 
		$str .= chr(82); //R 
		$str .= chr(67); //C 
		$str .= chr(75); //K 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($trackLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $track;
		$str .= chr(84); //T 
		$str .= chr(69); //E 
		$str .= chr(78); //N 
		$str .= chr(67); //C 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($encodedByLength); // 
		$str .= chr(64); //@ 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $encodedBy;
		$str .= chr(87); //W 
		$str .= chr(88); //X 
		$str .= chr(88); //X 
		$str .= chr(88); //X 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($urlLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $url;
		$str .= chr(84); //T 
		$str .= chr(67); //C 
		$str .= chr(79); //O 
		$str .= chr(80); //P 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($copyrightLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $copyright;
		$str .= chr(84); //T 
		$str .= chr(79); //O 
		$str .= chr(80); //P 
		$str .= chr(69); //E 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($origArtistLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $origArtist;
		$str .= chr(84); //T 
		$str .= chr(67); //C 
		$str .= chr(79); //O 
		$str .= chr(77); //M 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($composerLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $composer;
		$str .= chr(67); //C 
		$str .= chr(79); //O 
		$str .= chr(77); //M 
		$str .= chr(77); //M 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($commentsLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(9); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $comments;
		$str .= chr(84); //T 

		$str .= chr(67); //C 
		$str .= chr(79); //O 
		$str .= chr(78); //N 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($genreLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $genre;
		$str .= chr(84); //T 
		$str .= chr(89); //Y 
		$str .= chr(69); //E 
		$str .= chr(82); //R 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($yearLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $year;
		$str .= chr(84); //T 
		$str .= chr(65); //A 
		$str .= chr(76); //L 
		$str .= chr(66); //B 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($albumLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $album;
		$str .= chr(84); //T 
		$str .= chr(80); //P 
		$str .= chr(69); //E 
		$str .= chr(49); //1 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($artistLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $artist;
		$str .= chr(84); //T 
		$str .= chr(73); //I 
		$str .= chr(84); //T 
		$str .= chr(50); //2 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr($titleLength); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= chr(0); // 
		$str .= $title;
		$this->_str = $str . $this->_str;
	}

	/**
	 * Merge MP3 behind current mp3.
	 * 
	 * @param MP3 $mp3 
	 */
	public function mergeBehind(MP3 $mp3)
	{
		$this->_str .= $mp3->getStr();
	}

	/**
	 * Merge MP3 in front of current mp3.
	 * 
	 * @param MP3 $mp3 
	 */
	public function mergeInfront(MP3 $mp3)
	{
		$this->_str = $mp3->getStr() . $this->_str;
	}

	/**
	 * Get end of IDvx tag.
	 * 
	 * @return type 
	 */
	protected function getIdvEnd()
	{
		$strlen = strlen($this->_str);
		$str = substr($this->_str, ($strlen - 128));
		$str1 = substr($str, 0, 3);
		if (strtolower($str1) == strtolower('TAG')) {
			return $str;
		} else {
			return false;
		}
	}

	protected function getStart()
	{
		$strlen = strlen($this->_str);
		for ($i = 0; $i < $strlen; $i++) {
			$v = substr($this->_str, $i, 1);
			$value = ord($v);
			if ($value == 255) {
				return $i;
			}
		}
	}

	/**
	 * Strip IDvX tags.
	 * 
	 * @return bool success 
	 */
	public function striptags()
	{
		if (blank($this->_str))
			return FALSE;

		//Remove start stuff... 
		$s = $start = $this->getStart();
		if ($s === FALSE) {
			return FALSE;
		} else {
			$this->_str = substr($this->_str, $start);
		}
		//Remove end tag stuff 
		$end = $this->getIdvEnd();
		if ($end !== false) {
			$this->_str = substr($this->_str, 0, (strlen($this->_str) - 129));
		}
		return TRUE;
	}

	/**
	 * Output file to filesystem.
	 * 
	 * @param type $file Filename + path.
	 */
	public function save($file)
	{
		file_put_contents($file, $this->_str);
	}

	/**
	 * Join multiple MP3 files together WITH THE SAME BITRATE!!!
	 * 
	 * @param string $newFile File + path of the new file.
	 * @param array $arrayOfFiles Array of files in order.
	 */
	public static function multiJoin($newFile, $files, $save = TRUE, $id3info = NULL)
	{
		if (empty($files)) {
			show_error('No files to join.');
			return FALSE;
		}

		$base = NULL;

		#Go over each file, make sure it exists and has data.
		foreach ($files as $file) {
			$mp3 = new MP3($file);
			$mp3->striptags();
			if (blank($mp3->_str))
				continue;

			if (empty($base)) {
				$base = $mp3;
				$base->setIdv3_2($id3info);
			} else {
				$base->mergeBehind($mp3);
			}
		}
		#save to file or output to browser.
		if ($save) {
			$base->save($newFile);
			return TRUE;
		} else {
			$base->output($newFile);
		}
		return FALSE;
	}

	/**
	 * Output to browser.
	 * 
	 * @param type $path 
	 */
	public function output($filename = NULL)
	{
		Load::output('audio/mp3', $this->_str, 0, $filename);
	}

}