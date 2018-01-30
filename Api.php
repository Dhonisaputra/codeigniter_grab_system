<?php  

/**
* 
*/
class Api extends CI_Model
{
	private $allowed_params 	= ['fields', 'limit'];
	private $limit 				= 0;
	private $offset 			= 0;
	private $requested_parts 	= '';
	public $masked_fields 		= [];
	private $error 				= [];
	private $post 				= [];
	private $data_dependecy 	= [];
	private $data_dependecy_added 	= [];
	private $data_original_result 	= [];
	private $original_masked_fields = [];

	private $field_parametered 		= []; // array yang nantinya akan diisi field yang mempunyai parameter --> a,b(2),c(100)
	private $field_contain_fields 	= []; // array yang nantinya berisikan object fields yang ditulis didalam fields --> a,b.b1.b2(params1,params2),c,...
	private $data_requirement_fields = []; // array yang nantinya berisikan object fields yang ditulis didalam fields --> a,b.b1.b2(params1,params2),c,...

	private $db_table 				= [];
	private $data_advanced_processing_field = [];
	public 	$database;
	private $permitted;
	private $method 				= array();
	private $permitted_allowed 		= ['GET', 'POST'];
	private $bind_permitted_allowed = ['GET'];

	private $request_parts 			= [];
	private $last_query 			= [];
	private $using_pagination 		= true;


	function __construct()
	{
		# code...
		parent::__construct();
		// load configuration server
		$this->load->helper('tools');
		// set server_config sebagai class global parameter.
		// get request URI
		$request_uri 			= $_SERVER['REQUEST_URI'];
		$request_uri_arr 		= explode('/', $request_uri);
		if($request_uri_arr[0] == ''){array_shift($request_uri_arr);}
		
		array_shift($request_uri_arr);
		
		$request_uri_arr 			= implode('/', $request_uri_arr);
		$this->database 			= $this->db;
	}

	public function reset()
	{

		$this->reset_masking_fields();
		$this->reset_requirement_fields();
		$this->db->flush_cache();
		$this->limit 					= 0;
		$this->offset 					= 0;
		$this->requested_parts 			= '';
		$this->masked_fields 			= [];
		$this->original_masked_fields 			= [];
		$this->error 					= [];
		$this->post 					= [];
		$this->data_dependecy 			= [];
		$this->data_dependecy_added 	= [];
		$this->data_original_result 	= [];

		$this->field_parametered 		= []; // array yang nantinya akan diisi field yang mempunyai parameter --> a,b(2),c(100)
		$this->field_contain_fields 	= []; // array yang nantinya berisikan object fields yang ditulis didalam fields --> a,b.b1.b2(params1,params2),c,...
		$this->data_requirement_fields 	= []; // array yang nantinya berisikan object fields yang ditulis didalam fields --> a,b.b1.b2(params1,params2),c,...

		$this->db_table 				= [];
		$this->data_advanced_processing_field = [];
		$this->database;
		$this->permitted;
		$this->method 					= array();
		$this->request_parts 			= [];
		$this->using_pagination 		= true;
	}

	public function accepted_request_type($permitted, $request=FALSE)
	{
		if(!in_array($permitted, $this->permitted_allowed))
		{
			$this->set_error('404.1.1');
			return false;
		}

		$perm = '_'.$permitted;
		if(!$request &&!isset($$perm) && !in_array($permitted, $this->bind_permitted_allowed))
		{
			$this->set_error('404.1.2');
			return false;
		}
	}
	/*
	|------------------------------
	| Function untuk set request yang dapat diterima (GET|POST) atau passing request secara manual
	|------------------------------
	*/
	public function permitted_request_type($permitted = 'GET', $request = FALSE)
	{		
		
		$this->accepted_request_type($permitted, $request);
		$this->permitted = $permitted;
		switch ($permitted) {
			default:
			case 'GET':
				$this->method = !$request? $_GET: $request;
				break;
			case 'POST':
				$this->method = !$request? $_POST : $request;
				break;
		}

	}


	
	/*
	|---------------------------------
	| Function untuk push method manually
	|---------------------------------
	*/
	public function add_method($method, $fn)
	{
		if(!isset($this->method[$method]))
		{
			$this->method[$method] = array();
		}
		$this->method[$method] = $fn($method, $this->method[$method]);
	
	}

	/*
	|---------------------------------
	| Function untuk get method manually
	|---------------------------------
	*/
	public function get_method()
	{
		return $this->method;
	}

	/*
	|---------------------------------
	| Filtering table column
	|---------------------------------
	*/
	public function get_table_data($tablename, $data = array())
	{
		$return = [];
		foreach ($data as $key => $value) {
			$expl = explode('.', $key);
			if($expl[0] == $tablename)
			{
				$return[$key] = $value;
			}
		}
		return $return;

	}

	/*
	|---------------------------------
	| Function untuk recognize active query Codeigniter (WHERE|IN|NOT_IN|etc)
	|---------------------------------
	*/
	public function active_query_recognizer($post)
	{
		// check if data in [1,2,3,4,...,n]
		// $post['in'] = array(array('id_article', array(32,31))); <-- how to use

		if(isset($post['in'])){ 
			foreach ($post['in'] as $key => $value) {
				$value[0] = $this->get_actual_value($value[0]);
				call_user_func_array(array($this->db, 'where_in'), $value); 
			}
		}
		// check if data not in [1,2,3,4,...,n]
		// $post['not_in'] = array(array('id_article', array(32,31))); <-- how to use
		if(isset($post['not_in'])){
			foreach ($post['not_in'] as $key => $value) {
				$value[0] = $this->get_actual_value($value[0]);
				call_user_func_array(array($this->db, 'where_not_in'), $value);
			}
		}
		// where 
		if(isset($post['_where_'])){ $this->db->where($post['_where_']); }
		// like
		if(isset($post['like'])){ 
			foreach ($post['like'] as $key => $value) {
				$value[0] = $this->get_actual_value($value[0]);
				call_user_func_array(array($this->db, 'like'), $value);
				// $this->db->like('');
			}
		}
		// not like
		if(isset($post['not_like'])){ $this->db->not_like($post['not_like']); }
		// group by
		if(isset($post['group_by'])){ $this->db->group_by($post['group_by']); }
		// order by
		if(isset($post['order_by'])){ $this->db->order_by($post['order_by']); }
		// having
		if(isset($post['where'])){ $this->db->having($post['where']); }
		if(isset($post['having'])){ $this->db->having($post['having']); }
		if(isset($post['or_where'])){ $this->db->or_having($post['or_where']); }
		if(isset($post['or_like'])){ $this->db->or_like($post['or_like']); }
		// limit & Offset
		$default_limit = 15;
		$default_offset = !isset($post['page']) || $post['page'] <= 1? 0 : (@$post['page']*$default_limit)-$default_limit;

		$post['limit'] = isset($post['limit'])? $post['limit'] : $default_limit.','.$default_offset;
		$limit = $post['limit'];
		call_user_func_array(array($this->db, 'limit'), explode(',', $limit));
	}


	

	/*
	|----------------------------
	| Function untuk pengecheckan apakan fields mengandung fields turunan.
	|----------------------------
	| --> fields => 'a,b,c,d.d1.d2.d3' (d1,d2,d3 adalah fields turunan)
	*/
	function recognize_field_contain_fields($data)
	{
		foreach ($data as $key => $value) {
			$fields 		= advance_explode($value,'.');
			$actual_field 	= array_shift($fields);
			$fields 		= implode(',', $fields);
			$data[$key] 	= $actual_field;
			$this->add_field_contain_fields($actual_field, $fields);
			// echo $fields;
		}
		return $data;
		
	}

	/*
	|--------------------------------------------
	| Tambahkan fields turunan kedalam records
	|--------------------------------------------
		@params 
		- $key s index name
		- $value :any nilai yang akan dimasukkan
	*/
	function add_field_contain_fields($key, $value)
	{
		if($key && $value)
		{
			$this->field_contain_fields[$key] = $value;
		}
	}
	/*
	|--------------------------------------------
	| ambil fields turunan kedalam records
	|--------------------------------------------
		@params 
		- $key --> index name
	*/
	function get_field_contain_fields($key = FALSE)
	{
		if($key) return isset($this->field_contain_fields[$key])?$this->field_contain_fields[$key]:array();
		return $this->field_contain_fields;
	}


	/*
	|--------------------------------------------
	| Function untuk pengechekan apakah fields memiliki parameter
	|--------------------------------------------
	| --> fields => a,b,c(10)
		@params 
		- $key o object yang akan dilakukan pengechekan
	*/
	function recognize_field_parametered($data = array(), $addToWhere=TRUE)
	{
		foreach ($data as $key => $value) {
			$params = get_bracket_content($value);
			if(count($params) > 1)
			{
				$fields = str_replace($params[0], '', $value);
				$this->set_field_parametered($fields, $params[1]);
				if($addToWhere==TRUE)
				{
					$this->method['where'][$fields] = $params[1];
				}
				// $this->add_requested_parts($fields);

				$data[$key] = $fields;
			}
		}



		return $data;
	}

	/*
	|--------------------------------------------
	| set fields turunan kedalam records
	|--------------------------------------------
		@params 
		- $key :s index name
		- $value :s|:i nilai yang akan dimasukkan 
	*/
	function set_field_parametered($key, $value='')
	{
		if($key)
		{
			$this->field_parametered[$key] = $value;
		}
	}
	/*
	|--------------------------------------------
	| ambil records fields yang memiliki parameter
	|--------------------------------------------
		@params 
		- $key s index name
	*/
	function get_field_parametered($key = FALSE)
	{
		if($key) return isset($this->field_parametered[$key])? $this->field_parametered[$key] : null;
		return $this->field_parametered;
	}

	/*
	|--------------------------------------------
	| set default requested parts
	|--------------------------------------------
		@params 
		- $data :o data yang akan dimasukkan 
	*/
	function set_requested_parts($data = array())
	{
		$this->requested_parts = $data;
	}

	/*
	|--------------------------------------------
	| tambahkan nilai kedalam records requested parts
	|--------------------------------------------
		@params 
		- $data :any data yang akan dimasukkan 
	*/
	function add_requested_parts($data = FALSE)
	{
		if(!$data) return false;
		array_push($this->requested_parts, $data);
	}

	/*
	|--------------------------------------------
	| ambil nilai kedalam records requested parts
	|--------------------------------------------
		@params 
		- $data :any data yang akan dimasukkan 
	*/
	function get_requested_parts()
	{
		return $this->requested_parts;
	}

	/*
	|----------------------
	| Set Dependecy
	|-----------------------
	*/
	public function dependency($data)
	{

		$this->data_dependecy = $data;
	}

	/*
	|----------------------
	| get Dependecy
	|-----------------------
	*/
	public function get_data_dependency()
	{
		return $this->data_dependecy;
	}
	/*
	|----------------------
	| Function to recognize dependency
	|-----------------------
	*/
	public function recognize_dependency()
	{
		$this->load->helper('tools');
		$dependecy = $this->get_data_dependency();
		foreach ($dependecy as $key => $value) {
			$value = remove_all_whitespace($value);
			$str = is_string($value)? explode(',', $value) : $value;
			foreach ($str as $k => $val) {
				$requested_parts = $this->get_requested_parts();
				if(!in_array($val, $requested_parts) && in_array($key, $requested_parts))
				{
					$this->add_requested_parts($val);
					$this->data_dependecy_added[] = $val;
				}
			}
			// check is available in masked_fields

		}
	}
	/*
	|----------------------
	| unset dependecy
	|-----------------------
	| Karena kita tidak ingin data dependency juga dikembalikan sebagai data yang ditampilkan ke user
	*/
	public function unset_dependency($res_db_result)
	{
		foreach ($this->data_dependecy_added as $key => $value) {
			foreach ($res_db_result->result_array() as $res_key => $res_value) {
				if(in_array($value, $this->requested_parts))
				{
					$res_db_result->remove_result($res_key, $value);
				}
				// print_r($res_key);
			}
		}
	}

	// used when there are no $_REQUEST || $_GET || $_POST
	public function default_fields($default = array())
	{
		$this->load->helper('tools');
		
		$this->method['fields'] = isset($this->method['fields']) && !empty($this->method['fields'])? $this->method['fields'] : remove_all_whitespace($default['fields']);

	}
	public function masking($masked_fields = array())
	{
		$this->reset_masking_fields();
		foreach ($masked_fields as $key => $value) {
			if( is_array($value) )
			{			
				if(isset($value[1]))
				{
					$value = array('column' => $value[0], 'default' => $value[1]);			
				}
			}else
			{				
				$value = array('column' => $value, 'default' => '');
			}
			$this->masked_fields[$key] = $value['column'];
			$this->original_masked_fields[$key] = $value;
		}
	}
	public function reset_masking_fields()
	{
			$this->masked_fields = array();
	}

	public function get_masking_fields($key = FALSE)
	{
		if($key!=FALSE) return $this->masked_fields[$key];
		return $this->masked_fields;
	}

	public function get_original_masking_fields($key = FALSE)
	{
		if($key!=FALSE) return $this->original_masked_fields[$key];
		return $this->original_masked_fields;
	}

	public function query_form()
	{
		// $this->requested_parts = implode(glue, pieces)
		$def = $this->masked_fields;
		$req = [];
		$requested_parts = $this->get_requested_parts();

		foreach ($requested_parts as $key => $value) {

			$keys = array_keys($def);
			if(in_array($value, $keys))
			{
				$def[$value] = empty($def[$value])? '"'.$value.'"' : $def[$value];
				$req[$value] = $def[$value].' as '.$value;
			}
		}
		$def = array_values($req);
		return implode(',', $def);
	}

	/*
	|
	| Function untuk membaca fields yang di minta.
	|
	*/
	public function requested_fields($fields = FALSE)
	{

		if(!$fields)
		{
			$fields = $this->method['fields'];
		}
		if(is_string($fields))
		{
			$fields = advance_explode($fields);
		}

		// read array_keys dari masked_fields
		$keys 		= array_keys($this->masked_fields);
		// melakukan pengecheckan jika fields memiliki parameter

		$fields = $this->recognize_field_contain_fields($fields);
		$fields = $this->recognize_field_parametered($fields);
		$key_diff 	= array_diff($fields, $keys);
		$key_assoc 	= array_intersect($fields, $keys);
		$this->set_requested_parts($key_assoc);
		if(count($key_diff) > 0)
		{
				$this->set_error('404.2.1', "fields ".implode(',', $key_diff)." cant be recognized. please check again API document and your fields");
			
		}
		return $key_assoc;
	}

	public function get_actual_value($key)
	{
		return isset($this->masked_fields[$key])? $this->masked_fields[$key] : $key;
	}

	public function process($data)
	{

		$this->data_advanced_processing_field = $data;

	}

	public function table($table)
	{
		$this->db->from($table);
	}

	/**
	 * @param $fields - array() - fields that must be included
	 */
	public function requirement_fields($fields)
	{
		$this->reset_requirement_fields();
		if(is_string($fields))
		{
			$fields = remove_all_whitespace($fields);
			$fields = explode(',', $fields);
		}
		$this->set_requirement_fields($fields);
	}
	public function is_requirement_fields()
	{
		$data = $this->get_requirement_fields();
		return count($data) > 0? TRUE : FALSE;
	}
	public function set_requirement_fields($data)
	{
		$this->data_requirement_fields = $data;
	}
	public function reset_requirement_fields()
	{
		$this->set_requirement_fields(array());
	}
	public function get_requirement_fields()
	{
		return $this->data_requirement_fields;
	}
	public function check_requirement_fields($fields = array(), $type="STRICT")
	{
		$getSetter = $this->get_requirement_fields();
		$diff = array_diff($getSetter, array_keys($fields));
		if(count($diff) > 0)
		{
			switch ($type) {
				case 'STRICT':
						$error = 'please fill up data '.implode(',', $diff);
						show_error($error, 500);
						header('HTTP/1.0 500 '.$error);
						return false;
					break;
				
				default:
					# code...
					break;
			}
		}
		$this->reset_requirement_fields();
		return $diff;
	}
	public function recognize_requirement_fields()
	{
		$data 	= $this->get_requirement_fields();
		$req 	= $this->get_requested_parts();
		$diff 	= array_diff($data, $req);
		$is_req = $this->is_requirement_fields();
		if($is_req && count($diff) > 0)
		{
			$this->set_error('404.2.2');
			return false;
		}

	}

	public function add_last_query($query)
	{
		$this->last_query[] = $query;
	}

	public function get_last_query()
	{
		return $this->last_query;
	}

	public function fusion_data( $table, $data = array(),$exclude = array())
	{
		$col 	= $this->get_original_masking_fields();
		foreach ($col as $key => $value) {
			if(in_array($key, $exclude)) continue;

			$column_obj  = explode('.', $value['column']);
			$table_name	 = array_shift($column_obj);
			$column_name = implode('.', $column_obj);
			
			if($table == $table_name)
			{
				$column[]  = $column_name;
				$bind[] = '?';
				$values[] = isset($data[$key])? $data[$key] : $value['default'];
				$_obj[$column_name] = isset($data[$key])? $data[$key] : $value['default'];

			}
		}

		return array(
			'binds' => $bind,
			'columns' => $column,
			'values' => $values,
			'default' => $_obj,
			'_raw' => $col,
		);
		/*$col 	= implode(',', $col);
		$bind 	= implode(',', $bind);*/
	}

	public function compile()
	{
		$compile_result['code'] = 200;
		$compile_result['is_error'] = FALSE;
		
		$this->requested_fields($this->method['fields']);
		$this->active_query_recognizer($this->method);

		$this->recognize_requirement_fields();
		if($this->is_error())
		{

			$compile_result['code'] = 500;
			$compile_result['error'] = $this->get_error();
			$compile_result['is_error'] = TRUE;
			return $compile_result;
		}

		$this->recognize_dependency();
		$select = $this->query_form();
		$this->db->select($select,FALSE);
		$result = $this->db->get();
		$last_query = $this->db->last_query();
		$this->add_last_query($last_query);
		$e = $result->result_array();


		$this->data_original_result = $e;
		$requested_parts 			= $this->requested_parts;
		$advanced_process 			= $this->data_advanced_processing_field;

		$this->data_advanced_processing_field = array();
		$another_processing_fields 	= array();
		$this->reset_requirement_fields();

		foreach ($advanced_process as $key => $value) {
			foreach ($e as $key_e => $value_e) {
				if(in_array($key, $requested_parts))
				{
					
					// execute function 
					$field_params = $this->get_field_parameter($key);
					$event = array(
							'result' => $e,
							'index' => $key_e,
							'record' => $value_e,
							'another_processing_fields' => $another_processing_fields
						);
					$newval = $value($field_params, $value_e, $event);

					// change on the fly result_array
					$result->result_array[$key_e][$key] = $newval;

					// pass the "another_processing_fields" result to next function
					$another_processing_fields[$key] = $newval;

				}
			}
			
		}

		$this->unset_dependency($result);
		$compile_result['data'] = $result;
		$compile_result['last_query'] = $this->get_last_query();
		return $compile_result;
		// return $this->db;
	}

	public function get_field_parameter($fieldname)
	{
		$field_params 	= $this->get_field_parametered($fieldname);
		$field 			= $this->get_field_contain_fields($fieldname);
		$data['parameter'] = isset($field_params)? explode(',', $field_params) : array();
		$data['fields'] = $field;

		return $data;
	}

	private function error_list()
	{
		$error = array(
				'404.1.1' => 'Server has critical error when configure allowed permission type. ',
				'404.1.2' => 'Your method is not allowed. unexpectedly close the connection',
				'404.2.1' => 'One or some fields cant be recognized. please check again API document and your fields',
				'404.2.2' => 'This request need certain fields. please check the Documentation!'
			);
		return $error;
	}

	private function get_error_value($error)
	{
		return $this->error_list()[$error];
	}

	public function set_error($code, $message = NULL)
	{
		$message = isset($message)?$message:$this->get_error_value($code);
		$this->error[] = array('code' => $code, 'message' => $message);		
	}
	
	public function get_error()
	{
		return $this->error;
	}

	public function is_error()
	{
		return count($this->get_error()) > 0? TRUE : FALSE;
	}

	public function debug()
	{
		foreach ($this->get_error() as $key => $value) {
			echo <<<EOF
			<h3>Error code {$value['code']}</h3>
			<div> {$value['message']} </div>
EOF;
		}
	}


	public function using_pagination($using_pagination = TRUE)
	{
		$this->using_pagination = $using_pagination;
	}

	public function is_using_pagination()
	{
		return $this->using_pagination;
	}
	// BELOW here is Pagination's buddy
	public function pagination()
	{
		// get limit components
		// $limit = $this->db->get_components('limit');
		// get offset components
		// $offset = $this->db->get_components('offset');
		$offset = @$offset>0?$offset:0;
		// set the limit and offset
		$this->set_limit(@$limit,$offset);

		// get request URI
		$request_uri 		= $_SERVER['REQUEST_URI'];
		$request_uri_arr 	= explode('/', $request_uri);
		if($request_uri_arr[0] == ''){array_shift($request_uri_arr);}
		array_shift($request_uri_arr);
		$request_uri_arr 	= implode('/', $request_uri_arr);
		$this->parse();
		// $total 				= @$this->db->count_all($this->db->get_components('from')[0]);
		$prev 				= @$offset > 0? $this->generate_prev_request():NULL;
		$next 				= (@$offset+@$limit) < @$total? $this->generate_next_request() : NULL;
		return array(
				'pages_length' => @$total > 0 ? ceil(@$total/@$limit) : 1,
				'current_page' => @$offset > 0 ? @ceil(@$offset/@$limit)+1 : 1,
				'o' => @$offset,
				'l' => @$limit,
				'total' => @$total,
				'next' => $next,
				'prev' => $prev,
				'last_index' => @$offset, // usefull to create numbering in angularjs
				'start_index' => @$offset+1, // usefull to create numbering in angularjs
			);
	}
	public function last_request()
	{
		return $this->last_requested_url;
	}
	private function parse_request()
	{
		return parse_url($this->last_request());
	}
	public function parse()
	{
		$parse = $this->parse_request();
		parse_str(@$parse['query'], $get_query);
		$requested_keys = array_keys($get_query);		
		$allowed_keys = array_intersect($this->allowed_params, $requested_keys);

		foreach ($allowed_keys as $key => $value) {
			$params = array();
			$key_value = $get_query[$value];
			$params[] = $this;
			$params[] = "parse_".$value;
			$this->request_parts[$value] = $key_value;
			call_user_func_array($params, array($key_value));
		}
	}

	private function parse_limit($value)
	{
		$val = explode(',',$value);
		$this->request_parts['limit'] = $val;
		$countVal = count($val);
		switch ($countVal) {
			default:
			case 1:
				$this->limit = $val[0];
				break;
			
			case 2:
				$this->limit = $val[0];
				$this->offset = $val[1];
				# code...
				break;
		}

	}


	public function set_limit($newLimit=0, $newOffset=0)
	{
		$this->limit = $newLimit;
		$this->offset = $newOffset>0?$newOffset:0;
		$this->request_parts['limit'] = array($this->limit, $this->offset);
	}
	public function add_limit($newLimit=FALSE)
	{
		$newLimit =$newLimit?$newLimit:$this->limit;
		$limit = $newLimit + $this->current_limit();
		// jika limit sekarang - limit sekarang < 1? 0 : limit sekarang
		$limit = $limit < 1? 0 : $limit;

		$offset = $this->current_offset() + $newLimit;
		$offset = $offset < 1? 0 : $offset;

		// $this->request_parts['limit'][0] = $limit;
		$this->request_parts['limit'][1] = $offset;

	}
	public function less_limit($newLimit = FALSE)
	{
		$newLimit =$newLimit?$newLimit:$this->limit;
		$limit = $newLimit - $this->current_limit();
		// jika limit sekarang - limit sekarang < 1? 0 : limit sekarang
		$limit = $limit < 1? 0 : $limit;

		$offset = $this->current_offset() - $newLimit;
		$offset = $offset < 1? 0 : $offset;

		// $this->request_parts['limit'][0] = $limit;
		$this->request_parts['limit'][1] = $offset;
	}
	public function current_limit()
	{
		return $this->limit;
	}
	public function current_offset()
	{
		return $this->offset;
	}
	public function is_using_limit()
	{
		return $this->limit>0?TRUE:FALSE;
	}

	public function generate_prev_request($page=FALSE)
	{
		if($this->is_using_limit())
		{
			$page = $page? $page*$this->current_limit() : $page;
			$this->less_limit($page);
			$parts = array();
			foreach ($this->request_parts as $key => $value) {
				$parts[$key] = is_array($value)? implode(',', $value) : $value;
			}
			$query = http_build_query($parts);
			return rtrim($this->server_config['processing_server'],'/').$_SERVER['PATH_INFO'].'?'.$query;
		}
	}
	public function generate_next_request($page=FALSE)
	{
		if($this->is_using_limit())
		{
			$page = $page? $page*$this->current_limit() : $page;
			$this->add_limit($page);
			$parts = array();
			foreach ($this->request_parts as $key => $value) {
				$parts[$key] = is_array($value)? implode(',', $value) : $value;
			}
			$query = http_build_query($parts);
			return rtrim($this->server_config['processing_server'],'/').$_SERVER['PATH_INFO'].'?'.$query;
		}
	}

	/**
	 * FUngsi konverter fields yang di request dengan masking fields
	 * @require
	 * 	Masking_fields
	 * @param
	 * 	$string - string - field yang direquest --> id(1),no_induk(####)
	 * @return 
	 * 	- array - array yang berisikan masked_field sebagai key dan original fields sebagai value --> $somearray['id'] = 'siswa.id_siswa';
	 */
	// Above here is Pagination's buddy
	public function convert_to_original($field)
	{
		$mask = $this->get_masking_fields();
		$nfield = array();
		foreach ($field as $key => $value) {
			$nfield[$value] = $mask[$value];
		}
		return $nfield;
	}

	// convert object array to original databse columns
	public function convert_object_array_to_original($field, $alsoUnsettedFields = FALSE)
	{
		$mask = $this->get_masking_fields();

		foreach ($this->original_masked_fields as $key => $value) {
			if(isset($field[$key]))
			{
				$field[$value['column']] = $field[$key];
				unset($field[$key]);
			}else
			{
				if($alsoUnsettedFields == TRUE)
				{
					$field[$value['column']] = $value['default'];
				}
			}
		}
		return $field;
	}
	
	public function convert_to_active_query($string)
	{
		// $fields = advance_explode($string);
		$fields = $this->requested_fields($string);
		$fields = $this->convert_to_original($fields);

		return $fields;
	}
	/**
	 * Untuk mengkombinasikan nama fields dan parameter yang dibawanya.
	 * Biasanya digunakan untuk fungsi update dan delete.
	 * @param 
	 * 	- $data - array - value got from convert_to_active_query function
	 * @return
	 * 	- array - nama fields serta nilai dari parameter yang dibawanya.
	 */
	public function combine_with_params($data)
	{
		foreach ($data as $key => $value) {
			$ndata[$value] = $this->get_field_parametered($key);
		}
		// var_dump($ndata);
		return $ndata;
	}

	private function parse_fields($value)
	{
		// echo $value;
	}

}