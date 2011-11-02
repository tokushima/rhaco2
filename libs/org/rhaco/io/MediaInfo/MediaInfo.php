<?php
module('MediaInfoException');
/**
 * Exif情報
 * @author tokushima
 * @see http://www.sno.phy.queensu.ca/~phil/exiftool/
 * @var choice $type @{"choices":["photo","video"]}
 * @var string $name
 * @var string $filename
 * @var integer $width
 * @var integer $height
 * @var timestamp $create_date
 * @var integer $size
 * @var string $make
 * @var string $model
 * @var number $longitude
 * @var number $latitude
 * @var number $rotation
 * @var time $duration
 * @var mixed{} $raw @{"hash":false}
 */
class MediaInfo extends Object{
	protected $type = 'photo';
	protected $name;
	protected $filename;
	protected $width;
	protected $height;
	protected $create_date;
	protected $size;
	protected $make;
	protected $model;
	protected $longitude;
	protected $latitude;
	protected $rotation = 0;
	protected $duration = 0;

	protected $raw;

	protected function __init__(){
		$this->create_date = time();
	}
	protected function __get_create_date__(){
		return ($this->create_date > 0) ? $this->create_date : null;
	}
	private function cmd($filename){
		$cmd = module_const('cmd','/usr/bin/exiftool');
		$out = Command::out($cmd.' '.$filename);
		return trim($out);
	}
	/**
	 * 動画か
	 * @return boolean
	 */
	public function is_video(){
		return ($this->type === 'video');
	}
	/**
	 * EXIFを取得する
	 * @param string $filename
	 * @param string $name
	 * @return $this
	 */
	static public function get($filename,$name=null){
		if(empty($filename)) throw new MediaInfoException('undef filename');
		$self = new self();
		$self->name($name);
		$self->size(sprintf('%u',@filesize($filename)));

		$info = getimagesize($filename);
		if($info !== false){
			try{
				switch($info[2]){
					case IMAGETYPE_JPEG:
						$exif = exif_read_data($filename);
							foreach($exif as $k => $v) $self->raw($k,$v);							
							try{ $self->filename(isset($exif['FileName']) ? $exif['FileName'] : basename($filename)); }catch(InvalidArgumentException $e){}
							try{ $self->width(isset($exif['ExifImageWidth']) ? $exif['ExifImageWidth'] : (isset($info[0]) ? $info[0] : null)); }catch(InvalidArgumentException $e){}
							try{ $self->height(isset($exif['ExifImageLength']) ? $exif['ExifImageLength'] : (isset($info[1]) ? $info[1] : null)); }catch(InvalidArgumentException $e){}
							try{ if(isset($exif['DateTimeOriginal'])) $self->create_date($exif['DateTimeOriginal']); }catch(InvalidArgumentException $e){}
							try{ if(isset($exif['Make'])) $self->make($exif['Make']); }catch(InvalidArgumentException $e){}
							try{
								if(isset($exif['Model'])){
									$self->model($exif['Model']);
								}else if(isset($exif['Camera Model Name'])){
									$self->model($exif['Camera Model Name']);								
								}
							 }catch(InvalidArgumentException $e){}
							 try{
								if(isset($exif['Orientation'])){
									switch($exif['Orientation']){
										case 3: $self->rotation(180); break;
										case 6: $self->rotation(90); break;
										case 8: $self->rotation(270); break;
									}				
								}
							}catch(InvalidArgumentException $e){}
							try{
								if(isset($exif['GPSLatitudeRef']) && isset($exif['GPSLatitude']) 
									&& isset($exif['GPSLongitudeRef']) && isset($exif['GPSLongitude'])
								){
									list($a,$b) = explode('/',$exif['GPSLatitude'][0]);
									$latitude = $a / $b;
									list($a,$b) = explode('/',$exif['GPSLatitude'][1]);
									$latitude = $latitude + ($a / $b / 60);
									list($a,$b) = explode('/',$exif['GPSLatitude'][2]);
									$latitude = $latitude + ($a / $b / 3600);
									
									list($a,$b) = explode('/',$exif['GPSLongitude'][0]);
									$longitude = $a / $b;
									list($a,$b) = explode('/',$exif['GPSLongitude'][1]);
									$longitude = $longitude + ($a / $b / 60);
									list($a,$b) = explode('/',$exif['GPSLongitude'][2]);
									$longitude = $longitude + ($a / $b / 3600);
								
									$self->latitude($latitude * (($exif['GPSLatitudeRef'] == 'N') ? 1 : -1));
									$self->longitude($longitude * (($exif['GPSLongitudeRef'] == 'E') ? 1 : -1));
								}
							}catch(InvalidArgumentException $e){}
						break;
					default:
						$self->type('photo');
						$self->filename(basename($filename));
						$self->width((isset($info[0]) ? $info[0] : null));
						$self->height((isset($info[1]) ? $info[1] : null));
						break;
				}
			}catch(ErrorException $e){
				throw new MediaInfoException('不明なメディアタイプです');
			}
		}else{
			$exif = array();
			$data = $self->cmd($filename);

			foreach(explode("\n",$data) as $line){
				list($label,$value) = explode(':',$line,2);
				$exif[trim($label)] = trim($value);
				$self->raw(trim($label),trim($value));
			}
			if(!isset($exif['MIME Type']) || strpos(strtolower($exif['MIME Type']),'video') === false) throw new MediaInfoException('不明なMIMEタイプです');

			$self->type('video');
			try{ $self->filename(isset($exif['File Name']) ? $exif['File Name'] : basename($filename));  }catch(InvalidArgumentException $e){}
			try{ $self->size(sprintf('%u',@filesize($filename))); }catch(InvalidArgumentException $e){}
			try{ if(isset($exif['Image Width'])) $self->width($exif['Image Width']); }catch(InvalidArgumentException $e){}
			try{ if(isset($exif['Image Height'])) $self->height($exif['Image Height']); }catch(InvalidArgumentException $e){}
			try{ if(isset($exif['Create Date'])) $self->create_date($exif['Create Date']); }catch(InvalidArgumentException $e){}
			try{ if(isset($exif['User Data mak'])) $self->make($exif['User Data mak']); }catch(InvalidArgumentException $e){}
			try{ if(isset($exif['User Data mod'])) $self->model($exif['User Data mod']); }catch(InvalidArgumentException $e){}
			try{
				if(isset($exif['User Data xyz'])){
					list($xy,$z) = explode('/',$exif['User Data xyz']);
					if(preg_match("/([\-\+][\d\.]+)([\-\+][\d\.]+)/",$xy)){
						$self->latitude((float)$xy[1]);
						$self->longitude((float)$xy[2]);
					}
				}
			}catch(InvalidArgumentException $e){}
			try{ if(isset($exif['Rotation'])) $self->rotation($exif['Rotation']); }catch(InvalidArgumentException $e){}
			try{ if(isset($exif['Duration'])) $self->duration($exif['Duration']); }catch(InvalidArgumentException $e){}
		}
		return $self;
	}
}