<?php

abstract class dbm_adaptor 
{
	function init()
	{
		global $__dbm;

		$__dbm['adaptor'] = 'dbm_adaptor_'.$__dbm['type'];
		$__dbm['connection'] = new PDO(
			$__dbm['type'].':host='.$__dbm['host'].';dbname='.$__dbm['database'], 
			$__dbm['username'],
			$__dbm['password']
		);		
	}
	
	public static function get_tables()
	{
		global $__dbm;
		$sql = 'SELECT table_name as name FROM INFORMATION_SCHEMA.TABLES';
		$sql .= ' where table_schema = '.$__dbm['adaptor']::handle_format($__dbm['database']).'';
		
		return dbm::query($sql);
	}
	
	public static function get_columns($table)
	{
		global $__dbm;
		$sql = '
			SELECT COLUMN_NAME as name, 
				DATA_TYPE as data_type, 
				IS_NULLABLE as is_nullable, 
				COLUMN_DEFAULT as column_default,
				CHARACTER_MAXIMUM_LENGTH as max_length,
				NUMERIC_SCALE as numeric_scale
				FROM INFORMATION_SCHEMA.COLUMNS
				where table_schema='.$__dbm['adaptor']::handle_format($__dbm['database']).'
				and table_name = '.$__dbm['adaptor']::handle_format($table).'
				order by ORDINAL_POSITION
				;
			';
		#echo($sql);
		return dbm::query($sql);
	}
}

?>