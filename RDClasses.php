<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RDClasses
 *
 * @author sushilkamble
 */
class Product {
    //put your code here
    var $dbConn;
    var $id;
    var $sku;
    var $description;
    var $taxable;
    var $category;
    
    function __constrct($mysqli, $upc, $desc){
        $this->dbConn = $mysqli;
        $this->sku = $upc;
        $this->description = $desc;
    }
    
    function isNew(){
        
    }
    
    function save(){
        $this->id = $this->getProductId($this->dbConn, $this->sku);
        if($this->id === ""){
            createNewProduct($this->dbConn, $this->sku, trim($this->description));
            $productId = $this->dbConn->insert_id;
        }
    }
    
    function getProductId($mysqli, $upc){
        $productId = "";
        $sql = "SELECT id from t_product where sku = '$upc'";
        $results = $mysqli->query($sql);
        if($results->num_rows > 0) {
            $row = $results->fetch_assoc ();
            $productId = $row['id'];
        }      

        return $productId;
    }
    
}
