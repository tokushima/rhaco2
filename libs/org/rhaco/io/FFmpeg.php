<?php
import("org.rhaco.lang.DateUtil");
/**
 * @see http://ffmpeg.org/
 * @author tokushima
 */
class FFmpeg extends Object{
	private $cmd;

	protected $name;
	protected $ext;

	protected $filename;
	protected $framerate;
	protected $bitrate;
	protected $duration;

	protected $video_codec;
	protected $format;
	protected $height;
	protected $width;
	protected $aspect;
	protected $audio_codec;
	protected $samplerate;

	protected $frame_count;

	protected function __new__($file_path){
		$this->cmd = module_const("cmd","/usr/local/bin/ffmpeg");
		$info = Command::error(sprintf("%s -i %s",$this->cmd,$file_path));

		$file = new File($file_path);
		$this->name = $file->oname();
		$this->ext = $file->ext();

		$this->filename = $file_path;
		$this->framerate = preg_match("/frame rate: .+ -> ?([\d\.]+)/",$info,$match) ? (float)$match[1] : null;
		$this->bitrate = preg_match("/bitrate: (\d+)/",$info,$match) ? (float)$match[1] : null;
		$this->duration = preg_match("/Duration: ([\d\:\.]+)/",$info,$match) ? DateUtil::parse_time($match[1]) : null;
		if(preg_match("/Video: (.+)/",$info,$match)){
			$video = explode(",",$match[1]);
			if(isset($video[0])) $this->video_codec = trim($video[0]);
			if(isset($video[1])) $this->format = trim($video[1]);
			if(isset($video[2])){
				list($this->width,$this->height) = explode("x",trim($video[2]));
				if(preg_match("/(\d+) .+DAR (\d+\:\d+)/",$this->height,$match)) list(,$this->height,$this->aspect) = $match;
			}
			if(empty($this->aspect) && isset($video[3])) $this->aspect = preg_match("/DAR (\d+\:\d+)/",$video[3],$match) ? $match[1] :  null;
		}
		if(preg_match("/Audio: (.+)/",$info,$match)){
			$audio = explode(",",$match[1]);
			if(isset($audio[0])) $this->audio_codec = trim($audio[0]);
			if(isset($audio[1])) $this->samplerate = preg_match("/\d+/",$audio[1],$match) ? $match[0] : null;
		}
		$this->frame_count = ceil($this->duration * $this->framerate);
	}
	public function explode_range($limit){
		$limit = $limit - 1;
		$array = range(0,$this->duration,$this->duration / $limit);
		$array[] = $this->duration;
		return $array;
	}
	public function image_str($sec){
		return Command::out(sprintf("%s -ss %01.3f -i %s -f image2 -",$this->cmd,$sec,$this->filename));
	}
	public function write_jpg($sec,$output_filename){
		if(!preg_match("/\.(jpg|jpeg)$/i",$output_filename)) $output_filename = $output_filename.".jpg";
		File::mkdir(dirname($output_filename));
		return new Command(sprintf("%s -ss %01.3f -i %s -f image2 %s",$this->cmd,$sec,$this->filename,$output_filename));
	}
	public function audio(){
		return new Command(sprintf("%s -i %s -acodec copy out.mp3",$this->cmd,$this->filename));
	}
}
