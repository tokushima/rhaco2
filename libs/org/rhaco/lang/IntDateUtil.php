<?php
import("org.rhaco.lang.DateUtil");
/**
 * intdate型を扱うライブラリ
 *
 * @author Kentaro YABE
 * @license New BSD License
 */
class IntDateUtil{
	static protected $mday = array(31,28,31,30,31,30,31,31,30,31,30,31);
	static protected $weekday = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");
	static protected $month = array("January","February","March","April","May","June","July","August","September","October","November","December");
	
	/**
	 * intdate型の日付を返す
	 *
	 * @param integer $year
	 * @param integer $month
	 * @param integer $day
	 * @return intdate
	 */
	static public function mkdate($year=null,$month=null,$day=null){
		/***
		 * eq(20000101,IntDateUtil::mkdate(2000,1,1));
		 * eq(19991231,IntDateUtil::mkdate(2000,1,0));
		 * eq(20000201,IntDateUtil::mkdate(2000,1,32));
		 * eq(20040229,IntDateUtil::mkdate(2004,2,29));
		 * eq(19991201,IntDateUtil::mkdate(2000,0,1));
		 * eq(20010101,IntDateUtil::mkdate(2000,13,1));
		 * eq(19981231,IntDateUtil::mkdate(2000,1,-365));
		 * eq(20001231,IntDateUtil::mkdate(2000,1,366));
		 * eq(20010303,IntDateUtil::mkdate(2000,14,31));
		 * eq(20000228,IntDateUtil::mkdate(2000,3,-1));
		 * eq(intval(date("Ymd")),IntDateUtil::mkdate());
		 */
		$year = is_null($year) ? date("Y") : $year;
		$month = is_null($month) ? date("m") : $month;
		$day = is_null($day) ? date("d") : $day;
		if($month>12){
			$add = intval(floor($month/12));
			$month = intval(($month+12)%12);
			return self::mkdate($year+$add,$month,$day);
		}
		if($month<1){
			$add = intval(floor($month/12));
			$add += ($add%12 == 0) ? -1 : 0;
			return self::mkdate($year+$add,intval($month%12)+12,$day);
		}
		$uru = (($year%4==0) && (($year%400==0) || ($year%100!=0)));
		if($day<1){
			$year = ($month==1) ? $year-1 : $year;
			$month = ($month==1) ? 12 : $month - 1;
			$mday = self::$mday[$month-1];
			if($month==2 && $uru){
				$mday++;
			}
			return self::mkdate($year,$month,$day+$mday);
		}
		$mday = self::$mday[$month-1];
		if($month==2 && $uru){
			$mday++;
		}
		if($day>$mday){
			return self::mkdate($year,$month+1,$day-$mday);
		}
		return intval($year*10000+$month*100+$day);
	}
	
	/**
	 * intdate型から日付情報の配列を返す
	 *
	 * @param intdate $intdate
	 * @return array
	 */
	static public function getdate($intdate){
		/***
		 * $date = IntDateUtil::getdate(20090526);
		 * eq(145,$date["yday"]);
		 * $date = IntDateUtil::getdate(20000301);
		 * eq(60,$date["yday"]);
		 * $date = IntDateUtil::getdate(20000201);
		 * eq(31,$date["yday"]);
		 */
		$year = intval(floor($intdate/10000));
		$month = intval(floor(($intdate%10000)/100));
		$day = intval($intdate%100);
		if($month>12 || $month<1 || $day < 1){
			return self::getdate(self::mkdate($year,$month,$day));
		}
		$mday = self::$mday[$month-1];
		$uru = (($year%4==0) && (($year%400==0) || ($year%100!=0)));
		if($month==2 && $uru){
			$mday++;
		}
		if($day>$mday){
			return self::getdate(self::mkdate($year,$month,$day));
		}
		$yday = -1;
		for($i=0;$i<$month-1;$i++){
			$yday+=self::$mday[$i];
			if($i==1 && $uru){
				$yday++;
			}
		}
		$yday+=$day;
		$wday = DateUtil::weekday($intdate);
		return array(
			"mday"=>$day,
			"wday"=>$wday,
			"mon"=>$month,
			"year"=>$year,
			"yday"=>$yday,
			"weekday"=>self::$weekday[$wday],
			"month"=>self::$month[$month-1],
			"leap"=>$uru,
			0=>intval($intdate)
		);
	}
	
	/**
	 * 指定した日時を加算したintdateを取得
	 *
	 * @param intdate $intdate
	 * @param integer $day
	 * @param integer $month
	 * @param integer $year
	 * @return intdate
	 */
	static public function add($intdate,$day=0,$month=0,$year=0){
		$date = self::getdate($intdate);
		return self::mkdate($date["year"]+$year,$date["mon"]+$month,$date["mday"]+$day);
	}
	
	/**
	 * 日を加算する
	 *
	 *
	 * @param intdate $intdate
	 * @param integer $day
	 * @return intdate
	 */
	static public function add_day($intdate,$day){
		return self::add($intdate,$day);
	}
	
	/**
	 * 日付文字列からintdateを取得する
	 *
	 * @param string $str
	 * @return intdate
	 */
	static public function parse($str){
		return DateUtil	::parse_int($str);
	}
	
	/**
	 * 日付書式にフォーマットする
	 *
	 * @param intdate $intdate
	 * @param string $format
	 */
	static public function format($intdate,$format="Y/m/d"){
		/***
		 * eq("2009/05/27",IntDateUtil::format(20090527));
		 * eq("Feb,February,Thu,Thursday,2009,09,02,2,05,5,4,35,5",IntDateUtil::format(20090205,"M,F,D,l,Y,y,m,n,d,j,w,z,N"));
		 */
		$format = str_replace(array("YYYY","MM","DD"),array("Y","m","d"),$format);
		$intdate = self::parse($intdate);
		if(empty($intdate)) return "";
		$date = self::getdate($intdate);
		$search = array("Y","y","m","n","d","j","w","z","N","M","F","D","l","___M___","___F___","___D___","___l___");
		$replace = array(
						$date["year"],						//Y
						sprintf("%02d",$date["year"]%100),	//y
						sprintf("%02d",$date["mon"]),		//m
						$date["mon"],						//n
						sprintf("%02d",$date["mday"]),		//d
						$date["mday"],						//j
						$date["wday"],						//w
						$date["yday"],						//z
						$date["wday"]+1,					//N
						"___M___",							//
						"___F___",							//
						"___D___",							//
						"___l___",							//
						substr($date["month"],0,3),			//M
						$date["month"],						//F
						substr($date["weekday"],0,3),		//D
						$date["weekday"]					//l
					);
		return str_replace($search,$replace,$format);
	}
	
	/**
	 * 晦日を取得
	 * 
	 * @param intdate $intdate
	 * @return intdate
	 */
	static public function last_date($intdate){
		/***
		 * eq(20120229,IntDateUtil::last_date(20120215));
		 * eq(20091231,IntDateUtil::last_date(20091231));
		 */
		$date = self::getdate($intdate);
		return self::mkdate($date["year"],$date["mon"]+1,0);
	}
	
	/**
	 * 朔日を取得
	 *
	 * @param intdate $intdate
	 * @return intdate
	 */
	static public function first_date($intdate){
		/***
		 * eq(20120201,IntDateUtil::first_date(20120215));
		 * eq(20090701,IntDateUtil::first_date(20090731));
		 */
		$date = self::getdate($intdate);
		return self::mkdate($date["year"],$date["mon"],1);
	}
	
	/**
	 * 日付の差を取得する
	 *
	 * @param intdate $date1
	 * @param intdate $date2
	 * @return integer
	 */
	static public function diff($date1,$date2){
		/***
		 * eq(0,IntDateUtil::diff(20090101,20090101));
		 * eq(30,IntDateUtil::diff(20090401,20090501));
		 * eq(365,IntDateUtil::diff(20090301,20100301));
		 * eq(-366,IntDateUtil::diff(20090201,20080201));
		 * eq(730,IntDateUtil::diff(20090301,20110301));
		 * eq(1096,IntDateUtil::diff(20090301,20120301));
		 */
		if($date1 > $date2){
			return -1 * intval(self::diff($date2,$date1));
		}
		$info1 = self::getdate($date1);
		$info2 = self::getdate($date2);
		if($info1["year"] == $info2["year"]){
			return $info2["yday"] - $info1["yday"];
		}
		$diff = (365 + ($info1["leap"] ? 1 : 0)) - ($info1["yday"] + 1) + ($info2["yday"] + 1);
		for($year = $info1["year"]; $year < $info2["year"] - 1; $year++){
			$uru = (($year%4==0) && (($year%400==0) || ($year%100!=0)));
			$diff += 365 + ($uru ? 1 : 0);
		}
		return $diff;
	}
}
