<?php
/*****************************************************************************
* xls_template.php
*
* When provided with a publisher title (via the publisher query parameter)
* this script will create the inventory template XLS where one of the columns
* contains cells with a drop-down.  The drop-down will be validated against
* the first column in a second sheet, which contains all of the child
* publishers for the one provided.
*
* The Apache configuration must use SetEnv to set CKAN_CONFIG_FILE to point
* to the location of the CKAN configuration file.
*
*****************************************************************************/
error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('Europe/London');

/**
* If the required query parameter is not supplied we should just raise a 404.
**/
if (!isset($_GET["publisher"])) {
    header('HTTP/1.0 404 Not Found');
    echo "<h1>404 Not Found</h1>";
    echo "The page that you have requested could not be found.";
    exit();
}

$publisher =  $_GET["publisher"] or die();


/**
* Builds a DB connection string from the settings in the CKAN config. The location
* of the config file is found by reading the CKAN_CONFIG_FILE env var set by
* apache.
**/
function get_db_connection_string() {
    $config_file = getenv('CKAN_CONFIG_FILE') or die('CKAN_CONFIG_FILE not set');

    // Read the config file and find the postgresql connection string, parsing it
    // for the relevant information.
    $regex = "/sqlalchemy.url = postgresql:\/\/(.*)\:(.*)\@(.*):(.*)\/(.*)/";
    $match = preg_match_all ($regex, file_get_contents($config_file), $config);
    $uname    = $config[1][0];
    $password = $config[2][0];
    $host     = $config[3][0];
    $port     = $config[4][0];
    $db       = $config[5][0];

    return "host={$host} dbname={$db} user=${uname} password=${password} port=${port}";
}


/**
* For the provided publisher name this function will connect to the CKAN
* database and perform a recursive query in order to get the child groups.
**/
function get_subpublishers_for($name) {

    $CTE_QUERY = <<<EOT
        WITH RECURSIVE subtree(id) AS (
            SELECT M.* FROM public.member AS M
            WHERE M.table_name = 'group' AND M.state = 'active'
            UNION
            SELECT M.* FROM public.member M, subtree SG
            WHERE M.table_id = SG.group_id AND M.table_name = 'group' AND M.state = 'active')

        SELECT G.title FROM subtree AS ST
        INNER JOIN public.group G ON G.id = ST.table_id
        WHERE group_id = (select id from public.group where title=$1) AND G.type = 'publisher' and table_name='group' and G.state='active'
        ORDER BY G.name
EOT;

    // Start with the current title
    $publishers = array( $name );
    $dbconn = pg_connect(get_db_connection_string())
           or die('Could not connect: ' . pg_last_error());

    $result = pg_prepare($dbconn, "cte", $CTE_QUERY);
    $result = pg_execute($dbconn, "cte", array($name));

    while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        foreach ($line as $col_value) {
            array_push($publishers, $col_value);
        }
    }
    pg_free_result($result);
    pg_close($dbconn);
    return $publishers;
}

// PHPExcel is from http://phpexcel.codeplex.com/releases/view/107442
include 'Classes/PHPExcel.php';
include 'Classes/PHPExcel/Writer/Excel2007.php';


/*
  Create a new spreadsheet and set appropriate meta-data for the spreadsheet
*/
$excel = new PHPExcel();
$excel->getProperties()->setCreator("data.gov.uk")
                       ->setLastModifiedBy("data.gov.uk")
                       ->setTitle("Inventory template for " . $publisher)
                       ->setDescription("Template for inventory upload of {$publisher} inventory");


/*
  Set the header row for the spreadsheet
*/
$excel->setActiveSheetIndex(0);
$excel->getActiveSheet()->setTitle('Inventory')
                        ->SetCellValue('A1', 'Title')
                        ->SetCellValue('B1', 'Description')
                        ->SetCellValue('C1', 'Owner')
                        ->SetCellValue('D1', 'Date to be published');


/*
  Create a new sheet called Publisher
*/
$publisher_sheet = $excel->createSheet();
$publisher_sheet->setTitle('Publishers');

/*
  Fetch the list of sub-departments underneath the provided publisher and
  add them to the newly created second sheet which will then act as validation
*/
$subpublishers = get_subpublishers_for($publisher);
for ($i = 0; $i <= count($subpublishers) - 1; $i++) {
    $pos = $i + 1;
    $publisher_sheet->SetCellValue("A{$pos}", $subpublishers[$i] );
}

/*
  Build the formula that was be used by validation to point to the
  items we just added to the Publishers sheet
*/
$formula = 'Publishers!$A$1:$A$'.strval(count($subpublishers));

/* We can't set a drop down on a column, only each cell within the column, so we've
   picked 1000 as a reasonably large number. */
$excel->setActiveSheetIndex(0);
for ($i = 1; $i <=1000; $i++) {
    $validation = $excel->getActiveSheet()->getCell("C{$i}")->getDataValidation();
    $validation->setType( PHPExcel_Cell_DataValidation::TYPE_LIST )
               ->setErrorStyle( PHPExcel_Cell_DataValidation::STYLE_INFORMATION )
               ->setAllowBlank(true)
               ->setShowInputMessage(false)
               ->setShowErrorMessage(false)
               ->setShowDropDown(true)
               ->setError('Value is not in list.')
               ->setPromptTitle('Pick from list')
               ->setPrompt('Please pick a value from the drop-down list.')
               ->setFormula1($formula);
}

// Header setup for response
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/vnd.ms-excel');
header("Content-Disposition: attachment;filename='inventory_template.xls'");

// Save as an Excel 5 file directly to the response stream
$writer = new PHPExcel_Writer_Excel5($excel);
$writer->save("php://output");

?>