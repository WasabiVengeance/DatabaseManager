<?php
# Copyright 2013 Mike Thorn (github: WasabiVengeance). All rights reserved.
# Use of this source code is governed by a BSD-style
# license that can be found in the LICENSE file.

class dbm_model_sql_clauses extends dbm_model_sql_builder
{
	public function filter($field,$operator='',$value=_DBM_SQL_FAKENULL_)
	{
		if($value == _DBM_SQL_FAKENULL_)
		{
			$value = $operator;
			$operator = '=';
		}
		$this->__sql_filters[] = new dbm_filter($field,$operator,$value);
		return $this;
	}
	
	public function group()
	{
		return $this;
	}
	
	public function sort($col,$direction='asc')
	{
		$new_sort = $col;
		$new_sort .= ($direction=='asc')?'':' desc';
		$this->__sql_sorts[] = $new_sort;
		return $this;
	}
	
	public function limit($page_size)
	{
		$this->__sql_limit = $page_size;
		return $this;
	}
	
	public function page($start_page,$page_size=null)
	{
		$this->__sql_determine_max_page = true;
		if(!is_null($page_size))
			$this->__sql_limit  = $page_size;
		$this->__sql_offset = $start_page * $this->__sql_limit;
		return $this;
	}
	
	public function join($table,$type='inner',$conditions=null,$fields=null)
	{		
		# if a table model was passed, use that. If not, load the model
		if(is_string($table))
			$table = dbm::model($table);
				
		# if join conditions are not passed, try to find matching fields
		if(is_null($conditions))
		{
			
			
			# look for a perfect match
			$found = false;
			foreach($this->__field_index as $name=>$idx)
			{
				if(array_key_exists($name,$table->__field_index))
				{
					$conditions = '('.$this->__table.'.'.$name.'='.$table->__table.'.'.$name.')';
					$found = true;
					break;
				}
			}
			
			if(!$found)
			{
				foreach($this->__field_index as $name1=>$idx2)
				{
					foreach($table->__field_index as $name2=>$idx2)
					{
						if(strpos($name1,$name2) !== false || strpos($name2,$name1) !== false)
						{
							$conditions = '('.$this->__table.'.'.$name1.'='.$table->__table.'.'.$name2.')';
							$found = true;
							break;
						}
					}
				}
			}
			if(!$found)
			{
				throw new Exception('DBM: could not find fields to join tables on: '.$this->__table.' / '.$table->__table);
			}
		}
				
		$this->__sql_joins[] = new dbm_model_sql_join($table,$type,$conditions,$fields);
		return $this;
	}
	
	public function load($id=null)
	{
		if(is_numeric($id))
			$this->filter($this->__fields[0]->name,'=',$id);
		list($paged_sql,$max_page_sql) = $this->__build_select_query();
		
		# if we need to determine the max page, then do so	
		dbm::log('determining max page');
		if(!is_null($this->__sql_limit) && !is_null($this->__sql_offset))
		{
			$records = dbm::query($max_page_sql);
			$record = $records->fetch();
			
			$this->__sql_max_page = ceil($record['max_page'] / $this->__sql_limit);
		}
		$this->__records = dbm::query($paged_sql);
		$this->__records = $this->__records->fetchAll(PDO::FETCH_ASSOC);
		$this->__sql_row_count =count($this->__records);
		$this->__index = -1;
		#$this->rewind();
		
		$this->__sql_filters = array();
		
		if(is_numeric($id))
			$this->next();
			
		return $this;
	}
	
	function select($query)
	{
		$this->__records = dbm::query('select '.$query);
		$this->__sql_row_count = $this->__records->rowCount();
		$this->__index = -1;
		return $this;
	}
	
	public function save()
	{
		#echo('save being called: '.print_r($this->__data,true).'<br />');
		#echo('condition1: isset '.isset($this->__data[$this->__fields[0]->name]).'<br />');
		#echo('condition2: !isnull '.(!is_null($this->__data[$this->__fields[0]->name])).'<br/>');
		if(
			isset($this->__data[$this->__fields[0]->name])
			&&
			!is_null($this->__data[$this->__fields[0]->name])
		)
		{
			#echo('building update<br />');
			$sql = $this->__build_update_query();
			if($sql !== false)
			{
				dbm::query($sql);
			}
		}
		else
		{
			#echo('building insert<br />');
			$sql = $this->__build_insert_query();
			if($sql !== false)
			{
				$results = dbm::query($sql);
				$results->nextRowset();
				$results = $results->fetch();
				#print_r($results);
				$this->__data[$this->__fields[0]->name] = $results['new_id'];
			}
		}
			
		
					
		return $this;
	}

	public function delete($id=null)
	{
		if(!is_null($id))
			$this->filter($this->__fields[0]->name,'=',$id);
		
		dbm::query($this->__build_delete_query());
		return $this;
	}
}

?>