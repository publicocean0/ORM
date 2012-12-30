<?php


/**
 * ORM  framework
 * @author Cristian Lorenzetto <opensource.publicocean0@gmail.com>
 */



class PostgreSQLPlatform extends SQLPlatForm
{
    public function getName()
    {
        return 'PostgreSQL';
    }


    
    
     public function getTableColumnsSQL($table,$database)
    {
       return "select column_name,data_type,is_nullable,column_default from information_schema.columns where table_name ='$table' and table_catalog='$database'";
    }
    
    public function getPrimaryTableColumnsSQL($table,$database)
    {
       return "select column_name from information_schema.key_column_usage where table_name ='$table' and table_catalog='$database'";
    }


      public function createSequenceSQL($name,$step=1,$start=1){
         return "CREATE SEQUENCE {$name}_seq INCREMENT $step MINVALUE 1 MAXVALUE 9223372036854775807 START $start CACHE 1";
      }
      
     public function dropSequenceSQL($name){
         return "DROP SEQUENCE {$name}_seq ";
      }
      
      public function getCurrentSequenceSQL($name){
          return "SELECT last_value from \"{$name}_seq\"";
      }


      public function lastInsertId($pdo,$table,$column){  //echo "{$table}_{$column}_seq";
          $s= $pdo->prepare("SELECT last_value from \"{$table}_{$column}_seq\"");
          $r=$s->execute();
          $row=$s->fetch(PDO::FETCH_NUM);
          return $row[0];
      }

      
      public function getIncrementSequenceSQL($name){
          return "SELECT nextval('{$name}_seq')";
      }
      
      public function getExistsSequenceSQL($name){
          return "SELECT 1 FROM pg_class where relname='{$name}_seq'";
      }

      public function getFromSQLTypeMappings()
    {
        return array(
            'smallint' => 'shortint',
            'int2' => 'shortint',
            'serial' => 'int',
            'serial4' => 'int',
            'int' => 'int',
            'int4' => 'int',
            'integer' => 'int',
            'bigserial' => 'longint',
            'serial8' => 'longint',
            'bigint' => 'longint',
            'int8' => 'longint',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'text' => 'text',
            'varchar' => 'string',
            'interval' => 'string',
            '_varchar' => 'string',
            'char' => 'string',
            'bpchar' => 'string',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'timestamptz' => 'datetimetz',
            'time' => 'time',
            'timetz' => 'time',
            'float' => 'float',
            'float4' => 'float',
            'float8' => 'float',
            'double' => 'float',
            'double precision' => 'float',
            'real' => 'float',
            'decimal' => 'decimal',
            'money' => 'decimal',
            'numeric' => 'decimal',
            'year' => 'date',
            'uuid' => 'integer',
            'bytea' => 'blob',
        );
    }


        /**
* {@inheritDoc}
*/
    public function getToSQLTypeMappings()
    {
        return  array(
            'shortint' => 'smallint',
            'int' => 'int',
            'longint' => 'bigint',
            'boolean' => 'bool',
            'text' => 'text',
            'string' => 'varchar',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'timestamptz' => 'datetimetz',
            'time' => 'time',
            'float' => 'float',
            'double' => 'float',
            'decimal' => 'decimal',
            'blob' => 'bytea',
        );

    }



    public function getQuoteCharacter()
    {
        return '"';
    }



    

    
    
         public function convertFromSQLType($type,$value)
    {    
       switch ($type){
       case 'text':
       case 'string':
        if (is_resource($value)) throw new Exception(" data type seams to be a blob");
        return (string)$value;
        break;
        case 'int':
        case 'longint':
        case 'shortint':
        return (int)$value;
        break;
        case 'float':
        return (float)$value;
        break;
        case 'double':
        case 'decimal':
        return (double)$value;
        break;
       case 'blob':
        if (is_resource($value)) {
      
                            $contents = '';
                            fread($value, 1);
                            while (!feof($value)) {
                              $contents .= chr(hexdec(fread($value, 2)));
                            }
                            fclose($value);
                            return $contents;
             }        

       break;
       case 'boolean':
       return $value;
       break;
       case 'datetime':
       case 'timestamp':
              if (is_int($value)) return $value;
              else if(is_string($value)) {
                $d=DateTime::createFromFormat('Y-n-j h:i:s', $value);
                if ($d!==false) return  $d->getTimestamp();
              }
       break;
       case 'date':
              if (is_int($value)) return $value;
              else if(is_string($value)){
                $d=DateTime::createFromFormat('Y-n-j', $value);
                if ($d!==false) return  $d->getTimestamp();
              }
       break;
       case 'time':
              if (is_int($value)) return $value;
              else if(is_string($value)) {
                $d=DateTime::createFromFormat('h:i:s', $value);
                if ($d!==false) return  $d->getTimestamp();
              }
       break;
       case 'timestamptz':
       break;
       }
       throw new Exception("not able to convert data type '$type' from SQL for value '$value'");
    }
    // php has a additional data type 'null'
     public function convertToSQLType($type,$value)
    {   
       switch ($type){
       case 'null': if ($value==null) return null;
       break;
       case 'text':
       case 'string':
            return (string)$value;
            break;
        case 'int':
        case 'longint':
        case 'shortint':
            return (int)$value;
            break;
        case 'float':
            return (float)$value;
            break;
        case 'double':
        case 'decimal':
            return (double)$value;
            break;
       case 'blob':   
             return $value;      
             break;
       case 'boolean':
             if (is_int($value)) return $value==0;
             if (is_bool($value)) return $value;
             if (is_string($value)) { 
               $s=strtolower($value);
               if ($s=='true') return true;
               if ($s=='false') return false;
             }
             break;
       case 'datetime':
       case 'timestamp': 
              if (is_int($value)) return date('j-n-Y h:i:s',$value);
              else if (is_string($value)) return $value;
              break;
       case 'date':
              if (is_int($value)) return date('j-n-Y',$value);
               else if (is_string($value)) return $value;
              break;
       case 'time':
              if (is_int($value)) return date('h:i:s',$value);
               else if (is_string($value)) return $value;
       break;
       case 'timestamptz':
            break;
       }
       throw new Exception("not able to convert data type '$type' to SQL for value '$value'");
    }





    public function getCreateDatabaseSQL($name)
    {
        return 'CREATE DATABASE ' . $name;
    }
    
    
      public function getDeleteDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
    }
    
    
    public function getDeleteTableSQL($name)
    {
        return 'DROP TABLE ' . $name;
    }
    
    public function getExistsTableSQL($name)
    {
        return "select count(column_name) as c from information_schema.columns where table_name ='$name'";
    }
    
    public function getTables($catalog='public'){
        return "select table_name from information_schema.tables where table_schema ='$catalog'";
    }

   
       public function getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['primary_key']) && ! empty($options['primary_key'])) {
            $keyColumns = array_unique(array_values($options['primary_key']));
            $queryFields .= ', CONSTRAINT '.$tableName.'_'.implode('_', $keyColumns).' PRIMARY KEY(' . implode(', ', $keyColumns) . ')';
        }
        

        if (isset($options['foreign_key']) && ! empty($options['foreign_key'])) {
            $keyColumns = array_values($options['foreign_key']);
            
            $queryFields .= ', CONSTRAINT '.$tableName.'_'.implode('_', $keyColumns[0]).' FOREIGN KEY(' . implode(', ', $keyColumns[0]) . ') REFERENCES "'.$keyColumns[1].'" (' . implode(', ', $keyColumns[2]) . ')';
        }
        
        if (isset($options['unique']) && ! empty($options['unique'])) {
            $keyColumns = array_unique(array_values($options['unique']));
            $queryFields .= ', CONSTRAINT '.$tableName.'_'.implode('_', $keyColumns).' UNIQUE (' . implode(', ', $keyColumns) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $queryFields . ')';

        $sql[] = $query;

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index) {
                $sqlindex = $this->getCreateIndexSQL($index, $tableName);
            }
        }
        




        return join(" ", $sql);
    }

     public function getColumnDeclarationSQL($name, array $field)
    {

        $default = $this->getDefaultValueDeclarationSQL($field);

        $notnull = (isset($field['notnull']) && $field['notnull']) ? ' NOT NULL' : '';
        if (isset($field['autoincrement']) && $field['autoincrement'] ) {
         switch ($field['type']){
          case 'shortint':
          case 'int': $typeDecl='serial'; break;
          case 'longint': $typeDecl='bigserial'; break;
          default: 
           $mapping=$this->getToSQLTypeMappings();
           $typeDecl=$mapping[$field['type']];
         }
        } else {           
           $mapping=$this->getToSQLTypeMappings();
           $typeDecl=$mapping[$field['type']];
        }

        $columnDef = $typeDecl . $default . $notnull  ;



        return $name . ' ' . $columnDef;
    }


}
