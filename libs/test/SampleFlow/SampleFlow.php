<?php
class SampleFlow extends Flow{
	public function index(){
		if($this->is_vars("view")){
			$theme = "default";
			switch($this->in_vars("view")){
				case "red":
				case "blue":
					$theme = $this->in_vars("view");
			}
			$this->theme($theme);
		}
	}
	public function hoge(){
	}
	public function upload_value(){
		$value1 = $this->in_vars('value1');
		
		$this->rm_vars();
		$this->vars('get_data1',$value1);
	}
	public function upload_file(){
		if($this->is_post()){
			$file1 = $this->in_files('upfile1');
			$this->rm_vars();
			
			$mv = getcwd().'/'.md5(microtime());
			$this->vars('original_name1',$file1->name());
			$this->vars('size1',filesize($file1->tmp()));
			$this->vars('has1',is_file($file1->tmp()));

			$file1->generate($mv);
			$this->vars('mv1',is_file($mv));
			$this->vars('mv_size1',filesize($mv));
			$this->vars('data1',file_get_contents($mv));
			unlink($mv);
		}
		
	}
	public function upload_multi(){
		if($this->is_post()){
			$value1 = $this->in_vars('value1');
			$value2 = $this->in_vars('value2');
			$file1 = $this->in_files('upfile1');
			$file2 = $this->in_files('upfile2');
			
			$this->rm_vars();
			$this->vars('get_data1',$value1);
			$this->vars('get_data2',$value2);
			
			$mv = getcwd().'/'.md5(microtime());
			$this->vars('original_name1',$file1->name());
			$this->vars('size1',filesize($file1->tmp()));
			$this->vars('has1',is_file($file1->tmp()));

			$file1->generate($mv);
			$this->vars('mv1',is_file($mv));
			$this->vars('mv_size1',filesize($mv));
			$this->vars('data1',file_get_contents($mv));
			unlink($mv);

			$mv = getcwd().'/'.md5(microtime());
			$this->vars('original_name2',$file2->name());
			$this->vars('size2',filesize($file2->tmp()));
			$this->vars('has2',is_file($file2->tmp()));

			$file2->generate($mv);
			$this->vars('mv2',is_file($mv));
			$this->vars('mv_size2',filesize($mv));
			$this->vars('data2',file_get_contents($mv));
			unlink($mv);
		}
	}
}
