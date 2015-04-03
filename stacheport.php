<?php

class Stacheport
{
	private $template='',$group_by=false,$group_collection='',$group_field='',$brackets=array('\{\{','\}\}','{{'),$hash='/';

	public $display=true,$data=array();

	public static function factory($template)
	{
		return new Stacheport($template);
	}

	public function set_brackets($char1,$char2)
	{
		$this->brackets=array('\\'.$char1.'\\'.$char1,'\\'.$char2.'\\'.$char2,$char1.$char1);

		return $this;
	}

	public function bind ($var,&$value)
	{
		$this->data[$var]=&$value;

		return $this;
	}

	function __construct ($template)
	{
		$this->template= $template;

		return $this;
	}

	public function set_template($template)
	{
		$this->template= $template;

		return $this;
	}	

	private function process_totals($process_item,$collection_name)
	{
		if (is_object($process_item) || is_array($process_item) )
			foreach ($process_item as $k=>$v)
			{

				if (  isset($this->data[$collection_name.'.totals'][$k])) //|| is_float($v)
				{
					if ( is_numeric($v) )
						$this->data[$collection_name.'.totals'][$k]+=$v;
				}
				else
					$this->data[$collection_name.'.totals'][$k]=$v;
			}
	}

	private function run_loops($s)
	{
		preg_match($this->hash.$this->brackets[0].'\#(.+?)'.$this->brackets[1].'(.+?)'.$this->brackets[0].'\/(.+?)'.$this->brackets[1].$this->hash.'is', $s, $m);

		if (empty($m))
		{
			return $s;
		}

		$collection_name = trim($m[1]);

		$loop_temp = trim($m[2]);

		$collection=$this->get_value($collection_name);

		if (is_array($collection) && isset($collection[0]))
		{
			$loop_value='';

			$this->data[$collection_name.'.totals']=array();

			$this->data[$collection_name.'.totals']['counter']=0;

			$cnt=1;

			foreach ($collection as $process_item)
			{
				$this->data['process_item'] = $process_item;

				$this->process_totals($process_item,$collection_name);

				$this->data[$collection_name.'.totals']['counter']++;

				if (is_array($process_item))
					$this->data['process_item']['counter']=$cnt++;
				else
					$this->data['process_item']->counter=$cnt++;

				$loop_value.= $this->process_template($loop_temp,null,'process_item');
			}
		}
		else
		{
			$this->process_totals($collection,$collection_name);

			$loop_value=$this->process_template($loop_temp,null,$collection_name);
		}

		$s = preg_replace($this->hash.$this->brackets[0].'\#(.+?)'.$this->brackets[1].'(.+?)'.$this->brackets[0].'\/(.+?)'.$this->brackets[1].$this->hash.'is', $loop_value, $s, 1);

		return $this->run_loops($s);
	}

	private function replace_dot_notation_array($s)
	{
		preg_match('#\.(.+?)\.#is', $s, $m);

		if (empty($m))
			preg_match('#\.(.+?)$#is', $s, $m);

		if (empty($m))
			return $s;

		$t = $m[1];

		$s = preg_replace('+\.' . preg_quote($t) . "+is", "['".(isset($t)?$t:'')."']", $s, 1);

		return $this->replace_dot_notation_array($s);
	}

	private function replace_dot_notation_obj($s)
	{
		preg_match('#\.(.+?)\.#is', $s, $m);

		if (empty($m))
			preg_match('#\.(.+?)$#is', $s, $m);

		if (empty($m))
			return $s;

		$t = $m[1];

		$s = preg_replace('+\.' . preg_quote($t) . "+is", "->".(isset($t)?$t:'')."", $s, 1);

		return $this->replace_dot_notation_obj($s);
	}

	private function process_template($template, $match = null,$collection=null)
	{
		preg_match($this->hash.$this->brackets[0].'(.+?)'.$this->brackets[1].$this->hash.'is', $template, $match);

		if (empty($match))
			return $template;

		$stache_match = $match[1];

		if (strpos($stache_match, $this->brackets[2]) !== false)
		{
			$stache_match = substr($stache_match, strrpos($stache_match, $this->brackets[2]) + 2);
		}

		$t1=$stache_match;

		$stache_match= trim($stache_match);

		$value = $this->get_value($stache_match,$collection);

		$template = preg_replace('+'.$this->brackets[0].'' . preg_quote($t1) . ''.$this->brackets[1].'+is', isset($value)?$value:'', $template, 1);

		return $this->process_template($template,null,$collection);
	}

	private function get_value($stache_match,$collection=null)
	{
		if (stripos($stache_match,')') !== false && stripos($stache_match,'(') !== false)
		{
			preg_match('/(.*?)(\(.*?)\)/',$stache_match,$matches);

			if (isset($matches[1]))
			{
				$function=$matches[1];

				$code=str_ireplace($function,'',$stache_match);

				$value='';

				if(trim($function)=='echo' || trim($function)=='print' || trim($function)=='=')
				{
					$value=@eval('return '.$code.';');
				}
				elseif (function_exists($function) )
				{
					$value=@eval('return '.$stache_match.';');
				}
				else
				{
					$fn_obj=substr($function,0,stripos($function,'.'));

					$fn_name=substr($function,stripos($function,'.')+1);

					if($this->data[$fn_obj]->$fn_name instanceof Closure)
					{
						$code=trim($code);

						$code=substr($code,1);

						$code=substr($code,0,strlen($code)-1);

						$func =$this->data[$fn_obj]->$fn_name;

						$data=explode(',',$code);

						$value=call_user_func_array($func,$data);
					}
				}

				return $value;
			}
		}

		if (isset($collection))
		{
			if (isset($this->data[$collection]))
				if (!is_array($this->data[$collection]) )
					$value= $this->data[$collection]->$stache_match;
				else
					$value= isset($this->data[$collection][$stache_match])?$this->data[$collection][$stache_match]:null;
		}
		else if(isset( $this->data[$stache_match]))
		{
			$value = $this->data[$stache_match]; //regular variable
		}
		else
		{
			if (stripos($stache_match,'.') !== false) //dot notation for objects or array
			{
				$arr=substr($stache_match,0,stripos($stache_match,'.'));

				$item=substr($stache_match,stripos($stache_match,'.')+1);

				if (isset($this->data[$arr]->$item))
					//see if obj is accessible directly
				return $this->data[$arr]->$item;

				if (isset($this->data[$arr][$item]))
					//see if array is accessible directly
				return $this->data[$arr][$item];


				if ( @is_array( $this->data[$arr] ) )
					//replace dot notation with array notation
				$stache_match = $this->replace_dot_notation_array($stache_match);
				else
					//replace dot notation with obj notation
				$stache_match = $this->replace_dot_notation_obj($stache_match);

				@extract($this->data);

				$value=@eval('return $'.$stache_match.';');
			}
			else
			{
				$value=@eval('return $'.$stache_match.';'); //stdclass or array
			}
		}

		$value=isset($value)?$value:'';

		return $value;
	}

	public function group_by($collection,$field)
	{
		$this->group_by=true;
		$this->group_collection=$collection;
		$this->group_field=$field;

		return $this;
	}

	public function render()
	{
		if ($this->group_by)
		{
			$data = $this->process_group_by();
		}
		else
		{
			$data = $this->run_loops($this->template);
		}

		$data = $this->process_template($data);

		return $data;
	}

	private function process_group_by()
	{
		$group_by=array();

		$data='';

		foreach ($this->data[$this->group_collection] as $record)
		{
			$group_by[$record->{$this->group_field}][] = $record;
		}

		foreach ($group_by as $run_group)
		{
			$this->bind($this->group_collection, $run_group);

			$data .= $this->run_loops($this->template);

			$data = $this->process_template($data);
		}

		return $data;
	}
}
