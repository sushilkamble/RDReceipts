<?php
/****************
 * 
 * 
 * [0] == "Restaurant Depot 136" = skip the element
 * 
 * [0] == "nbsp" then 
 * if [1] == 'Sub-Total' then [4] is amount
 * if [1] == 'Tax' then [4] is amount
 * if [1] == 'Total' then [4] is amount
 * skip the element
 * 
 * [0] == "Invoice 855" then get extract invoice number from [0] and 
 * [1] contains date time [1] = "Terminal 16 - 7/2/2016 9:34 AM".  
 * Extract the date and time
 * 
 * [0] == "UPC" then skip the element
 * 
 * [0] == [0] is UPC, [1] is Description [2] Unit Price [3] = Qty and [4] extended price
 * 
 * If [4] == "($5.00)" then it's negative amount
 * 
 */
$INVOICELINE = "/Invoice/";
$TAXLINE = "/Tax/";
$SUBTOTAL = "/Sub-Total/";
$TOTAL = "/Total/";
$UPCLINE = "/UPC/";
$BLANKLINE = "/nbsp/";
$RDLINE = "/Depot 136 - Sacramento/";

$SERVER_IP = "127.0.0.1";
$SERVER_PORT = "3307";
$DB_USER = "apache";
$DB_PASSWD = "firechicken";
$SEED_ACCOUNT = "pizza";

// Connect to the databse
$mysqli = new mysqli ( $SERVER_IP, $DB_USER, $DB_PASSWD, $SEED_ACCOUNT,$SERVER_PORT );
if ($mysqli->connect_errno) {
	echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

$dir = "/Users/sushilkamble/Downloads/Pizza/RD-Receipts/2016/";

if(!isset($argv[1])){ 
    die ("Please give input filename\n");
}

$filename = $dir . $argv[1];

if(!file_exists($filename)){
    die ("File does not exists $filename\n");
}

$xml=simplexml_load_file($filename) or die("Error: Cannot create object");
$count = count($xml->tr);

$invoiceDate = "";
$tax = 0;
$subTotal = 0;
$total = 0;
$invoiceId = -1;

for($i=0; $i < $count; $i++){
    $elem0 = $xml->tr[$i]->td[0];
    $elem1 = $xml->tr[$i]->td[1];
    $elem2 = $xml->tr[$i]->td[2];
    $elem3 = $xml->tr[$i]->td[3];
    $elem4 = $xml->tr[$i]->td[4];

    // check if RD Line
    if(preg_match($RDLINE, $elem0) || preg_match($UPCLINE, $elem0)){
      // do nothing
      next;
    } elseif (preg_match($INVOICELINE, $elem0)) {

        $invoiceDate = extractInvDate($elem1);
        $invoiceId = insertNewInvoice($mysqli, $invoiceDate);

    } elseif (preg_match($BLANKLINE, $elem0)){

        if(preg_match($TAXLINE, $elem1)) {            

            $tax = removeDollarSign($elem4);

            } elseif(preg_match($SUBTOTAL, $elem1)) {

            $subTotal = removeDollarSign($elem4);
            
        } elseif(preg_match($TOTAL, $elem1)) {

            $total = removeDollarSign($elem4);
            updateInvoice($mysqli, $invoiceId, $invoiceDate, $subTotal, $tax, $total);

        }
    } else{
        $productId = checkProductExists($mysqli, $elem0);
        if($productId === ""){
            insertNewProduct($mysqli, $elem0, trim($elem1));
            $productId = $mysqli->insert_id;
        }
        
        $unitPrice = removeDollarSign($elem2);
        $qty = $elem3;
        print "$elem4\n";
        $extPrice = removeDollarSign($elem4);
        // handle returns
        if($qty < 0 ){
            $extPrice = -1* $extPrice;
        }
        insertNewItemPurchase($mysqli, $invoiceId, $elem0, $qty, $unitPrice, $extPrice);
    }
}
$mysqli->close();

function extractInvDate($invoiceLine){    
    #format of the line "Terminal 16 - 7/2/2016 9:34 AM"
    $date = trim(substr($invoiceLine, strpos($invoiceLine, " - ") + 3));
    return $date;
}

function removeDollarSign($amount){
    $amount = trim($amount);
    if(preg_match('/\(/', $amount)){
        $amount = str_replace('(', '', $amount);
        $amount = str_replace(')', '', $amount);
        $negative = TRUE;
    }
    if(preg_match('/$/', $amount)){
        $amount = (double)  str_replace('$','', $amount);
    }
    
    if(is_nan($amount)){
        print "Not a number: $amount\n";
    }
     
    return $amount;
}


function insertNewItemPurchase($mysqli, $invoiceId, $sku, $qty, $unitPrice, $extPrice){
    $itemId = -1;
    $sql = "INSERT INTO t_invoice (invoice_id, sku, quantity, unit_price, ext_price) 
            VALUES ($invoiceId, '$sku', $qty, $unitPrice, $extPrice)";
    if($mysqli->query($sql) === TRUE){
        $itemId = $mysqli->insert_id; 
    }else {
        print "Could not create record for $sku\n$sql\n";
        print_dberror($mysqli);
    }
    return $itemId;
    
}

function checkProductExists($mysqli, $upc){
    $productId = "";
    $sql = "SELECT id from t_product where sku = '$upc'";
    $results = $mysqli->query($sql);
    if($results->num_rows > 0) {
        $row = $results->fetch_assoc ();
        $productId = $row['id'];
    }      
   
    return $productId;
}

function insertNewProduct($mysqli, $sku, $desc){
    $productId = -1;
    $sql = "INSERT INTO t_product (sku, description) 
            VALUES ('$sku', '$desc')";
    if($mysqli->query($sql) === TRUE){
        $productId = $mysqli->insert_id; 
    }else {
        print "Could not create record for $sku\n$sql\n";
        print_dberror($mysqli);
    }
    return $productId;
}


function insertNewInvoice($mysqli, $invoiceDate){
    $invoiceId = -1;
    $sql = "INSERT INTO t_invoice_summary (invoice_dt) 
            VALUES (STR_TO_DATE('$invoiceDate', '%m/%d/%Y %h:%i %p'))";
    if($mysqli->query($sql) === TRUE){
        $invoiceId = $mysqli->insert_id; 
    }else {
        print "Could not create record for $invoiceDate\n$sql\n";
        print_dberror($mysqli);
    }
    return $invoiceId;
}

function updateInvoice($mysqli, $invoiceId, $invoiceDate, $subTotal, $tax, $total){
    $sql = "Update t_invoice_summary "
            . "set subtotal = $subTotal,"
                . " tax = $tax, "
                    . "total = $total "
            . "WHERE id = $invoiceId";
    if($mysqli->query($sql) === FALSE){
        print "Could not update record for $invoiceId\n$sql\n";
        print_dberror($mysqli);
    }
    
}

// function to print db errors
function print_dberror($mysqli) {
	printf ( "[%d] %s\n", $mysqli->errno, $mysqli->error );
}

?>

