<?php
class ORMException extends Exception {
private $query;

public function getQuery(){ return $this->query; }



public function __construct($message,$query){
  parent::__construct($message);
  $this->query=$query;
}


}
?>

