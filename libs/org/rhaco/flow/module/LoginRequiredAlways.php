<?php
/**
 * do_login以外の場合にログインしていなければ例外をだす
 * @author tokushima
 *
 */
class LoginRequiredAlways{
	public function before_login_required(Flow $flow){
		if(!$flow->is_login()){
			Http::status_header(401);
			if(!Exceptions::has()) Exceptions::add(new LogicException(Gettext::trans('Unauthorized')),'do_login');
			Exceptions::throw_over();
		}
	}
}