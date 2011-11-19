<?php
/**
 * Paginatorのhtml表現
 * twotter bootstrap のPagination風
 * @author tokushima
 *
 */
class TwitterBootstrapPagination{
	public function before_template(&$src){
		if(strpos($src,'rt:paginator') !== false){
			while(Tag::setof($tag,$src,'rt:paginator')){
				$param = '$'.$tag->in_param('param','paginator');
				$func = sprintf('<?php try{ ?><?php if(%s instanceof Paginator){ ?>',$param);
				$func .= '<div class="pagination"><ul>';
				$uniq = uniqid('');
				$name = '$__pager__'.$uniq;
				$counter_var = '$__counter__'.$uniq;
				$href = $tag->in_param('href','?');
				$stag = '<li%s>';
				$etag = '</li>';
				$navi = array_change_key_case(array_flip(explode(',',$tag->in_param('navi','prev,next,first,last,counter'))));
				$counter = $tag->in_param('counter',50);
				$total = '$__pagertotal__'.$uniq;
				if(isset($navi['prev'])) $func .= sprintf('<?php if(%s->is_prev()){ ?><li class="prev"><?php }else{ ?><li class="prev disabled"><?php } ?><a href="%s{%s.query_prev()}">%s</a></li>',$param,$href,$param,'&larr; Previous');
				if(isset($navi['first'])) $func .= sprintf('<?php if(!%s->is_dynamic() && %s->is_first(%d)){ ?><li><a href="%s{%s.query(%s.first())}">{%s.first()}</a></li><li class="disabled"><a href="#">...</a></li><?php } ?>',$param,$param,$counter,$href,$param,$param,$param);
				if(isset($navi['counter'])){
					$func .= sprintf('<?php if(!%s->is_dynamic()){ ?>',$param);
					$func .= sprintf('<?php %s = %s; if(!empty(%s)){ ?>',$total,$param,$total);
					$func .= sprintf('<?php for(%s=%s->which_first(%d);%s<=%s->which_last(%d);%s++){ ?>',$counter_var,$param,$counter,$counter_var,$param,$counter,$counter_var);
						$func .= sprintf('<?php if(%s == %s->current()){ ?>',$counter_var,$param);
							$func .= sprintf('<li class="active"><a href="#">{%s}</a></li>',$counter_var);
						$func .= '<?php }else{ ?>';
							$func .= sprintf('<li><a href="%s{%s.query(%s)}">{%s}</a></li>',$href,$param,$counter_var,$counter_var);
						$func .= '<?php } ?>';
					$func .= '<?php } ?>';
					$func .= '<?php } ?>';
					$func .= '<?php } ?>';
				}
				if(isset($navi['last'])) $func .= sprintf('<?php if(!%s->is_dynamic() && %s->is_last(%d)){ ?><li class="disabled"><a href="#">...</a></li><li><a href="%s{%s.query(%s.last())}">{%s.last()}</a></li><?php } ?>',$param,$param,$counter,$href,$param,$param,$param);
				if(isset($navi['next'])) $func .= sprintf('<?php if(%s->is_next()){ ?><li class="next"><?php }else{ ?><li class="next disabled"><?php } ?><a href="%s{%s.query_next()}">%s</a></li>',$param,$href,$param,'Next &rarr;',$etag);

				$func .= "<?php } ?><?php }catch(\\Exception \$e){} ?>";
				$func .= '</ul></div>';
				$src = str_replace($tag->plain(),$func,$src);
			}
		}
	}
}