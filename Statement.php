<?php

/**
 * ORM  framework
 * @author Cristian Lorenzetto <opensource.publicocean0@gmail.com>
 */

 class Statement
 {
        const BQT_SELECT=1;
        const BQT_UPDATE=2;
        const BQT_INSERT=3;
        const BQT_DELETE=4;
        const BQT_RAW=5;
        const BQS_SELECT = 1;
        const BQS_JOIN = 2;
        const BQS_WHERE = 3;
        const BQS_GROUP_BY = 4;
        const BQS_ORDER_BY = 5; 
        const BQS_LIMIT = 6; 
        const BQS_OFFSET = 7;
        const BQS_UPDATE = 8;
        const BQS_INSERT = 9;
        const BQS_INSERT_MULTIPLE = 10;
        const BQS_DELETE = 11;
        
        const EQS_USE_SELECT = 0x1; 
        const EQS_USE_DISTINCT = 0x2;   
        const EQS_USE_FROM = 0x4; 
        const EQS_USE_LOGIC_OPERATOR = 0x8;   
        const ERROR= 'invalid statement command ';        
         // The name of the table the current ORM instance is associated with
        protected $table_name;
        protected $table_columns_types=array();
        // Name of the column to use as the primary key for
        // this instance only. Overrides the config settings.
        protected $id_column = null;
        protected $orm;
        protected $building_query_type=null;
        protected $building_query_step=null;

        protected $extra_query_status=0;

        // Values to be bound to the query
        protected $building_values = array();

          // The raw query
        protected $raw_query = '';



       public function __construct(ORM $orm,$table,$c_types,$_id_column=null){
        $this->orm=$orm;
        $this->table_name=$table;
        $this->table_columns_types=$c_types;
        $this->id_column=$_id_column;
        $this->building_values=array('conditions'=>array(),'select_filters'=>array());
       }
       
       public function autodetect_schema(){
         if ($this->building_query_type!=null) throw new Exception(self::ERROR.__METHOD__);
         $orm=$this->orm;
         $columns=$orm->get_columns_by_table($this->table_name);
         $this->table_columns_types=array();
         foreach($columns as $col) $this->table_columns_types[$col['column_name']]=$col['data_type'];
         $primaries=$orm->get_primary_columns_by_table($this->table_name);
         if (count($primaries)!=1) throw new Exception('table must contain just a primary key');
         $this->id_column=$primaries[0];
       }
       
       private function convert_row_from_SQL($row){
          $orm=$this->orm;

          foreach($row as $key=>$value) {
          $row[$key]=$orm->convertFromSQLType(isset($this->table_columns_types[$key])?$this->table_columns_types[$key]:'string',$value);
          }
          return $row;
       }
       
        private function convert_row_to_SQL($row){
          $orm=$this->orm;
          foreach($row as $key=>$value) {
          $row[$key]=$orm->convertToSQLType(isset($this->table_columns_types[$key])?$this->table_columns_types[$key]:'string',$value);
          }
          return $row;
       }
       
        public function set_dirty_field($id,$key){
            $this->orm->set_dirty_field_by_table($this->table_name,$id,$key);
        }
        
        
        public function get_dirty_fields($id){
            return   $this->orm->get_dirty_fields_by_table($this->table_name,$id);
        }
        
        
        public function remove_dirty_fields($id){
            return   $this->orm->remove_dirty_fields_by_table($this->table_name,$id);
        }
        
        public function remove_dirty_field($id,$key){
            return   $this->orm->remove_dirty_field_by_table($this->table_name,$id,$key);
        }


        public function create($data=array(),$is_new=true) {
            return new Entity($this,$data,$is_new);
        }
        
        public function use_id_column($id_column) {
            $this->id_column = $id_column;
            return $this;
        }
        
        public function id_column() { 
            $config=$this->orm->get_config();
            if (is_null($this->id_column)) return $config['id_column'];
            return $this->id_column;
        }
        
        public function find_one($r=true) {
            if ($this->building_query_type==null){
                $this->complete_select_from();
            }  else if  ($this->building_query_type!=self::BQT_SELECT)  throw new Exception(self::ERROR.__METHOD__);
            $this->limit(1);
            $is_select_all= ($this->extra_query_status & self::EQS_USE_SELECT)!= self::EQS_USE_SELECT;
            $this->raw_query='SELECT '.(($is_select_all)?'* ':join(',',$this->building_values['select_filters'])).$this->raw_query;
            $this->castToSQLParameters($this->building_values['conditions'],$values,$types);
            $rows = $this->run($values,$types);

            if (empty($rows)) {
                return false;
            }
            $v=$rows[0];
            
            if ($r) return $v;
            return new Entity($this,$v,false);
        }
        
        protected function create_instance_from_row($row) {
            return new Entity($this,$row,false);
        }

        public function find_many($r=true) {
            if ($this->building_query_type==null){
                $this->complete_select_from();
            } else if  ($this->building_query_type!=self::BQT_SELECT)  throw new Exception(self::ERROR.__METHOD__);
            $is_select_all= ($this->extra_query_status & self::EQS_USE_SELECT)!= self::EQS_USE_SELECT;
            $this->raw_query='SELECT '.(($is_select_all)?'* ':join(',',$this->building_values['select_filters'])).$this->raw_query;
            $this->castToSQLParameters($this->building_values['conditions'],$values,$types);
            $_data= $this->run($values,$types);
            //ar_dump($_data);
            if ($r) return $_data;
            return array_map(array($this, 'create_instance_from_row'),  $_data);
        }


        public function count($column=null) {
            $is_select_all= ($this->extra_query_status & self::EQS_USE_SELECT)!= self::EQS_USE_SELECT;
            if ($column==null) $column='*';
            $this->limit(1);
            $this->raw_query='SELECT '.(($is_select_all)?'COUNT('.$column.') as count ':'COUNT('.$column.') as count, ').$this->raw_query;
            $this->castToSQLParameters($this->building_values['conditions'],$values,$types);
            $result = $this->run($values,$types);
            //vr_dump($result);
            return ($result !== false && (count($result)>0) && isset($result[0]['count'])) ? (int) $result[0]['count'] : 0;
        }
        
        public function max($column) {
            $is_select_all= ($this->extra_query_status & self::EQS_USE_SELECT)!= self::EQS_USE_SELECT;
            $this->raw_query='SELECT '.(($is_select_all)?'MAX('.$column.') as max ':'MAX('.$column.') as max, ').$this->raw_query;
            $this->castToSQLParameters($this->building_values['conditions'],$values,$types);
            $result = $this->run($values,$types);
            return ($result !== false && isset($result['max'])) ? (int) $result['max'] : 0;
        }
        
        public function min($column) {
            $is_select_all= ($this->extra_query_status & self::EQS_USE_SELECT)!= self::EQS_USE_SELECT;
            $this->raw_query='SELECT '.(($is_select_all)?'MIN('.$column.') as min ':'MIN('.$column.') as min, ').$this->raw_query;
            $this->castToSQLParameters($this->building_values['conditions'],$values,$types);
            $result = $this->run($values,$types);
            return ($result !== false && isset($result['min'])) ? (int) $result['min'] : 0;
        }
        
        
        public function raw_query($query, $parameters) {
           if  ($this->building_query_type==null)  {
              $this->building_query_type=self::BQT_RAW;
              $this->raw_query = $query;
              $this->raw_parameters = $parameters;
            } else throw new Exception(self::ERROR.__METHOD__);
            $this->castToSQLParameters($parameters,$values,$types);
            $result = $this->run($values,$types);
            return $this->run($parameters);
        }
        
        
        public function select($column, $alias=null) {
            if  ($this->building_query_type==null)  {
              $this->extra_query_status=$this->extra_query_status|self::EQS_USE_SELECT; 
              $this->building_values['select_filters'][]=$this->quote_identifier($column).(($alias==null)?'':' AS '.$alias);
            } else throw new Exception(self::ERROR.__METHOD__);
            
            return $this;
        }
        
        
          public function distinct() {
            if  ($this->building_query_type==null)  {
            $this->building_query_type=self::BQT_SELECT;
            $this->building_query_step=self::BQS_SELECT_DISTINCT;
            $this->extra_query_status=$this->extra_query_status|self::EQS_USE_DISTINCT; 
            } else throw new Exception(self::ERROR.__METHOD__);
            
            return $this;
        }
        
        private  function complete_select_from(){        
              if ($this->building_query_type==self::BQT_SELECT && (($this->extra_query_status & self::EQS_USE_FROM)!= self::EQS_USE_FROM) ){
                  $table = $this->quote_identifier($this->table_name);
                  $this->raw_query='FROM '.$table.' '.$this->raw_query;
                  $this->extra_query_status= $this->extra_query_status | self::EQS_USE_FROM;
              } else if ($this->building_query_type==null){
                  $table = $this->quote_identifier($this->table_name);
                  $this->raw_query='FROM '.$table.' '.$this->raw_query;
                  $this->building_query_type=self::BQT_SELECT;
                  $this->building_query_step=self::BQS_SELECT;
                  $this->extra_query_status= $this->extra_query_status | self::EQS_USE_FROM;
              }     
        }
        

        protected function add_join_source($join_operator, $table, $constraint, $table_alias=null) {
              $this->complete_select_from();
              //echo $this->raw_query;
             if  ($this->building_query_type==self::BQT_SELECT && $this->building_query_step<=self::BQS_JOIN)  {
              $this->building_query_step=self::BQS_JOIN;
              $join_operator = trim("{$join_operator} JOIN");
              $table = $this->quote_identifier($table);
              // Add table alias if present
              if (!is_null($table_alias)) {
                  $table_alias = $this->quote_identifier($table_alias);
                  $table .= " {$table_alias}";
              }
  
              // Build the constraint
              if (is_array($constraint)) {
                  list($first_column, $operator, $second_column) = $constraint;
                  $first_column = $this->quote_identifier($first_column);
                  $second_column = $this->quote_identifier($second_column);
                  $constraint = "{$first_column} {$operator} {$second_column}";
              }
  
              $this->raw_query.= " {$join_operator} {$table} ON {$constraint}";
              } else  throw new Exception(self::ERROR.__METHOD__);
              return $this;
            }
            

        public function quote_identifier($identifier) {
            $parts = explode('.', $identifier);
            $parts = array_map(array($this, 'quote_identifier_part'), $parts);
            return join('.', $parts);
        }


        protected function quote_identifier_part($part) {
            if ($part === '*') {
                return $part;
            }
            $quote_character = $this->orm->quote_character();
            return $quote_character . $part . $quote_character;
        }

          public function group_by($column) {
            if  ($this->building_query_type==self::BQT_SELECT||$this->building_query_type==null)  {
              $this->complete_select_from();
              if ($this->building_query_step<self::BQS_GROUP_BY) {
               $this->raw_query.='  GROUP BY '.$this->quote_identifier($column);
               $this->building_query_step=self::BQS_GROUP_BY;
              }
              else if  ($this->building_query_step==self::BQS_GROUP_BY) $this->raw_query.=' ,'.$this->quote_identifier($column);
              else throw new Exception(self::ERROR.__METHOD__);
            } else  throw new Exception(self::ERROR.__METHOD__);
            return $this;
        }  
        
        
         protected function order_by($column,$order) {
              $this->complete_select_from();
              if ($this->building_query_step<self::BQS_ORDER_BY) {
               $this->raw_query.='  ORDER BY '.$this->quote_identifier($column).' '.$order;
               $this->building_query_step=self::BQS_ORDER_BY;
              }
              if  ($this->building_query_step==self::BQS_ORDER_BY) $this->raw_query.=' ,'.$this->quote_identifier($column).' '.$order;
              else  throw new Exception(self::ERROR.__METHOD__);
            return $this;
        }  
        
        
        public function limit($n) {  
              $this->complete_select_from();
              if ($this->building_query_step<self::BQS_LIMIT) {   
               $this->raw_query.='  LIMIT '.$n;
               $this->building_query_step=self::BQS_LIMIT;
              }
              else if  ($this->building_query_step>self::BQS_LIMIT) throw new Exception(self::ERROR.__METHOD__);

            return $this;
        }  
        
        public function offset($n) {
            $this->complete_select_from();
            if ($this->building_query_step<self::BQS_OFFSET) {
               $this->raw_query.='  OFFSET '.$n;
               $this->building_query_step=self::BQS_OFFSET;
              }else if  ($this->building_query_step>self::BQS_OFFSET) throw new Exception(self::ERROR.__METHOD__);
            return $this;
        }  
        
        
        
        
        public function order_by_asc($column) {
         return $this->order_by($column,'ASC');
        }

        public function order_by_desc($column) {
         return $this->order_by($column,'DESC');
        }
        

        public function add_where_fragment($fragment) {


            if  ( $this->building_query_step!=self::BQS_WHERE )  {
           
              $this->raw_query.=' WHERE '.$fragment; 
              $this->building_query_step=self::BQS_WHERE;
               
              } else if ($this->building_query_step==self::BQS_WHERE) {
                $this->raw_query.=' '.((($this->extra_query_status & self::EQS_USE_LOGIC_OPERATOR)!= self::EQS_USE_LOGIC_OPERATOR)?'AND ':'').$fragment;  
                $this->extra_query_status=$this->extra_query_status & (~ self::EQS_USE_LOGIC_OPERATOR);
             } else throw new Exception(self::ERROR.__METHOD__);
            
            
            return $this;
        }
        
        
        public function logic_or() {             
             if ($this->building_query_step==self::BQS_WHERE) {
                $this->raw_query.=' OR';
                $this->extra_query_status=self::EQS_USE_LOGIC_OPERATOR;  
             } else throw new Exception(self::ERROR.__METHOD__);
            return $this;
        }
        
        public function logic_and() {             
             if ($this->building_query_step==self::BQS_WHERE) {
                $this->raw_query.=' AND';
                $this->extra_query_status=self::EQS_USE_LOGIC_OPERATOR;  
             } else throw new Exception(self::ERROR.__METHOD__);
            return $this;
        }
        
        
                /**
* Add a simple JOIN source to the query
*/
        public function join($table, $constraint, $table_alias=null) {
            return $this->add_join_source("", $table, $constraint, $table_alias);
        }

        /**
* Add an INNER JOIN souce to the query
*/
        public function inner_join($table, $constraint, $table_alias=null) {
            return $this->add_join_source("INNER", $table, $constraint, $table_alias);
        }

        /**
* Add a LEFT OUTER JOIN souce to the query
*/
        public function left_outer_join($table, $constraint, $table_alias=null) {
            return $this->add_join_source("LEFT OUTER", $table, $constraint, $table_alias);
        }

        /**
* Add an RIGHT OUTER JOIN souce to the query
*/
        public function right_outer_join($table, $constraint, $table_alias=null) {
            return $this->add_join_source("RIGHT OUTER", $table, $constraint, $table_alias);
        }

        /**
* Add an FULL OUTER JOIN souce to the query
*/
        public function full_outer_join($table, $constraint, $table_alias=null) {
            return $this->add_join_source("FULL OUTER", $table, $constraint, $table_alias);
        }




        
        
        protected function add_simple_where($column_name, $separator, $value) {
            $column_name = $this->quote_identifier($column_name);
            $this->building_values['conditions'][] = $value;
            return $this->add_where_fragment("{$column_name} {$separator} ?");
        }
        
        
               /**
* Add a WHERE column = value clause to your query. Each time
* this is called in the chain, an additional WHERE will be
* added, and these will be ANDed together when the final query
* is built.
*/
        public function where($column_name, $value) {
            return $this->where_equal($column_name, $value);
        }

        /**
* More explicitly named version of for the where() method.
* Can be used if preferred.
*/
        public function where_equal($column_name, $value) {
            return $this->add_simple_where($column_name, '=', $value);
        }

        /**
* Add a WHERE column != value clause to your query.
*/
        public function where_not_equal($column_name, $value) {
            return $this->add_simple_where($column_name, '!=', $value);
        }

        /**
* Special method to query the table by its primary key
*/
        public function where_id_is($id) {
            return $this->where($this->id_column(), $id);
        }

        /**
* Add a WHERE ... LIKE clause to your query.
*/
        public function where_like($column_name, $value) {
            return $this->add_simple_where($column_name, 'LIKE', $value);
        }

        /**
* Add where WHERE ... NOT LIKE clause to your query.
*/
        public function where_not_like($column_name, $value) {
            return $this->add_simple_where($column_name, 'NOT LIKE', $value);
        }

        /**
* Add a WHERE ... > clause to your query
*/
        public function where_gt($column_name, $value) {
            return $this->add_simple_where($column_name, '>', $value);
        }

        /**
* Add a WHERE ... < clause to your query
*/
        public function where_lt($column_name, $value) {
            return $this->add_simple_where($column_name, '<', $value);
        }

        /**
* Add a WHERE ... >= clause to your query
*/
        public function where_gte($column_name, $value) {
            return $this->add_simple_where($column_name, '>=', $value);
        }

        /**
* Add a WHERE ... <= clause to your query
*/
        public function where_lte($column_name, $value) {
            return $this->add_simple_where($column_name, '<=', $value);
        }

        /**
* Add a WHERE ... IN clause to your query
*/
        public function where_in($column_name, $values) {
            $column_name = $this->quote_identifier($column_name);
            $placeholders = $this->create_placeholders(count($values));
            $this->building_values['conditions']= array_merge($this->building_values['conditions'],$values);
            return $this->add_where_fragment("{$column_name} IN ({$placeholders})");
        }

        /**
* Add a WHERE ... NOT IN clause to your query
*/
        public function where_not_in($column_name, $values) {
            $column_name = $this->quote_identifier($column_name);
            $placeholders = $this->create_placeholders(count($values));
            $this->building_values['conditions']= array_merge($this->building_values['conditions'],$values);
            return $this->add_where_fragment("{$column_name} NOT IN ({$placeholders})");
        }

        /**
* Add a WHERE column IS NULL clause to your query
*/
        public function where_null($column_name) {
            $column_name = $this->quote_identifier($column_name);
            return $this->add_where_fragment("{$column_name} IS NULL");
        }

        /**
* Add a WHERE column IS NOT NULL clause to your query
*/
        public function where_not_null($column_name) {
            $column_name = $this->quote_identifier($column_name);
            return $this->add_wher_fragment("{$column_name} IS NOT NULL");
        }

        /**
* Add a raw WHERE clause to the query. The clause should
* contain question mark placeholders, which will be bound
* to the parameters supplied in the second argument.
*/
        public function where_raw($clause, $parameters=array()) {
            $this->building_values['conditions']= array_merge($this->building_values['conditions'],$parameters);
            return $this->add_where_fragment($clause);
        }

        
        
        

        public function delete(array $ids=null) {
            if  ($this->building_query_type==null && $this->building_query_step==null )  {
              $this->building_query_type=self::BQT_DELETE;
              $this->building_query_step=self::BQS_DELETE;
              $table=$this->quote_identifier($this->table_name);
              $id_key=$this->id_column();
              $this->raw_query='DELETE FROM '.$table;
              if ($ids==null) return $this->run(array(),array(),array($id_key,'SELECT '.$this->quote_identifier($id_key).' as id FROM '.$table));
              else if (!is_array($ids)){
                 $this->raw_query.='WHERE '.$this->quote_identifier($id_key).'= ?';
                 $this->castToSQLParameter($id_key,$ids,$v,$t);
                 return $this->run(array($v),array($t),array($id_key,$ids));
              } else {
                $count=count($ids);
                if ($count==1) {
                  $this->raw_query.='WHERE '.$this->quote_identifier($id_key).'= ?';
                  $this->castToSQLParameter($id_key,$ids[0],$v,$t);
                  $this->run(array($v),array($t),array($id_key,$ids));
                }
                else if ($count>1) {
                  $this->raw_query.='WHERE '.$this->quote_identifier($id_key).' IN ('.$this->create_placeholders($count).')';
                  $this->castToSQLParameters($ids,$values,$types);
                  return $this->run($values,$types,array($id_key,$ids));
                  }
              }
            } else if ($this->building_query_type==null && $this->building_query_step==self::BQS_WHERE && $ids==null) {
              $this->building_query_type=self::BQT_DELETE;    
              $this->building_query_step=self::BQS_DELETE;
              $id_key=$this->id_column();
              $query= $this->raw_query;
              $this->raw_query='DELETE FROM '.$this->quote_identifier($this->table_name). $query ;
              $this->castToSQLParameters($this->building_values['conditions'],$values,$types);
              return $this->run($values,$types,array($id_key,'SELECT '.$this->quote_identifier($this->id_column()).' AS id FROM '.$this->quote_identifier($this->table_name).$query));
            } else  throw new Exception(self::ERROR.__METHOD__);            
          
        }
        
        
        protected function purge_data($id_key,array &$data){
                $purgedData=array();                
                $dirty_fields=$this->get_dirty_fields($data[$id_key]);
                if ($dirty_fields==null) return $data;
                foreach ($dirty_fields as $key) $purgedData[$key]=$data[$key];
                return $purgedData; 
        } 
        
         public function update(array &$data) {
            if  ($this->building_query_type==null)  {
              $this->building_query_type=self::BQT_UPDATE;
              $this->building_query_step=self::BQS_UPDATE;
              $id_key=$this->id_column();
  
              $query='UPDATE '.$this->quote_identifier($this->table_name).' SET ';
              $tdata=$this->purge_data($id_key,$data);
              if (count($tdata)==0) {
                $this->reset_status();
                return true;
              }
              var_dump(array_merge($tdata,array($id_key=>$data[$id_key])));
              $field_list = array();
              foreach ($tdata as  $key=>$value) {
                  $field_list[] = "{$this->quote_identifier($key)} = ? ";
              }
              $query.= join(", ", $field_list);
              $query.= " WHERE ";
              $query.= $this->quote_identifier($id_key);
              $query.= "= ?";
              $this->raw_query=$query;
              $this->castToSQLParameters($tdata,$values,$types);
              $this->castToSQLParameter($id_key,$data[$id_key],$v,$t);
              $values[]=$v;
              $types[]=$t;
            return $this->run($values,$types,array($id_key,array($data[$id_key])));
            } else if ($this->building_query_type==null && $this->building_query_step==self::BQT_WHERE) {
              $this->building_query_type=self::BQT_UPDATE;
              $this->building_query_step=self::BQS_UPDATE;
              $id_key=$this->id_column();
              $query='UPDATE '.$this->quote_identifier($this->table_name).' SET ';                        
              $tdata=$this->convert_row_to_SQL($this->purge_data($id_key,$data));
              $field_list = array();
              $ids=array();
              foreach ($data as  $value) {
                  $ids[] = $value[$id_key];
              }
              foreach ($tdata as  $value) {
                  $field_list[] = "{$this->quote_identifier($value)} = ? ";
              }
              $query.= join(", ", $field_list);
              $query.= " WHERE ".$this->_raw_query;
              $this->_raw_query=$query;
              $this->castToSQLParameters($tdata,$values1,$types1);
              $this->castToSQLParameters($this->building_values['conditions'],$values2,$types2);
              return $this->run(array_merge($values1,$values2),array_merge($types1,$types2),array($id_key,$ids));
            } else  throw new Exception(self::ERROR.__METHOD__);            
            
            
        }

        protected function create_placeholders($number_of_placeholders) {
            return join(", ", array_fill(0, $number_of_placeholders, "?"));
        }
        
        
        function insert(array &$data) {
            
            if  ($this->building_query_type==null)  {
            $this->building_query_type=self::BQT_INSERT;
            $this->building_query_step=self::BQS_INSERT;
            $tdata=$this->convert_row_to_SQL($data);

            $tdata_keys=array_keys($tdata);
            $query = "INSERT INTO ";
            $query.= $this->quote_identifier($this->table_name);
            $field_list = array_map(array($this, 'quote_identifier'), $tdata_keys);
            $query.= " (" . join(", ", $field_list) . ")";
            $query.= " VALUES";
            $placeholders = $this->create_placeholders(count($data));
            $query.= "({$placeholders})";
            $this->raw_query=$query;
            } else throw new Exception(self::ERROR.__METHOD__);            
            $key_id=$this->id_column();
            $this->castToSQLParameters($tdata,$values,$types);
            return $this->run($values,$types,array($key_id,$data[$key_id]));
        }
        
        
        function multiple_insert(array &$data) {
            if  ($this->building_query_type==null)  {
            $this->building_query_type=self::BQT_INSERT;
            $this->building_query_step=self::BQS_INSERT_MULTIPLE;
            if (count($data)==0) return true;
            $data_keys=array_keys($data[0]);
            $query = "INSERT INTO ";
            $query.= $this->quote_identifier($this->_table_name);
            $field_list = array_map(array($this, 'quote_identifier'), $data_keys);
            $query.= " (" . join(", ", $field_list) . ")";
            $query.= " VALUES";
            $placeholders = $this->create_placeholders(count($data[0]));
            $query.= join(", ", array_fill(0, count($data), "({$placeholders})"));
            $this->raw_query=$query;
            } else throw new Exception(self::ERROR.__METHOD__);
            $values=array();
            $types=array();
            $count=count($data);
            for($i=0;$i<$count;$i++) {
              $this->castToSQLParameters($data[$i],$values0,$types0);
              $values=array_merge($values,$values0);
              $types=array_merge($types,$types0);
            }
            $key_id=$this->id_column();
            return $this->run($values,$types,array($count,$key_id,array_search($key_id,$data_keys)));
        }

        private static  function get_type(&$var)
        {
            if(is_object($var))
                return 'blob';
            if(is_null($var))
                return 'null';
            if(is_string($var))
                return 'string';
            if(is_array($var))
                return 'blob';
            if(is_int($var))
                return 'int';
            if(is_bool($var))
                return 'boolean';
            if(is_float($var))
                return 'float';
            if(is_double($var))
                return 'double';

            return null;
        }
        
       private function  castToSQLParameters(&$data,&$values,&$types){
         $values=array();
         $types=array();
         foreach ($data as $k=>$v) {
           $this->castToSQLParameter($k,$v,$value,$type);
           $types[]=$type;
           $values[]=$value;
         }
       }
       
         private function  castToSQLParameter($k,&$v,&$value,&$type){
           $orm=$this->orm;
           if (is_array($v)) {
             $value=$orm->convertToSQLType($type,$v[0]);
             $type=$v[1];
           }
           else {
             if (isset($this->table_columns_types[$k])) { $type=$this->table_columns_types[$k]; }
             else {$type=self::get_type($v); }
             $value=$orm->convertToSQLType($type,$v);
           }
       }
        
       private function execute($querytype,&$query,&$values,&$types,&$success){
         $orm=$this->orm;

         //echo $query;
         //var_dump($values);
         //vr_dump($types);
         if ($querytype==self::BQT_SELECT || $querytype==self::BQT_RAW){
             $result=$orm->get_cached_query($this->table_name,$query,$values,$types,$success);
             if ($success) return $result;
             else {
                 $statement=$orm->prepare($query,$values,$types);
                 $success=$orm->execute_statement($statement);
                  $rows = array();
                  while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
             
                      
                      $rows[] = $this->convert_row_from_SQL($row);
                  }

                 $orm->set_cached_query($this->table_name,$query,$values,$types,$rows);
                 return $rows;
             }
         } else if ($querytype==self::BQT_DELETE || $querytype==self::BQT_UPDATE || $querytype==self::BQT_INSERT){
           $orm->invalid_cache($this->table_name);
           //echo "...........$query<br>";
           //var_dump($values);

           $statement=$orm->prepare($query,$values,$types);
           $success=$orm->execute_statement($statement);
           return $success;
         }
       }
      
      

        protected function run(array &$values,array &$types ,$info=null) {
            try{
                if  ($this->building_query_type==self::BQT_DELETE && !is_array($info[1]) ){    
                   $orm=$this->orm;
                   $statement1=$orm->prepare($info[1],$values,$types);
                   $success1=$orm->execute_statement($statement1);
                   $ids=array();
                   if ($success1) {
                        while ($row = $statement1->fetch(PDO::FETCH_NUM)) {
                            $ids[]=$row[0];
                        }
                        $info[1]=$ids;
                    }  else return false;
                }
                $rows = $this->execute($this->building_query_type,$this->raw_query,$values,$types,$success);

                $this->raw_query='';
                $result=false;
                if  ($this->building_query_type==self::BQT_RAW){

                  $result= array('success'=>$success,'rows'=>$rows);            
                } else if  ($this->building_query_type==self::BQT_SELECT){
                   $result= $rows;
                } else   if  ($this->building_query_type==self::BQT_DELETE){
                  $id_key=$info[0];
                  $ids=$info[1];
                  foreach($ids as $id) $this->remove_dirty_fields($id);
                  $result=true;
                } else if ($this->building_query_type==self::BQT_UPDATE){
                  //echo $this->orm->get_last_query()."<br>";
                  foreach($info[1] as $id) $this->remove_dirty_fields($id);
                  $result=true;
                } else  if  ($this->building_query_type==self::BQT_INSERT){
                    if ($this->building_query_step==self::BQS_INSERT_MULTIPLE) {
                    if (!$success) return false;
                      $vs=array();
                      if ($info[2]===false) {
                        $id=$this->orm->last_insert_id($this->table_name,$info[1]);
                        $count=$info[0];
                        for($i=0;$i<$count;$i++) $vs[]=$id-$count+$i;
                      } else {
                         $step=$info[2];
                         for($i=$step;$i<count($values);$i+=$step) {
                            $vs[]=$values[$i];
                         }
                      }
                      $result= $vs;
                  } else {
                    if ($success) {
                      if (is_null($info[1])) {  
                          $result= $this->orm->last_insert_id($this->table_name,$info[0]);
                      } else $result= $info[1]; 
                    }
                  }           
                }  else  {
                    var_dump($this);
                    throw new ORMException(self::ERROR.__METHOD__,$this->raw_query);
                }
       
            }catch(ORMException $e){
                $this->reset_status();
                throw $e;
            }
            $this->reset_status();
            return $result;


        }

        private function reset_status(){
            $this->building_query_type=null;
            $this->building_query_step=null;
            $this->extra_query_status=0;
            $this->raw_query='';
            $this->building_values=array('conditions'=>array(),'select_filters'=>array());
        }
   



 }

         

?>