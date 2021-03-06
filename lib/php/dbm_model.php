<?php
# Copyright 2013 Mike Thorn (github: WasabiVengeance). All rights reserved.
# Use of this source code is governed by a BSD-style
# license that can be found in the LICENSE file.

define('_DBM_SQL_FAKENULL_',-9999999999);

class dbm_model extends dbm_model_sql_clauses implements ArrayAccess
{
	public function __construct($table_name = '')
	{
		# if the table name is passed, then use that. otherwise,
		# derive it from the class name of the object.
		if($table_name == '')
			$this->__table = str_replace('dbm_model__','',get_class($this));
		else
			$this->__table = $table_name;
		
		# these contain the data/metadata for the model
		$this->__data = array();
		$this->__records = null;
		$this->__original_data = array();
		$this->__fields = array();
		$this->__field_index = array();
		
		# these properties contain settings for querying
		$this->__index       = -1;
		$this->__sql_joins   = array();
		$this->__sql_filters = array();
		$this->__sql_sorts   = array();
		$this->__sql_groups  = array();
		$this->__sql_limit   = null;
		$this->__sql_offset  = null;
		
		$this->__autoformat = array();

		#
		$this->__sql_max_page = 0;
		$this->__sql_determine_max_page = false;
		
		$this->__init_fields();
		$this->__build_index();
	}
	
	# this function is overridden by a submodel
	function __init_fields()
	{
	}
	
	function __build_index()
	{
		for($i=0;$i<count($this->__fields);$i++)
		{
			$this->__field_index[$this->__fields[$i]->name] = $i;
		}
	}
	
	public function to_array($idx=null,$value=null)
	{
		$this->load();
		if(!is_null($idx))
		{
			$to_return = array();
			if(is_null($value))
			{
				
				foreach($this->__records as $record)
				{
					$to_return[$record[$idx]] = $record;
				}
			}
			else
			{
				foreach($this->__records as $record)
				{
					$to_return[$record[$idx]] = $record[$value];
				}
			}
			return $to_return;
		}
		return $this->__records;
	}
	
	public function new_query()
	{
		$this->__reset();
		$this->__index = -1;
		$this->__records = null;
		$this->__sql_sorts   = array();
		$this->__sql_groups  = array();
		$this->__sql_limit   = null;
		$this->__sql_offset  = null;
		$this->__sql_row_count = null;
		$this->__sql_filters = array();
		$this->__sql_row_count = null;
		$this->__sql_determine_max_page = false;
		return $this;
	}

	function __reset()
	{
		$this->__data = array();
		$this->__original_data = array();
		return $this;
	}
	
	public function import($source = false)
	{
		global $__dbm;
		if($source === false)
			$source = $_REQUEST;
		if(is_null($this->__records))
			$this->__records = array();
		foreach($this->__fields as $field)
		{
			if(isset($source[$field->name]))
			{
				$val = $source[$field->name];
				
				$this->__data[$field->name] = $val;
			}
		}
		return $this;
	}
	
	public function __import($data,$is_original=false)
	{
		global $__dbm;
		
		foreach($this->__autoformat as $field=>$format)
		{
			if(is_callable($format))
			{
				$data = $format($data);
			}
			else
			{
				switch($format)
				{
					case 'date-short':
						$data[$field] = date($__dbm['formats']['date-short'],$data[$field]);
						break;
					case 'date-long':
						$data[$field] = date($__dbm['formats']['date-long'],$data[$field]);
						break;
				}
			}
		}
		
		
		foreach($data as $field=>$value)
		{			
			if($is_original)
				$this->__original_data[$field] = $value;
			$this->__data[$field] = $value;
		}

		
		$this->__data['__max_page'] = $this->__sql_max_page;
	}
	
	public function offsetExists ($offset )
	{
		return isset($this->__data[$offset]);
	}
	
	public function offsetGet ( $offset )
	{
		if(is_null($this->__records))
			$this->__load();
			
		if(!array_key_exists($offset,$this->__data))
			return null;
			
		return $this->__data[$offset];
	}
	
	public function offsetSet ( $offset , $value )
	{
		if(is_null($this->__records))
			$this->load();
		$this->__data[$offset] = $value;
	}
	
	public function offsetUnset ( $offset )
	{
		unset($this->__data[$offset]);
	}
	
	public function get_field($field_name)
	{
		if(is_null($this->__records))
			$this->__load();
		return $this->__fields[$this->__field_index[$field_name]]->get_value();
	}
	
	public function set_field($field_name,$value)
	{
		$this->__data[$field_name] = $value;
		return $this;
	}
	
	public function row_array()
	{
		return $this->__data;
	}
	
	public function dump($html = true)
	{
		
		if(!$html)
		{
			$out = '';
			if($this->__index == -1)
			{
				foreach($this as $row)
				{
					$out .= print_r($row->__data,true)."\n";
				}
			}
			else
			{
				$out .= print_r($this->__data,true)."\n";
			}
			return $out;
		}
		
		$out = '';
		$out .= ('<table>');
		
		# if we're dumping the whole recordset,
		if($this->__index == -1)
		{
			#echo('dumping entire table');
			$first = true;
			foreach($this as $row)
			{
				if($first == true)
				{
					$out .= ('<tr>');
					foreach($row->__data as $label=>$value)
					{
						$out .= ('<th>'.$label.'</th>');
					}
					$out .= ('</tr>');
					$first = false;
				}
				$out .= ('<tr>');
				foreach($row->__data as $label=>$value)
				{
					$out .= ('<td>'.$value.'</td>');
				}
				$out .= ('</tr>');
			}	
		}
		else
		{
			#echo('dumping single row');
			$out .= ('<tr>');
			foreach($this->__data as $label=>$value)
			{
				$out .= ('<th>'.$label.'</th>');
			}
			$out .= ('</tr>');
			$out .= ('<tr>');
			foreach($this->__data as $label=>$value)
			{
				$out .= ('<td>'.$value.'</td>');
			}
			$out .= ('</tr>');
		}
		
		$out .= ('</table>');
		return $out;
	}
	
	function format($field,$format=null)
	{
		if(is_null($format))
			$this->__autoformat[] = $field;
		else
			$this->__autoformat[$field] = $format;
			
		return $this;
	}
	
	function is_changed($field)
	{
		return ($this->__data[$field] != $this->__original_data[$field]);
	}
}

?>