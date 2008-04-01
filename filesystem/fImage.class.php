<?php
/**
 * Represents an image on the filesystem, also provides image manipulation functionality
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fImage
 * 
 * @uses  fCore
 * @uses  fEnvironmentException
 * @uses  fFilesystem
 * @uses  fProgrammerException
 * @uses  fValidationException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-12-19]
 */
class fImage extends fFile
{     
	/**
	 * The processor to use for the image manipulation
	 * 
	 * @var string 
	 */
	static private $processor = NULL;
	
	/**
	 * If we are using the imagemagick processor, this stores the path to the binaries
	 * 
	 * @var string 
	 */
	static private $imagemagick_dir = NULL;
	
	/**
	 * The modifications to perform on the image when it is saved
	 * 
	 * @var array 
	 */
	private $pending_modifications = array();
	
	/**
	 * Creates an object to represent an image on the filesystem
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $image      The full path to the image
	 * @param  object $exception  An exception that was tossed during the object creation process
	 * @return fImage
	 */
	public function __construct($image, Exception $exception=NULL) 
	{
		self::determineProcessor();
		
		try {

			self::verifyImageCompatible($image);
			parent::__construct($image, $exception);
				
		} catch (fExpectedException $e) {
			$this->file = NULL;
			$this->exception = $e;	
		}
	}
	
	
	/**
	 * Sets the directory the ImageMagick binary is installed in and tells the class to use ImageMagick even if GD is installed
	 * 
	 * @param  string $directory   The directory ImageMagick is installed in
	 * @return void
	 */
	static public function setImageMagickDirectory($directory) 
	{
		$directory = fDirectory::makeCanonical($directory);
		
		self::checkImageMagickBinary($directory);
		
		self::$imagemagick_dir = $directory;
		self::$processor = 'imagemagick';
	}
	
	
	/**
	 * Sets a custom directory to use for the ImageMagick temporary files
	 * 
	 * @param  string $temp_dir   The directory to use for the ImageMagick temp dir
	 * @return void
	 */
	static public function setImageMagickTempDir($temp_dir) 
	{
		if (!in_array(strtolower(ini_get('safe_mode')), array('0', '', 'off'))) {
			if (stripos(ini_get('safe_mode_allowed_env_vars'), 'magick_') === FALSE) {
				fCore::toss('fEnvironmentException', 'Safe mode is turned on and the safe_mode_allowed_env_vars ini setting is not set to allow environmental variables that start with MAGICK_');
			}	
		}
		putenv('MAGICK_TMPDIR=' . $temp_dir);	
	}
	
	
	/**
	 * Checks to make sure the class can handle the image file specified
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  string $image   The image to check for incompatibility
	 * @return void
	 */
	static public function verifyImageCompatible($image)
	{
		self::determineProcessor();
		
		if (!file_exists($image)) {
			fCore::toss('fValidationException', 'The image specified does not exist');	
		}
		
		$info = self::getInfo($image);
		
		if ($info['type'] === NULL) {
			fCore::toss('fValidationException', 'The image specified is not a GIF, JPG, PNG or TIFF file');
		}
	}
	
	
	/**
	 * Returns an array of acceptable mimetype for the processor installed
	 * 
	 * @return array  The mimetypes that the installed processor can manipulate
	 */
	static public function getCompatibleMimetypes()
	{
		self::determineProcessor();
		
		$mimetypes = array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png');
		
		if (self::$processor == 'imagemagick') {
			$mimetypes[] = 'image/tiff';
		}
		
		return $mimetypes;
	}
	
	
	/**
	 * Determines what processor to use for image manipulation
	 * 
	 * @return void
	 */
	static private function determineProcessor()
	{
		// Determine what processor to use
		if (self::$processor === NULL) {
			
			// Look for imagemagick first since it can handle more than GD
			try {
				
				if (fCore::getOS() == 'windows') {
					$win_search = 'dir /B "C:\Program Files\ImageMagick*"';
					$win_output = trim(shell_exec($win_search));
					
					if (stripos($win_output, 'File not found') !== FALSE) {
						throw new Exception();    
					}
					
					$path = 'C:\Program Files\\' . $win_output . '\\';
				}
				
				if (fCore::getOS() == 'linux/unix') {
					$nix_search = 'whereis -b convert';
					$nix_output = trim(str_replace('convert: ', '', shell_exec($nix_search)));
					
					if (empty($nix_output)) {
						throw new Exception();
					}
				
					$path = preg_replace('#^(.*)convert$#i', '\1', $nix_output); 
				}
				
				self::checkImageMagickBinary($path);
				
				self::$imagemagick_dir = $path;
				self::$processor = 'imagemagick';
				
			} catch (Exception $e) {

				// Look for GD last since it does not support tiff files
				if (function_exists('gd_info')) {
					
					self::$processor = 'gd';
				
				} else {
					self::$processor = 'none';
				}
			}
		}	
	}
	
	
	/**
	 * Checks to make sure we can get to and execute the ImageMagick convert binary
	 * 
	 * @param  string $path   The path to ImageMagick on the filesystem
	 * @return void
	 */
	static private function checkImageMagickBinary($path)
	{
		if (!file_exists($path . 'convert.exe') && !file_exists($path . 'convert')) {
			fCore::toss('fEnvironmentException', 'The ImageMagick convert binary could not be found');	
		}
		if (!is_executable($path . 'convert.exe') && !is_executable($path . 'convert')) {
			fCore::toss('fEnvironmentException', 'The ImageMagick convert binary is not executable');	
		}
		
		// Make sure we can execute the convert binary
		if (!in_array(strtolower(ini_get('safe_mode')), array('0', '', 'off'))) {
			$exec_dirs = explode(';', ini_get('safe_mode_exec_dir'));
			$found = FALSE;
			foreach ($exec_dirs as $exec_dir) {
				if (stripos($path, $exec_dir) === 0) {
					$found = TRUE;	
				}
			}
			if (!$found) {
				fCore::toss('fEnvironmentException', 'Safe mode is turned on and the ImageMagick convert binary is not in one of the paths defined by the safe_mode_exec_dir ini setting');
			}	
		}
	}
	
	
	/**
	 * Gets the dimensions and type of an image stored on the filesystem
	 * 
	 * The 'type' element will be one of the following:
	 *  - {null} (File type is not supported)
	 *  - 'jpg'
	 *  - 'gif'
	 *  - 'png'
	 *  - 'tif'
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string $image    The image to get stats for
	 * @param  string $element  The element to retrieve ('type', 'width', 'height')
	 * @return array  An associative array: 'type' => {mixed}, 'width' => {integer}, 'height' => {integer}
	 */
	static public function getInfo($image, $element=NULL)
	{
		$image_info = @getimagesize($image);
		if ($image_info == FALSE) {
			fCore::toss('fValidationException', 'The file specified is not an image');    
		}
		
		if ($element !== NULL && !in_array($element, array('type', 'width', 'height'))) {
			fCore::toss('fProgrammerException', 'Invalid element requested');  
		}
		
		$types = array(IMAGETYPE_GIF     => 'gif',
					   IMAGETYPE_JPEG    => 'jpg',
					   IMAGETYPE_PNG     => 'png',
					   IMAGETYPE_TIFF_II => 'tif',
					   IMAGETYPE_TIFF_MM => 'tif');
		
		$output           = array();
		$output['width']  = $image_info[0];
		$output['height'] = $image_info[1];
		if (isset($types[$image_info[2]])) {
			$output['type'] = $types[$image_info[2]];
		} else {
			$output['type'] = NULL;	
		}
		
		if ($element !== NULL) {
			return $output[$element];    
		}
		
		return $output;	
	}

	
	/**
	 * Sets the image to be resized proportionally to a specific sized canvas. Will only size down an image. Resize does not occur until {@link fImage::saveChanges()} is called.
	 * 
	 * @param  integer $canvas_width   The width of the canvas to fit the image on, 0 for no constraint
	 * @param  integer $canvas_height  The height of the canvas to fit the image on, 0 for no constraint
	 * @return void
	 */
	public function resize($canvas_width, $canvas_height) 
	{
		$this->tossIfException();
		
		// Make sure the user input is valid
		if (!is_int($canvas_width) || $canvas_width < 0) {
			fCore::toss('fProgrammerException', 'The canvas width specified is not an integer or is less than zero');  
		}
		if (!is_int($canvas_height) || $canvas_height < 0) {
			fCore::toss('fProgrammerException', 'The canvas height specified is not an integer or is less than zero');   
		}
		if ($canvas_width == 0 && $canvas_height == 0) {
			fCore::toss('fProgrammerException', 'The canvas width and canvas height are both zero, no resizing will occur');     
		}

		// Calculate what the new dimensions will be
		$dim = $this->getCurrentDimensions();
		$orig_width  = $dim['width'];
		$orig_height = $dim['height'];
		
		if ($canvas_width == 0) {
			$new_height = $canvas_height;
			$new_width  = (int) (($new_height/$orig_height) * $orig_width);    
		
		} elseif ($canvas_height == 0) {
			$new_width  = $canvas_width;
			$new_height = (int) (($new_width/$orig_width) * $orig_height);    
		
		} else {
			$orig_ratio   = $orig_width/$orig_height;
			$canvas_ratio = $canvas_width/$canvas_height;
			
			if ($canvas_ratio > $orig_ratio) {
				$new_height = $canvas_height;
				$new_width  = (int) ($orig_ratio * $new_height);
			} else {
				$new_width  = $canvas_width;
				$new_height = (int) ($new_width / $orig_ratio);		        
			}
		}

		// If the size did not go down, don't even record the modification
		if ($orig_width <= $new_width || $orig_height <= $new_height) {
			return;	
		}
		
		// Record what we are supposed to do
		$this->pending_modifications[] = array(
			'operation'  => 'resize',
			'width'      => $new_width,
			'height'     => $new_height,
			'old_width'  => $orig_width,
			'old_height' => $orig_height
		);
	}
  
	
	/**
	 * Crops the biggest area possible from the center of the image that matches the ratio provided. Crop does not occur until {@link fImage::saveChanges()} is called.
	 * 
	 * @param  numeric $ratio_width   The width to crop the image to
	 * @param  numeric $ratio_height  The height to crop the image to
	 * @return void
	 */
	public function cropToRatio($ratio_width, $ratio_height) 
	{
		$this->tossIfException();
		
		// Make sure the user input is valid
		if (!is_numeric($ratio_width) || $ratio_width < 0) {
			fCore::toss('fProgrammerException', 'The ratio width specified is not a number or is less than or equal to zero');  
		}
		if (!is_int($ratio_height) || $ratio_height < 0) {
			fCore::toss('fProgrammerException', 'The ratio height specified is not a number or is less than or equal to zero');   
		}
		
		// Get the new dimensions
		$dim = $this->getCurrentDimensions();
		$orig_width  = $dim['width'];
		$orig_height = $dim['height'];
		
		$orig_ratio = $orig_width / $orig_height;
		$new_ratio  = $ratio_width / $ratio_height;
			
		if ($orig_ratio > $new_ratio) {
			$new_height = $orig_height;
			$new_width  = (int) ($new_ratio * $new_height);    
		} else {
			$new_width  = $orig_width;
			$new_height = (int) ($new_width / $new_ratio);   
		}
			
		// Figure out where to crop from
		$crop_from_x = floor(($orig_width - $new_width) / 2);
		$crop_from_y = floor(($orig_height - $new_height) / 2);
		
		$crop_from_x = ($crop_from_x < 0) ? 0 : $crop_from_x;
		$crop_from_y = ($crop_from_y < 0) ? 0 : $crop_from_y;
			
		// If nothing changed, don't even record the modification
		if ($orig_width == $new_width && $orig_height == $new_height) {
			return;	
		}
		
		// Record what we are supposed to do
		$this->pending_modifications[] = array(
			'operation'  => 'crop',
			'start_x'    => $crop_from_x,
			'start_y'    => $crop_from_y,
			'width'      => $new_width,
			'height'     => $new_height,
			'old_width'  => $orig_width,
			'old_height' => $orig_height
		);
	}
	
	
	/**
	 * Converts the image to grayscale. Desaturation does not occur until {@link fImage::saveChanges()} is called.
	 * 
	 * @return void
	 */
	public function desaturate() 
	{
		$this->tossIfException();
		
		$dim = $this->getCurrentDimensions();
		
		// Record what we are supposed to do
		$this->pending_modifications[] = array(
			'operation'  => 'desaturate',
			'width'      => $dim['width'],
			'height'     => $dim['height']
		);
	}
	
	
	/**
	 * Saves any changes to the image
	 * 
	 * @param  string $new_image_type  The new file type for the image, can be 'jpg', 'gif' or 'png'
	 * @return void
	 */
	public function saveChanges($new_image_type=NULL) 
	{
		$this->tossIfException();
		
		if (self::$processor == 'none') {
			fCore::toss('fEnvironmentException', "The changes to the image can't be saved because neither the GD extension or ImageMagick appears to be installed on the server");   
		}
		
		$info = self::getInfo($this->file);     
		if ($info['type'] == 'tif' && self::$processor == 'gd') {
			fCore::toss('fEnvironmentException', 'The image specified is a TIFF file and the GD extension can not handle TIFF files');    
		}
		
		if ($new_image_type !== NULL && !in_array($new_image_type, array('jpg', 'gif', 'png'))) {
			fCore::toss('fProgrammerException', 'Invalid new image type specified');  
		}
		
		if ($new_image_type) {
			$output_file = fFilesystem::createUniqueName($this->file, $new_image_type);		
		} else {
			$output_file = $this->file;	
		}
		
		if (self::$processor == 'gd') {
			$this->processWithGD($output_file);	
		} elseif (self::$processor == 'imagemagick') {
			$this->processWithImageMagick($output_file);
		}
		
		// If we created a new image, delete the old one
		if ($output_file != $this->file) {
			unlink($this->file);	
		}
		fFilesystem::updateFilenameMap($this->file, $output_file);
		
		$this->pending_modifications = array();
	}
	
	
	/**
	 * Gets the dimensions of the image as of the last modification
	 * 
	 * @return array  An associative array: 'width' => {integer}, 'height' => {integer}
	 */
	private function getCurrentDimensions()
	{
		if (empty($this->pending_modifications)) {
			$output = self::getInfo($this->file);
			unset($output['type']);	
		
		} else {
			$last_modification = $this->pending_modifications[sizeof($this->pending_modifications)-1];
			$output['width']  = $last_modification['width'];
			$output['height'] = $last_modification['height'];
		}
		
		return $output;
	}
	
	
	/**
	 * Checks if the current image is an animated gif
	 * 
	 * @return boolean  If the image is an animated gif
	 */
	private function isAnimatedGif()
	{
		$info = self::getInfo($this->file);
		if ($info['type'] == 'gif') {
			if (preg_match('#\x00\x21\xF9\x04.{4}\x00\x2C#s', file_get_contents($this->file))) {
				return TRUE;
			}	
		}
		return FALSE;
	}
	
	
	/**
	 * Processes the current image using GD
	 * 
	 * @param  string $output_file  The file to save the image to
	 * @return void
	 */
	private function processWithGD($output_file)
	{
		if (empty($this->pending_modifications)) {
			return;	
		}
		
		$info = self::getInfo($this->file);
		
		switch ($info['type']) {
			case 'gif':
				$gd_res = imagecreatefromgif($this->file);	
				break;
			case 'jpg':
				$gd_res = imagecreatefromjpeg($this->file);	
				break;
			case 'png':
				$gd_res = imagecreatefrompng($this->file);	
				break;
		}
		
		
		foreach ($this->pending_modifications as $mod) {
			
			// Perform the resize operation
			if ($mod['operation'] == 'resize') {
				
				$new_gd_res = imagecreatetruecolor($mod['width'], $mod['height']);
				imagecopyresampled($new_gd_res,       $gd_res,
								   0,                 0,
								   0,                 0,
								   $mod['width'],     $mod['height'],
								   $mod['old_width'], $mod['old_height']);
				imagedestroy($gd_res);
				$gd_res = $new_gd_res;
				
				
			// Perform the crop operation
			} elseif ($mod['operation'] == 'crop') {
			
				$new_gd_res = imagecreatetruecolor($mod['width'], $mod['height']);
				imagecopyresampled($new_gd_res,       $gd_res,
								   0,                 0,
								   $mod['start_x'],   $mod['start_y'],
								   $mod['width'],     $mod['height'],
								   $mod['width'],     $mod['height']);
				imagedestroy($gd_res);
				$gd_res = $new_gd_res;
				
				
			// Perform the desaturate operation
			} elseif ($mod['operation'] == 'desaturate') {
			
				$new_gd_res = imagecreate($mod['width'], $mod['height']);

				// Create a palette of grays
				$grays = array();
				for ($i=0; $i < 256; $i++) {
					$grays[$i] = imagecolorallocate($new_gd_res, $i, $i, $i);
				}

				// Loop through every pixel and convert the rgb values to grays
				for ($x=0; $x < $mod['width']; $x++) {
					for ($y=0; $y < $mod['height']; $y++) {
						
						$color = imagecolorat($gd_res, $x, $y);
						$red   = ($color >> 16) & 0xFF;
						$green = ($color >> 8) & 0xFF;
						$blue  = $color & 0xFF;

						// Get the appropriate gray (http://en.wikipedia.org/wiki/YIQ)
						$yiq = round(($red * 0.299) + ($green * 0.587) + ($blue * 0.114));
						imagesetpixel($new_gd_res, $x, $y, $grays[$yiq]);
					}
				} 
				
				imagedestroy($gd_res);
				$gd_res = $new_gd_res;
			}	
		}
		
		// Save the file
		$info = fFilesystem::getPathInfo($output_file);
		
		switch ($info['extension']) {
			case 'gif':
				imagegif($gd_res, $output_file);	
				break;
			case 'jpg':
				imagejpeg($gd_res, $output_file, 90);	
				break;
			case 'png':
				imagepng($gd_res, $output_file);
				break;
		}
	}
	
	
	/**
	 * Processes the current image using ImageMagick
	 * 
	 * @param  string $output_file  The file to save the image to
	 * @return void
	 */
	private function processWithImageMagick($output_file)
	{
		if (empty($this->pending_modifications)) {
			return;	
		}
		
		$info = self::getInfo($this->file);
		
		$command_line  = escapeshellcmd(self::$imagemagick_dir . 'convert');
		$command_line .= ' ' . escapeshellarg($this->file) . ' ';
		
		// Animated gifs need to be coalesced
		if ($this->isAnimatedGif()) {
			$command_line .= ' -coalesce ';	
		}
		
		// TIFF files should be set to a depth of 8
		if ($info['type'] == 'tif') {
			$command_line .= ' -depth 8 ';	
		}
		
		foreach ($this->pending_modifications as $mod) {
			
			// Perform the resize operation
			if ($mod['operation'] == 'resize') {
				
				$command_line .= ' -resize ' . $mod['width'] . 'x' . $mod['height'] . ' ';
				
			// Perform the crop operation
			} elseif ($mod['operation'] == 'crop') {
			
				$command_line .= ' -crop ' . $mod['width'] . 'x' . $mod['height'];
				$command_line .= '+' . $mod['start_x'] . '+' . $mod['start_y'];
				$command_line .= ' -repage ' . $mod['width'] . 'x' . $mod['height'] . '+0+0 ';
				
			// Perform the desaturate operation
			} elseif ($mod['operation'] == 'desaturate') {
			
				$command_line .= ' -colorspace GRAY ';
				
			}	
		}
		
		// Default to the RGB colorspace
		if (strpos($command_line, ' -colorspace ')) {
			$command_line .= ' -colorspace RGB ';	
		}
		
		// Set up jpeg compression
		$info = fFilesystem::getPathInfo($output_file);
		if ($info['extension'] == 'jpg') {
			$command_line .= ' -compress JPEG -quality 90 ';	
		}
		
		$command_line .= ' ' . escapeshellarg($output_file);
		
		shell_exec($command_line);
	}
} 


/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */ 
?>