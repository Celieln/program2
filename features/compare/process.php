<?php

session_start();

require '../../config/database.php';
require '../../functions/auth.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

checkLogin();

if(
    !isset($_SESSION['compare_file_a'])
    ||
    !isset($_SESSION['compare_file_b'])
    ||
    !isset($_SESSION['compare_mapping'])
    ||
    !isset($_SESSION['compare_keys'])
)
{
    header('Location: upload_a.php');
    exit;
}

set_time_limit(0);
ini_set('memory_limit', '1024M');

$fileA = $_SESSION['compare_file_a'];
$fileB = $_SESSION['compare_file_b'];
$mapping = $_SESSION['compare_mapping'];
$compareKeys = $_SESSION['compare_keys'];
$compareOptions = $_SESSION['compare_options'] ?? [
    'duplicate_internal',
    'compare_master',
    'nik_mismatch',
    'name_mismatch',
    'not_found'
];

$batchId = 'COMPARE_' . date('YmdHis') . '_' . uniqid();

class CompareChunkReadFilter implements IReadFilter
{
    private int $startRow = 1;
    private int $endRow = 1;

    public function setRows(int $startRow, int $endRow): void
    {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}

function compareNormalizeValue($value): string
{
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function compareWorksheetInfo(string $file): array
{
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);

    $info = $reader->listWorksheetInfo($file);

    return $info[0] ?? [];
}

function compareLoadHeaderRow(string $file, ?array $info = null): array
{
    $info = $info ?? compareWorksheetInfo($file);
    $totalColumns = (int)($info['totalColumns'] ?? 0);

    if($totalColumns < 1)
    {
        return [];
    }

    $highestColumn = Coordinate::stringFromColumnIndex($totalColumns);

    $filter = new CompareChunkReadFilter();
    $filter->setRows(1, 1);

    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $reader->setReadFilter($filter);

    $spreadsheet = $reader->load($file);
    $sheet = $spreadsheet->getActiveSheet();

    $row = $sheet->rangeToArray(
        'A1:' . $highestColumn . '1',
        null,
        true,
        true,
        false
    );

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $row[0] ?? [];
}

function compareLoadRowsChunk(
    string $file,
    int $startRow,
    int $endRow,
    int $columnCount
): array
{
    if($endRow < $startRow || $columnCount < 1)
    {
        return [];
    }

    $highestColumn = Coordinate::stringFromColumnIndex($columnCount);

    $filter = new CompareChunkReadFilter();
    $filter->setRows($startRow, $endRow);

    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $reader->setReadFilter($filter);

    $spreadsheet = $reader->load($file);
    $sheet = $spreadsheet->getActiveSheet();

    $rows = $sheet->rangeToArray(
        'A' . $startRow . ':' . $highestColumn . $endRow,
        null,
        true,
        true,
        false
    );

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $rows ?: [];
}

function compareIsRowEmpty(array $row): bool
{
    foreach($row as $value)
    {
        if(trim((string)$value) !== '')
        {
            return false;
        }
    }
    return true;
}

function compareBuildHeaderIndex(array $header): array
{
    $index = [];

    foreach($header as $offset => $column)
    {
        $column = trim((string)$column);

        if($column === '')
        {
            continue;
        }

        $index[$column] = $offset;
    }

    return $index;
}

function compareBuildRowAssoc(array $header, array $row): array
{
    $assoc = [];

    foreach($header as $offset => $column)
    {
        $column = trim((string)$column);

        if($column === '')
        {
            continue;
        }

        $assoc[$column] = $row[$offset] ?? '';
    }

    return $assoc;
}

function compareBuildCompositeValue(array $row, array $offsetMap): string
{
    $parts = [];

    foreach($offsetMap as $offset)
    {
        $parts[] = compareNormalizeValue($row[$offset] ?? '');
    }

    $value = implode('|', $parts);

    if(trim($value, '|') === '')
    {
        return '';
    }

    return $value;
}

function compareHashValue(string $value): string
{
    $value = trim($value);

    if($value === '')
    {
        return '';
    }

    return md5($value);
}

function compareFindFieldByPatterns(array $fields, array $patterns): ?string
{
    foreach($fields as $field)
    {
        $normField = compareNormalizeValue($field);

        foreach($patterns as $pattern)
        {
            $normPattern = compareNormalizeValue($pattern);

            if($normPattern !== '' && strpos($normField, $normPattern) !== false)
            {
                return $field;
            }
        }
    }

    return null;
}

function compareBulkInsertRows(mysqli $conn, string $batchId, array $rows): int
{
    if(empty($rows))
    {
        return 0;
    }

    $batchIdEsc = mysqli_real_escape_string($conn, $batchId);
    $values = [];

    foreach($rows as $row)
    {
        $values[] =
            "('{$batchIdEsc}','"
            . (int)$row['source_row']
            . "','"
            . mysqli_real_escape_string($conn, $row['row_json'])
            . "','normal')";
    }

    $sql =
        "INSERT INTO compare_rows
        (batch_id, source_row, row_json, row_status)
        VALUES "
        . implode(',', $values);

    if(!mysqli_query($conn, $sql))
    {
        return 0;
    }

    return (int)mysqli_insert_id($conn);
}

function compareBulkInsertDuplicates(mysqli $conn, string $batchId, array $rows): bool
{
    if(empty($rows))
    {
        return true;
    }

    $batchIdEsc = mysqli_real_escape_string($conn, $batchId);
    $values = [];

    foreach($rows as $row)
    {
        $values[] =
            "('{$batchIdEsc}',"
            . (int)$row['compare_row_id']
            . ",'"
            . mysqli_real_escape_string($conn, $row['compare_type'])
            . "','"
            . mysqli_real_escape_string($conn, $row['duplicate_value'])
            . "','"
            . mysqli_real_escape_string($conn, $row['duplicate_column'])
            . "',"
            . (int)$row['file_a_row']
            . ","
            . (int)$row['file_b_row']
            . ",'"
            . mysqli_real_escape_string($conn, $row['file_a_json'])
            . "','"
            . mysqli_real_escape_string($conn, $row['file_b_json'])
            . "','duplicate')";
    }

    $sql =
        "INSERT INTO compare_duplicates
        (batch_id, compare_row_id, compare_type, duplicate_value, duplicate_column, file_a_row, file_b_row, file_a_json, file_b_json, status)
        VALUES "
        . implode(',', $values);

    return (bool)mysqli_query($conn, $sql);
}

function compareBatchUpdateDuplicateRows(mysqli $conn, array $ids): bool
{
    $ids = array_values(
        array_unique(
            array_map(
                'intval',
                $ids
            )
        )
    );

    if(empty($ids))
    {
        return true;
    }

    foreach(array_chunk($ids, 1000) as $chunk)
    {
        $in = implode(',', $chunk);
        $sql = "UPDATE compare_rows SET row_status='duplicate' WHERE id IN ($in)";

        if(!mysqli_query($conn, $sql))
        {
            return false;
        }
    }

    return true;
}

function compareFetchRowJsonByIdCached(mysqli $conn, int $rowId): string
{
    static $cache = [];

    if($rowId <= 0)
    {
        return '{}';
    }

    if(isset($cache[$rowId]))
    {
        return $cache[$rowId];
    }

    $rowId = (int)$rowId;
    $result = mysqli_query(
        $conn,
        "SELECT row_json FROM compare_rows WHERE id=$rowId LIMIT 1"
    );

    if($result && $row = mysqli_fetch_assoc($result))
    {
        $cache[$rowId] = (string)($row['row_json'] ?? '{}');
        return $cache[$rowId];
    }

    return '{}';
}

$masterInfo = compareWorksheetInfo($fileA);
$targetInfo = compareWorksheetInfo($fileB);

$headerA = compareLoadHeaderRow($fileA, $masterInfo);
$headerB = compareLoadHeaderRow($fileB, $targetInfo);

if(count($headerA) < 1 || count($headerB) < 1)
{
    die('Data kosong');
}

$headerAIndex = compareBuildHeaderIndex($headerA);
$headerBIndex = compareBuildHeaderIndex($headerB);

$masterCompareIndexMap = [];
$targetCompareIndexMap = [];
$reverseMapping = [];

foreach($mapping as $headerBName => $headerAName)
{
    $headerBName = trim((string)$headerBName);
    $headerAName = trim((string)$headerAName);

    if($headerAName !== '' && !isset($reverseMapping[$headerAName]))
    {
        $reverseMapping[$headerAName] = $headerBName;
    }
}

foreach($compareKeys as $compareField)
{
    if(isset($headerAIndex[$compareField]))
    {
        $masterCompareIndexMap[$compareField] = $headerAIndex[$compareField];
    }

    if(isset($reverseMapping[$compareField]) && isset($headerBIndex[$reverseMapping[$compareField]]))
    {
        $targetCompareIndexMap[$compareField] = $headerBIndex[$reverseMapping[$compareField]];
    }
}

if(
    empty($masterCompareIndexMap)
    ||
    empty($targetCompareIndexMap)
)
{
    die('Mapping compare key tidak lengkap');
}

$primaryNikField = compareFindFieldByPatterns(
    $compareKeys,
    [
        'NIK',
        'NO KTP',
        'NO IDENTITAS',
        'NO ID'
    ]
);

$primaryNameField = compareFindFieldByPatterns(
    $compareKeys,
    [
        'NAMA',
        'NAMA LENGKAP',
        'FULL NAME',
        'FULLNAME'
    ]
);

$primaryNikMasterOffset = ($primaryNikField !== null && isset($masterCompareIndexMap[$primaryNikField]))
    ? $masterCompareIndexMap[$primaryNikField]
    : null;

$primaryNameMasterOffset = ($primaryNameField !== null && isset($masterCompareIndexMap[$primaryNameField]))
    ? $masterCompareIndexMap[$primaryNameField]
    : null;

$primaryNikTargetOffset = ($primaryNikField !== null && isset($targetCompareIndexMap[$primaryNikField]))
    ? $targetCompareIndexMap[$primaryNikField]
    : null;

$primaryNameTargetOffset = ($primaryNameField !== null && isset($targetCompareIndexMap[$primaryNameField]))
    ? $targetCompareIndexMap[$primaryNameField]
    : null;

$masterExactIndex = [];
$masterNikIndex = [];
$masterNameIndex = [];

$totalA = 0;
$masterTotalRows = (int)($masterInfo['totalRows'] ?? 0);
$masterChunkSize = 1000;

for($chunkStart = 2; $chunkStart <= $masterTotalRows; $chunkStart += $masterChunkSize)
{
    $chunkEnd = min($chunkStart + $masterChunkSize - 1, $masterTotalRows);

    $rowsChunk = compareLoadRowsChunk($fileA, $chunkStart, $chunkEnd, count($headerA));

    foreach($rowsChunk as $offset => $rowArr)
    {
        if(compareIsRowEmpty($rowArr))
        {
            continue;
        }

        $rowIndex = $chunkStart + $offset;
        $totalA++;

        $rowAssocA = compareBuildRowAssoc($headerA, $rowArr);
        $rowJsonA = json_encode($rowAssocA, JSON_UNESCAPED_UNICODE);

        $composite = compareBuildCompositeValue($rowArr, $masterCompareIndexMap);
        $hashKey = compareHashValue($composite);

        if($hashKey !== '')
        {
            $masterExactIndex[$hashKey] = [
                'row' => $rowIndex
            ];
        }

        if($primaryNikMasterOffset !== null)
        {
            $nikValue = compareNormalizeValue($rowArr[$primaryNikMasterOffset] ?? '');

            if($nikValue !== '' && !isset($masterNikIndex[$nikValue]))
            {
                $masterNikIndex[$nikValue] = [
                    'row' => $rowIndex,
                    'row_json' => $rowJsonA
                ];
            }
        }

        if($primaryNameMasterOffset !== null)
        {
            $nameValue = compareNormalizeValue($rowArr[$primaryNameMasterOffset] ?? '');

            if($nameValue !== '' && !isset($masterNameIndex[$nameValue]))
            {
                $masterNameIndex[$nameValue] = [
                    'row' => $rowIndex,
                    'row_json' => $rowJsonA
                ];
            }
        }
    }
}

$targetTotalRows = (int)($targetInfo['totalRows'] ?? 0);
$targetChunkSize = 1000;

$seenTargetExactIndex = [];
$totalB = 0;
$totalDuplicate = 0;

mysqli_begin_transaction($conn);

for($chunkStart = 2; $chunkStart <= $targetTotalRows; $chunkStart += $targetChunkSize)
{
    $chunkEnd = min($chunkStart + $targetChunkSize - 1, $targetTotalRows);

    $rowsChunk = compareLoadRowsChunk($fileB, $chunkStart, $chunkEnd, count($headerB));

    $rowsToInsert = [];

    foreach($rowsChunk as $offset => $rowB)
    {
        if(compareIsRowEmpty($rowB))
        {
            continue;
        }

        $rowNumberB = $chunkStart + $offset;
        $rowAssocB = compareBuildRowAssoc($headerB, $rowB);
        $rowJsonB = json_encode($rowAssocB, JSON_UNESCAPED_UNICODE);

        $rowsToInsert[] = [
            'source_row' => $rowNumberB,
            'row_assoc' => $rowAssocB,
            'row_json' => $rowJsonB,
            'raw_row' => $rowB
        ];
    }

    if(empty($rowsToInsert))
    {
        continue;
    }

    $firstRowId = compareBulkInsertRows($conn, $batchId, $rowsToInsert);

    if($firstRowId <= 0)
    {
        mysqli_rollback($conn);
        die(mysqli_error($conn));
    }

    $duplicateRows = [];
    $duplicateRowIds = [];

    foreach($rowsToInsert as $idx => $item)
    {
        $rowB = $item['raw_row'];
        $rowAssocB = $item['row_assoc'];
        $rowJsonB = $item['row_json'];
        $rowNumberB = (int)$item['source_row'];
        $compareRowId = $firstRowId + $idx;

        $totalB++;

        $composite = compareBuildCompositeValue($rowB, $targetCompareIndexMap);
        $hashKey = compareHashValue($composite);

        $findingType = null;
        $matchedMasterRow = null;
        $matchedInternalRow = null;

        $duplicateInternalEnabled = in_array('duplicate_internal', $compareOptions, true);
        $compareMasterEnabled = in_array('compare_master', $compareOptions, true);
        $nikMismatchEnabled = in_array('nik_mismatch', $compareOptions, true);
        $nameMismatchEnabled = in_array('name_mismatch', $compareOptions, true);
        $notFoundEnabled = in_array('not_found', $compareOptions, true);

        if($hashKey !== '')
        {
            if($duplicateInternalEnabled && isset($seenTargetExactIndex[$hashKey]))
            {
                $matchedInternalRow = $seenTargetExactIndex[$hashKey];
                $findingType = 'duplicate_internal';
            }

            if(
                $findingType === null
                &&
                $compareMasterEnabled
            )
            {
                if(isset($masterExactIndex[$hashKey]))
                {
                    // Exact match with master is SAFE
                    $findingType = null;
                }
                else
                {
                    if(
                        $nikMismatchEnabled
                        &&
                        $primaryNikMasterOffset !== null
                        &&
                        $primaryNameMasterOffset !== null
                        &&
                        $primaryNikTargetOffset !== null
                        &&
                        $primaryNameTargetOffset !== null
                    )
                    {
                        $targetNik = compareNormalizeValue($rowB[$primaryNikTargetOffset] ?? '');
                        $targetName = compareNormalizeValue($rowB[$primaryNameTargetOffset] ?? '');

                        if($targetName !== '' && isset($masterNameIndex[$targetName]))
                        {
                            $candidate = $masterNameIndex[$targetName];
                            $candidateNik = compareNormalizeValue($candidate['row_json'] ? (json_decode($candidate['row_json'], true)[$primaryNikField] ?? '') : '');

                            if($targetNik !== '' && $candidateNik !== '' && $candidateNik !== $targetNik)
                            {
                                $findingType = 'nik_mismatch';
                                $matchedMasterRow = $candidate;
                            }
                        }
                    }

                    if(
                        $findingType === null
                        &&
                        $nameMismatchEnabled
                        &&
                        $primaryNikMasterOffset !== null
                        &&
                        $primaryNameMasterOffset !== null
                        &&
                        $primaryNikTargetOffset !== null
                        &&
                        $primaryNameTargetOffset !== null
                    )
                    {
                        $targetNik = compareNormalizeValue($rowB[$primaryNikTargetOffset] ?? '');
                        $targetName = compareNormalizeValue($rowB[$primaryNameTargetOffset] ?? '');

                        if($targetNik !== '' && isset($masterNikIndex[$targetNik]))
                        {
                            $candidate = $masterNikIndex[$targetNik];
                            $candidateName = compareNormalizeValue($candidate['row_json'] ? (json_decode($candidate['row_json'], true)[$primaryNameField] ?? '') : '');

                            if($targetName !== '' && $candidateName !== '' && $candidateName !== $targetName)
                            {
                                $findingType = 'name_mismatch';
                                $matchedMasterRow = $candidate;
                            }
                        }
                    }

                    if(
                        $findingType === null
                        &&
                        $notFoundEnabled
                    )
                    {
                        $findingType = 'not_found';
                    }
                }
            }
        }

        if($findingType !== null)
        {
            $duplicateRow = [
                'compare_row_id' => $compareRowId,
                'compare_type' => $findingType,
                'duplicate_value' => $composite !== '' ? $composite : implode('|', $rowB),
                'duplicate_column' => implode(', ', $compareKeys),
                'file_a_row' => 0,
                'file_b_row' => $rowNumberB,
                'file_a_json' => '{}',
                'file_b_json' => $rowJsonB
            ];

            if(
                $findingType === 'duplicate_internal'
                &&
                $matchedInternalRow !== null
            )
            {
                $duplicateRow['file_a_row'] = (int)$matchedInternalRow['row'];
                $duplicateRow['file_a_json'] = compareFetchRowJsonByIdCached(
                    $conn,
                    (int)$matchedInternalRow['compare_row_id']
                );
            }

            if(
                (
                    $findingType === 'nik_mismatch'
                    ||
                    $findingType === 'name_mismatch'
                )
                &&
                $matchedMasterRow !== null
            )
            {
                $duplicateRow['file_a_row'] = (int)$matchedMasterRow['row'];
                $duplicateRow['file_a_json'] = (string)($matchedMasterRow['row_json'] ?? '{}');
            }

            if($findingType === 'not_found')
            {
                $duplicateRow['file_a_row'] = 0;
                $duplicateRow['file_a_json'] = '{}';
            }

            $duplicateRows[] = $duplicateRow;
            $duplicateRowIds[] = $compareRowId;
            $totalDuplicate++;
        }

        if($hashKey !== '')
        {
            $seenTargetExactIndex[$hashKey] = [
                'row' => $rowNumberB,
                'compare_row_id' => $compareRowId
            ];
        }
    }

    if(
        !empty($duplicateRows)
        &&
        !compareBulkInsertDuplicates($conn, $batchId, $duplicateRows)
    )
    {
        mysqli_rollback($conn);
        die(mysqli_error($conn));
    }

    if(
        !empty($duplicateRowIds)
        &&
        !compareBatchUpdateDuplicateRows($conn, $duplicateRowIds)
    )
    {
        mysqli_rollback($conn);
        die(mysqli_error($conn));
    }

    mysqli_commit($conn);
    mysqli_begin_transaction($conn);

    gc_collect_cycles();
}

mysqli_commit($conn);

$insertJob = mysqli_query(
    $conn,
    "
    INSERT INTO compare_jobs
    (
        batch_id,
        file_a,
        file_b,
        total_a,
        total_b,
        total_duplicate
    )
    VALUES
    (
        '"
        .
        mysqli_real_escape_string($conn, $batchId)
        .
        "',
        '"
        .
        mysqli_real_escape_string($conn, basename($fileA))
        .
        "',
        '"
        .
        mysqli_real_escape_string($conn, basename($fileB))
        .
        "',
        '"
        .
        (int)$totalA
        .
        "',
        '"
        .
        (int)$totalB
        .
        "',
        '"
        .
        (int)$totalDuplicate
        .
        "'
    )
    "
);

if(!$insertJob)
{
    die(mysqli_error($conn));
}

$_SESSION['compare_batch_id'] = $batchId;

header('Location: result.php');
exit;