<?php

/**
 * ORM  framework
 * @author Cristian Lorenzetto <opensource.publicocean0@gmail.com>
 */

    require_once ("SQLPlatForm.php");
    require_once ("Statement.php");
    require_once ("Entity.php");
    require_once ("ORMException.php");
    
    class ORM {


        protected static  $DB_PLATFORM_MAP=array(
        'pgsql'=> 'PostgreSQLPlatform',
        'sqlsrv'=>null,
        'dblib'=>null,
        'mssql'=>null,
        'sybase'=>null,
        'mysql'=>null,
        'sqlite'=>null,
        'sqlite2'=>null
        );

        public static  $ORM_TYPE_MAP=array(
        'string',
        'int',
        'longint',
        'shortint',
        'text',
        'blob',
        'date',
        'time',
        'datetime',
        'timestamp',// format is integer or dd-mm-yy h:m:s
        'timestamptz',
        'float',
        'decimal',
        'double',
        'boolean'
        );
        
        protected $cache=null;

       
        // ------------------------ //
        // --- CLASS PROPERTIES --- //
        // ------------------------ //

        // Class configuration
        protected $_config = array(
            'connection_string' => 'sqlite::memory:',
            'id_column' => 'id',
            'username' => null,
            'password' => null,
            'driver_options' => null,
            'error_mode' => PDO::ERRMODE_EXCEPTION,
            'caching' => false,
        );
        


        protected $_pdo_platform;


        // Database connection, instance of the PDO class
        protected  $_pdo;

        // Last query run, only populated if logging is enabled
        protected  $_last_query;

        protected  $_database;


        // --------------------------- //
        // --- INSTANCE PROPERTIES --- //
        // --------------------------- //

        // Fields that have been modified during the
        // lifetime of the object
        protected $_dirty_fields = array();

        
        public function set_dirty_field_by_table($table,$id,$key){
           if (!isset($this->_dirty_fields[$table][$id]))  $this->_dirty_fields[$table][$id]=array($key);
           $this->_dirty_fields[$table][$id]=array_merge($this->_dirty_fields[$table][$id],array($key));
        }
        
        public function remove_dirty_field_by_table($table,$id,$key){

           if (!isset($this->_dirty_fields[$table][$id])){
              $f=$this->_dirty_fields[$table][$id];
              $fn=array();
              for($i=0;count($f);$i++) {
                $v=$f[$i];
                if($key!=$v) $fn[]=$v;
              }
              $this->_dirty_fields[$table][$id]=$fn;
           }
        }
        
        
        public function remove_dirty_fields_by_table($table,$id){
           if (!isset($this->_dirty_fields[$table][$id]))  unset($this->_dirty_fields[$table][$id]);
        }
        

        public function get_dirty_fields_by_table($table,$id){
           if (!isset($this->_dirty_fields[$table][$id]))  return null;
           return $this->_dirty_fields[$table][$id];
        }


       public function get_config(){
        return $this->_config;
       }



        /**
* Pass configuration settings to the class in the form of
* key/value pairs. As a shortcut, if the second argument
* is omitted, the setting is assumed to be the DSN string
* used by PDO to connect to the database. Often, this
* will be the only configuration required to use Idiorm.
*/
        public function configure($key, $value=null) {
            // Shortcut: If only one argument is passed,
            // assume it's a connection string                        
            if  ($key=='error_mode') return;
            if (is_null($value)) {
                $value = $key;
                $key = 'connection_string';
            }
            if ($key=='connection_string'){
              $ss=explode(';',$value);
              foreach($ss as $s) if (substr($s,0,6)=='dbname') {
                $sss=explode('=',$s);
                $this->_database=trim($sss[1]);
              } 
              
            }
            $this->_config[$key] = $value;
        }

 
        public  function for_table($table_name,array $columns_types=array(),$_id_column=null) {
            if ($this->_pdo==null) $this->init();
            foreach($columns_types as $t) if (array_search($t,self::$ORM_TYPE_MAP)===false) throw new Exception(" $t is not valid type");
            return new Statement($this,$table_name,$columns_types,$_id_column);
        }
        

      

        /**
* Set up the database connection used by the class.
*/      



        public  function init() {           
                $connection_string = $this->_config['connection_string'];
                $username = $this->_config['username'];
                $password = $this->_config['password'];
                $driver_options = $this->_config['driver_options'];
                $this->_pdo = new PDO($connection_string, $username, $password, $driver_options);
                $this->_pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                $this->_pdo->setAttribute(PDO::ATTR_PERSISTENT , true);
                $db_name=$this->_pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                $platform=(isset(self::$DB_PLATFORM_MAP[$db_name]))?self::$DB_PLATFORM_MAP[$db_name]:null;
                if ($platform == null) throw new Exception("$db_name platform not found");
                require_once("platform/$platform.php");
                $this->_pdo_platform=new $platform();
                if ($this->_config['caching'] && class_exists('Cache')){
                  $this->cache=new Cache($this->_database);   
                }
               
        }
        
        
        public function getPDO(){
           return $this->_pdo;
        }



        /**
* Add a query to the internal query log. Only works if the
* 'logging' config option is set to true.
*
* This works by manually binding the parameters to the query - the
* query isn't executed like this (PDO normally passes the query and
* parameters to the database which takes care of the binding) but
* doing it this way makes the logged queries more readable.
*/
        private  function log_query($query, $parameters) {
            // If logging is not enabled, do nothing


            if (count($parameters) > 0) {
                // Escape the parameters
                $parameters = array_map(array($this->_pdo, 'quote'), $parameters);

                // Replace placeholders in the query for vsprintf
                $query = str_replace("?", "%s", $query);

                // Replace the question marks in the query with the parameters
                $bound_query = vsprintf($query, $parameters);
            } else {
                $bound_query = $query;
            }
                      //  echo "|". $bound_query;
      
            //echo "|";

            $this->_last_query = $bound_query;


            return true;
        }
        
        
        private static function  generateQueryKey(&$query,&$values,&$types){
          return sha1($query.json_encode(array_merge($values,$types)));
        }
        

         public function invalid_cache($table){
          if ($this->_config['caching']) return $this->cache->removeAll($table);
          return true;
        }
        

        public function get_cached_query($table,&$query,&$values,&$types,$success){
         
          if ($this->_config['caching']) {
               $key=$table.self::generateQueryKey($query,$values,$types);
               if (isset($this->cache->$key)) {
                 $result=$this->cache->$key;
                 $success=true;
                 return $result;
                 }
          }
          $success=false;
        }
        
        
        public function set_cached_query($table,&$query,&$values,&$types,$value){
          if ($this->_config['caching']) {
               $key=$table.self::generateQueryKey($query,$values,$types);
               $this->cache->$key=$value;
          }
        }

        public function execute_statement(&$statement){
          try{
             if (!$this->_pdo->inTransaction()) {
                $this->_pdo->beginTransaction();
                $success = $statement->execute();
                $this->_pdo->commit();
              } else $success = $statement->execute();
            }catch (Exception $e){
              $this->_pdo->rollBack();
              throw new ORMException($e->getMessage,$this->_last_query);
            }

         return $success;
     }
        

        
        
        public function prepare(&$query,&$values,&$types){
            $this->log_query($query,$values);

            $statement = $this->_pdo->prepare($query);
            for($i=0;$i<count($values);$i++)  {
              $statement->bindValue($i+1, $values[$i], $this->getPDOType($types[$i]));
            }

         return $statement;
      }

        /**
* Get the last query executed. Only works if the
* 'logging' config option is set to true. Otherwise
* this will return null.
*/
        public  function get_last_query() {
            return $this->_last_query;
        }



        // ------------------------ //
        // --- INSTANCE METHODS --- //
        // ------------------------ //

        /**
* "Private" constructor; shouldn't be called directly.
* Use the ORM::for_table factory method instead.
*/
        public function __construct() {
            set_error_handler(array($this,'errorHandler'),E_STRICT );
            register_shutdown_function(array($this,'errorHandler'));
            set_exception_handler(array($this,'exceptionHandler'));

  }


        public function errorHandler($errno=null, $errstr=null,$errfile=null, $errline=null,$context=null){
         if(connection_status() != CONNECTION_NORMAL){
            if ($this->_pdo->inTransaction()) $this->_pdo->roolBack();
         }
         //echo "$errfile-$errline : $errstr<br>";
         return false;
        }
        
        
        public function exceptionHandler($ex){        
            if ($this->_pdo->inTransaction()) $this->_pdo->roolBack();
        }

 

  
        public function quote_character() {
            return  $this->_pdo_platform->getQuoteCharacter();
        }


 

        public function convertFromSQLType($type,&$value) {
              return $this->_pdo_platform->convertFromSQLType($type,$value);
        }
        
        public function convertToSQLType($type,&$value) {
              return $this->_pdo_platform->convertToSQLType($type,$value);
        }




      
      public function create_table($table,$columns,$options=array()){
       $query=$this->_pdo_platform->getCreateTableSQL($table,$columns,$options);
       $statement=$this->_pdo->prepare($query);
        $success=$this->execute_query($statement);
        return $success;
      }


      public function drop_table($table){
        $query=$this->_pdo_platform->getDeleteTableSQL($table);
        $statement=$this->_pdo->prepare($query);
        $success=$this->execute_query($statement);
        return $success;
      }
      
      public function exists_table($table){
        $query=$this->_pdo_platform->getExistsTableSQL($table);
        $statement=$this->_pdo->prepare($query);
        $success=$this->execute_query($statement);
        $row= $statement->fetch(PDO::FETCH_NUM);
        return $success && $row[0];
      }
      
      
      public function exists_sequence($s){
        $query=$this->_pdo_platform->getExistsSequenceSQL($s);
        $statement=$this->_pdo->prepare($query);
        $success=$this->execute_query($statement);
        $row= $statement->fetch(PDO::FETCH_NUM);
        return $success && $row[0];
      }
      

      public function current_sequence($s){
        $query=$this->_pdo_platform->getCurrentSequenceSQL($s);
        $statement=$this->_pdo->prepare($query);
        $success=$this->execute_query($statement);
        $row= $statement->fetch(PDO::FETCH_NUM);
        return ($success && isset($row))?$row[0]:false;
      }
      
      public function increment_sequence($s){
        $query=$this->_pdo_platform->getIncrementSequenceSQL($s);
        $statement=$this->_pdo->prepare($query);
        $success=$this->execute_query($statement);
        $row= $statement->fetch(PDO::FETCH_NUM);
         return ($success && isset($row))?$row[0]:false;
      }
      
      public function get_columns_by_table($table){
        
        $query=$this->_pdo_platform->getTableColumnsSQL($table,$this->_database);
        $statement=$this->_pdo->prepare($query);
        $this->execute_query($statement);
        $rows = array();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                  $mapping=$this->_pdo_platform->getFromSQLTypeMappings();
                  $row['data_type']=$mapping[$row['data_type']];
                  $rows[] = $row;
        }
        return $rows;
      }
      
      
      public function get_primary_columns_by_table($table){
        
        $query=$this->_pdo_platform->getPrimaryTableColumnsSQL($table,$this->_database);
        $statement=$this->_pdo->prepare($query);
        $this->execute_query($statement);
        $rows = array();
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
                  $rows[] = $row[0];
        }
        return $rows;
      }
      
      
      public function get_tables(){
        $query=$this->_pdo_platform->getTables();
        $statement=$this->prepare($query);
        $success=$this->execute_query($statement);
        $rows = array();
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
                  $rows[] = $row[0];
        }
        return $rows;
      }

        
       private function getPDOType( $type )
      {
        switch ($type){
          case "int":
          case 'bigint':
          case 'smallint':
          return PDO::PARAM_INT;
          case "boolean":return PDO::PARAM_BOOL;
          case "null" :    return PDO::PARAM_NULL;
          case 'blob':    return PDO::PARAM_LOB;
          default: return PDO::PARAM_STR;
        }
      }



      

      public function last_insert_id($table,$id_key){
         return $this->_pdo_platform->lastInsertId($this->_pdo,$table,$id_key);
      }
        
        
       public function create_sequence($name,$step=1,$start=1){
          $query=$this->_pdo_platform->createSequence($name,$step,$start);
          $statement=$this->prepare($query);
          $success=$this->execute_query($statement);
          return $success;
        }
        
        
      public function delete_sequence($name){
          $query=$this->_pdo_platform->dropSequence($name);
          $statement=$this->prepare($query);
          $success=$this->execute_query($statement);        
          return $success;
        }





      public function __sleep(){
        return array('_config','_database');
     }
     
     public function __wakeup(){
        //ar_dump($this);       echo "11111";
        $this->init();  
        set_error_handler(array($this,'errorHandler'),E_STRICT );
        register_shutdown_function(array($this,'errorHandler'));
        set_exception_handler(array($this,'exceptionHandler'));
     }
        

    }
    
    
    ?>