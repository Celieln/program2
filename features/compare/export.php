<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

checkLogin();

$batchId =
trim(
    $_GET['batch_id']
    ?? ''
);

if(
    empty($batchId)
)
{
    die(
        'Batch ID tidak ditemukan'
    );
}

$jobQuery =
mysqli_query(
$conn,
"
SELECT *
FROM compare_jobs
WHERE batch_id='"
.
mysqli_real_escape_string(
    $conn,
    $batchId
)
.
"'
LIMIT 1
"
);

if(
    mysqli_num_rows(
        $jobQuery
    ) == 0
)
{
    die(
        'Compare job tidak ditemukan'
    );
}

$job =
mysqli_fetch_assoc(
    $jobQuery
);

$query =
mysqli_query(
$conn,
"
SELECT *
FROM compare_rows
WHERE batch_id='"
.
mysqli_real_escape_string(
    $conn,
    $batchId
)
.
"'
AND row_status!='deleted'
ORDER BY source_row ASC
"
);

if(
    mysqli_num_rows(
        $query
    ) == 0
)
{
    die(
        'Tidak ada data export'
    );
}

$rows = [];

while(
    $row =
    mysqli_fetch_assoc(
        $query
    )
)
{
    $json =
    json_decode(
        $row['row_json'],
        true
    );

    if(
        is_array(
            $json
        )
    )
    {
        $rows[] =
        $json;
    }
}

if(
    empty(
        $rows
    )
)
{
    die(
        'Data kosong'
    );
}

$exportColumns =
$_SESSION['compare_export_columns']
?? [];

if(
    !empty(
        $exportColumns
    )
)
{
    $headers =
    $exportColumns;
}
else
{
    $headers =
    array_keys(
        $rows[0]
    );
}

$totalRows =
count(
    $rows
);

$totalDuplicate =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM compare_duplicates
WHERE batch_id='$batchId'
"
)
)[0];

$totalEdited =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM compare_rows
WHERE batch_id='$batchId'
AND row_status='edited'
"
)
)[0];

$totalDeleted =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM compare_rows
WHERE batch_id='$batchId'
AND row_status='deleted'
"
)
)[0];

$totalMaster =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM compare_duplicates
WHERE batch_id='$batchId'
AND compare_type='duplicate_master'
"
)
)[0];

$totalInternal =
mysqli_fetch_row(
mysqli_query(
$conn,
"
SELECT COUNT(*)
FROM compare_duplicates
WHERE batch_id='$batchId'
AND compare_type='duplicate_internal'
"
)
)[0];

$spreadsheet =
new Spreadsheet();

$summarySheet =
$spreadsheet
->getActiveSheet();

$summarySheet
->setTitle(
    'Summary'
);

$summarySheet
->setCellValue(
    'A1',
    'COMPARE EXPORT SUMMARY'
);

$summarySheet
->setCellValue(
    'A3',
    'Batch ID'
);

$summarySheet
->setCellValue(
    'B3',
    $batchId
);

$summarySheet
->setCellValue(
    'A4',
    'Master File'
);

$summarySheet
->setCellValue(
    'B4',
    $job['file_a']
);

$summarySheet
->setCellValue(
    'A5',
    'Target File'
);

$summarySheet
->setCellValue(
    'B5',
    $job['file_b']
);

$summarySheet
->setCellValue(
    'A7',
    'Total Export Row'
);

$summarySheet
->setCellValue(
    'B7',
    $totalRows
);

$summarySheet
->setCellValue(
    'A8',
    'Total Duplicate'
);

$summarySheet
->setCellValue(
    'B8',
    $totalDuplicate
);

$summarySheet
->setCellValue(
    'A9',
    'Duplicate Master'
);

$summarySheet
->setCellValue(
    'B9',
    $totalMaster
);

$summarySheet
->setCellValue(
    'A10',
    'Duplicate Internal'
);

$summarySheet
->setCellValue(
    'B10',
    $totalInternal
);

$summarySheet
->setCellValue(
    'A11',
    'Edited Row'
);

$summarySheet
->setCellValue(
    'B11',
    $totalEdited
);

$summarySheet
->setCellValue(
    'A12',
    'Deleted Row'
);

$summarySheet
->setCellValue(
    'B12',
    $totalDeleted
);

$summarySheet
->setCellValue(
    'A13',
    'Export Time'
);

$summarySheet
->setCellValue(
    'B13',
    date(
        'Y-m-d H:i:s'
    )
);

$summarySheet
->getStyle(
    'A1'
)
->getFont()
->setBold(
    true
)
->setSize(
    16
);

$dataSheet =
$spreadsheet
->createSheet();

$dataSheet
->setTitle(
    'Clean Data'
);

$columnLetter = 'A';

foreach(
    $headers
    as
    $header
)
{
    $dataSheet
    ->setCellValue(
        $columnLetter.'1',
        $header
    );

    $columnLetter++;
}

$dataSheet
->getStyle(
    'A1:'
    .
    $dataSheet->getHighestColumn()
    .
    '1'
)
->getFont()
->setBold(
    true
);

$rowNumber = 2;

foreach(
    $rows
    as
    $rowData
)
{
    $columnLetter = 'A';

    foreach(
        $headers
        as
        $header
    )
    {
        $dataSheet
        ->setCellValue(
            $columnLetter.$rowNumber,
            $rowData[$header]
            ?? ''
        );

        $columnLetter++;
    }

    $rowNumber++;
}

foreach(
    range(
        'A',
        $dataSheet->getHighestColumn()
    )
    as
    $column
)
{
    $dataSheet
    ->getColumnDimension(
        $column
    )
    ->setAutoSize(
        true
    );
}

$dataSheet
->freezePane(
    'A2'
);

$dataSheet
->setAutoFilter(
    'A1:'
    .
    $dataSheet->getHighestColumn()
    .
    '1'
);

$spreadsheet
->setActiveSheetIndex(
    1
);

$fileName =
'CLEAN_DATA_'
.
date(
    'Ymd_His'
)
.
'.xlsx';

header(
'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
'Content-Disposition: attachment; filename="'.$fileName.'"'
);

header(
'Cache-Control: max-age=0'
);

$writer =
new Xlsx(
    $spreadsheet
);

$writer->save(
    'php://output'
);

exit;