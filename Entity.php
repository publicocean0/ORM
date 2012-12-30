<?php

/**
 * ORM  framework
 * @author Cristian Lorenzetto <opensource.publicocean0@gmail.com>
 */

 class Entity  implements Serializable
 {
  private $data;
  private $statement;
  private $is_new;

  public function __construct(Statement $statement,array $c=array(),$new=true){
  $this->data=$c;
  $this->is_new=$new;
  $this->statement=$statement;
  }

   public function is_new(){
    return $this->is_new;
   }
   public function serialize() {
        return $this->data;
    }
    public function unserialize($data) {
        $this->data = $data;
    }

    public function as_array() {
        return $this->data;
    }

     public function __get($key) {
            return $this->data[$key];
        }

    public function __set($key, $value) {
          $exist=isset($this->data[$key]);
          if (($exist && $value!=$this->data[$key]) || !$exist) $this->statement->set_dirty_field($id,$key);
          $this->data[$key]=$value;
      }

    public function __isset($key) {
          return isset($this->data[$key]);
    }
    
    public function id() {
        return $this->data[$this->statement->id_column()];
    }


   public function save(){
      if ($this->is_new) { 
        $id=$this->statement->insert($this->data);
        if ($id===false)  $this->is_new=false;
        else  $this->data[$this->statement->id_column()]=$id;
        return !$this->is_new;
      } else  {
         $id=$this->statement->id_column();
         $dirty_fields=$this->statement->get_dirty_fields($this->data[$id]);
         $this->statement->where_id_is($this->data[$this->statement->id_column()])->update($this->data);
      }
   }
   
   public function delete(){
      if ($this->statement->where_id_is($this->data[$this->statement->id_column()])->delete())  $this->is_new=true;
   }
   

    public function  __toString(){
    return  json_encode($this->data);
    }

 }

         

?>