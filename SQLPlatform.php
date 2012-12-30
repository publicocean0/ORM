<?php

 /**
 * ORM  framework
 * @author Cristian Lorenzetto <opensource.publicocean0@gmail.com>
 */


abstract class SQLPlatform
{
    abstract public function convertFromSQLType($type,$value);
    abstract public function convertToSQLType($type,$value);
    abstract public function createSequenceSQL($name,$step=1,$start=1);
    abstract public function dropSequenceSQL($name);
    abstract  public function getCurrentSequenceSQL($name);


    abstract  public function lastInsertId($pdo,$table,$column);


    abstract  public function getIncrementSequenceSQL($name);

    abstract  public function getExistsSequenceSQL($name);

    public function getColumnDeclarationListSQL(array $fields)
    {
        $queryFields = array();

        foreach ($fields as $fieldName => $field) {

            $queryFields[] = $this->getColumnDeclarationSQL($fieldName, $field);
        }

        return implode(', ', $queryFields);
    }
    
    /**
     * Obtain DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $field      field definition array
     *
     * @return string           DBMS specific SQL code portion needed to set a default value
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        $default = empty($field['notnull']) ? ' DEFAULT NULL' : '';

        if (isset($field['default'])) {
            $default = " DEFAULT '".$field['default']."'";
        }
        return $default;
    }
    
        /**
     * Obtain DBMS specific SQL code portion needed to declare a generic type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param array  $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     *      charset
     *          Text value with the default CHARACTER SET for this field.
     *      collation
     *          Text value with the default COLLATION for this field.
     *      unique
     *          unique constraint
     *      check
     *          column check constraint
     *      columnDefinition
     *          a string that defines the complete column
     *
     * @return string  DBMS specific SQL code portion that should be used to declare the column.
     */
    public function getColumnDeclarationSQL($name, array $field)
    {

        $default = $this->getDefaultValueDeclarationSQL($field);

        $notnull = (isset($field['notnull']) && $field['notnull']) ? ' NOT NULL' : '';
        $mapping=$this->getToSQLTypeMappings();
        $typeDecl = $mapping[$field['type']];

        $columnDef = $typeDecl . $default . $notnull  ;



        return $name . ' ' . $columnDef;
    }
    
    



  abstract  public function getCreateTableSQL($tableName, array $columns, array $options = array());

  abstract public function getCreateDatabaseSQL($name);


  abstract public function getDeleteDatabaseSQL($name);


  abstract public function getDeleteTableSQL($name);

   abstract public function getQuoteCharacter();



   
   abstract  public function getTableColumnsSQL($table,$database);
   
   abstract  public function getPrimaryTableColumnsSQL($table,$database);

    /**
     * getIndexFieldDeclarationList
     * Obtain DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $fields
     *
     * @return string
     */
    public function getIndexFieldDeclarationListSQL(array $fields)
    {
        $ret = array();

        foreach ($fields as $field => $definition) {
            if (is_array($definition)) {
                $ret[] = $field;
            } else {
                $ret[] = $definition;
            }
        }

        return implode(', ', $ret);
    }
    




    /**
     * Name of this keyword list.
     *
     * @return string
     */
    abstract public function getName();
}
