<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Logger;
use JanKavach\Core\Response;
use JanKavach\Libraries\FileUploader;
use JanKavach\Libraries\XlsxReader;
use JanKavach\Libraries\XlsxWriter;
use JanKavach\Models\SchemeImport;

/**
 * Excel scheme import (specification section 37):
 * upload -> validate -> preview -> import, with rejected rows kept for review.
 */
class SchemeImportController extends Controller
{
    private SchemeImport $import;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->import = new SchemeImport();
    }

    public function index(): void
    {
        $this->view('imports/index', [
            'pageTitle'   => 'Import Schemes',
            'breadcrumbs' => [['label' => 'Import Schemes']],
            'batches'     => $this->import->batches(20),
            'fields'      => SchemeImport::TARGET_FIELDS,
            'maxSizeMb'   => round((int)config('upload.import_max_size', 10485760) / 1048576),
        ]);
    }

    public function upload(): void
    {
        $file = $this->request->file('import_file');
        if (!$file) {
            Flash::error('Please choose an Excel workbook or CSV file to upload.');
            $this->back('/import/schemes');
        }

        $uploader = new FileUploader();
        $stored = $uploader->storeImport($file);
        if (!$stored) {
            Flash::error($uploader->firstError() ?? 'The workbook could not be uploaded.');
            $this->back('/import/schemes');
        }

        try {
            $reader = new XlsxReader($stored['absolute_path']);
            $sheets = $reader->sheetNames();
            $sheet  = $this->request->post('sheet_name') ?: ($sheets[0] ?? null);

            $headers = $reader->headers($sheet);
            if (count(array_filter($headers)) === 0) {
                throw new \RuntimeException('No column headings were found in the first row of the sheet.');
            }

            $batchId = $this->import->createBatch($stored, $sheet);
            $map = $this->import->autoMap($headers);

            $this->db->update('scheme_import_batches',
                ['column_map' => json_encode($map)], ['id' => $batchId]);

            Audit::log('Import', 'Imported', [
                'entity_type'  => 'SchemeImportBatch',
                'entity_id'    => $batchId,
                'entity_label' => $stored['original_name'],
                'description'  => sprintf('Workbook uploaded (%s, sheet "%s", %d column(s) detected)',
                                          $stored['original_name'], (string)$sheet, count(array_filter($headers))),
            ]);

            Flash::success('Workbook uploaded. Review the column mapping, then validate the rows.');
            $this->redirect('/import/schemes/' . $batchId . '/preview');

        } catch (\Throwable $e) {
            @unlink($stored['absolute_path']);
            Logger::error('Scheme import upload failed: ' . $e->getMessage());
            Flash::error('The workbook could not be read: ' . $e->getMessage());
            $this->back('/import/schemes');
        }
    }

    public function preview(string $id): void
    {
        $batch = $this->findOrFail($this->import->batch((int)$id), 'Import batch');
        $path = IMPORT_PATH . '/' . $batch['stored_path'];

        if (!is_file($path)) {
            Flash::error('The uploaded workbook is no longer on the server. Please upload it again.');
            $this->redirect('/import/schemes');
        }

        $reader  = new XlsxReader($path);
        $headers = $reader->headers($batch['sheet_name']);
        $map     = json_list($batch['column_map']) ?: $this->import->autoMap($headers);

        // Validate on first view, or when the mapping was just changed.
        $revalidate = $this->request->bool('revalidate')
                   || (int)$batch['total_rows'] === 0
                   || $batch['status'] === 'Uploaded';

        if ($revalidate) {
            // A re-posted mapping overrides the automatic one.
            $posted = $_GET['map'] ?? $_POST['map'] ?? null;
            if (is_array($posted)) {
                $map = [];
                foreach ($posted as $field => $index) {
                    if ($index !== '' && $index !== null && isset(SchemeImport::TARGET_FIELDS[$field])) {
                        $map[$field] = (int)$index;
                    }
                }
            }
            try {
                $this->import->parseAndValidate((int)$id, $path, $batch['sheet_name'], $map);
                $batch = $this->import->batch((int)$id);
            } catch (\Throwable $e) {
                Logger::error('Scheme import validation failed: ' . $e->getMessage());
                Flash::error('The workbook could not be validated: ' . $e->getMessage());
                $this->redirect('/import/schemes');
            }
        }

        $statusFilter = $this->inOrNull('status', ['Valid', 'Duplicate', 'Error', 'Inserted', 'Updated', 'Skipped']);

        $this->view('imports/preview', [
            'pageTitle'   => 'Preview Import',
            'breadcrumbs' => [
                ['label' => 'Import Schemes', 'url' => '/import/schemes'],
                ['label' => $batch['batch_code']],
            ],
            'batch'       => $batch,
            'headers'     => $headers,
            'map'         => $map,
            'fields'      => SchemeImport::TARGET_FIELDS,
            'rows'        => $this->import->batchRows((int)$id, $statusFilter, 500),
            'counts'      => $this->import->rowCounts((int)$id),
            'statusFilter'=> $statusFilter,
        ]);
    }

    public function process(string $id): void
    {
        $batch = $this->findOrFail($this->import->batch((int)$id), 'Import batch');

        if ($batch['status'] === 'Imported') {
            Flash::warning('This batch has already been imported.');
            $this->redirect('/import/schemes/' . (int)$id . '/preview');
        }

        // Per-row decisions from the preview screen.
        $actions = [];
        $posted = $_POST['action'] ?? [];
        if (is_array($posted)) {
            foreach ($posted as $rowId => $action) {
                if (in_array($action, ['insert', 'update', 'skip'], true)) {
                    $actions[(int)$rowId] = $action;
                }
            }
        }

        // A blanket choice for the duplicates, applied where no explicit
        // per-row decision was made.
        $duplicateAction = $this->inOrNull('duplicate_action', ['skip', 'update']) ?? 'skip';
        foreach ($this->import->batchRows((int)$id, 'Duplicate', 5000) as $row) {
            if (!isset($actions[(int)$row['id']])) {
                $actions[(int)$row['id']] = $duplicateAction;
            }
        }

        try {
            $result = $this->import->process((int)$id, $actions);
        } catch (\Throwable $e) {
            Logger::error('Scheme import failed: ' . $e->getMessage());
            Flash::error('The import could not be completed: ' . $e->getMessage());
            $this->redirect('/import/schemes/' . (int)$id . '/preview');
        }

        Audit::log('Import', 'Imported', [
            'entity_type'  => 'SchemeImportBatch',
            'entity_id'    => (int)$id,
            'entity_label' => $batch['original_name'],
            'description'  => sprintf('Import completed: %d inserted, %d updated, %d skipped, %d failed.',
                                      $result['inserted'], $result['updated'], $result['skipped'], $result['failed']),
            'new_values'   => $result,
        ]);

        $message = sprintf('Import complete: %d scheme(s) created, %d updated, %d skipped.',
            $result['inserted'], $result['updated'], $result['skipped']);

        if ($result['failed'] > 0) {
            Flash::warning($message . ' ' . $result['failed'] . ' row(s) failed - open the error list to see why.');
        } else {
            Flash::success($message);
        }

        if ($result['inserted'] > 0 || $result['updated'] > 0) {
            Flash::info('Imported schemes have no eligibility rules yet. Configure them under each scheme so '
                      . 'beneficiary matching can use them.');
        }

        $this->redirect('/import/schemes/' . (int)$id . '/preview');
    }

    public function cancel(string $id): void
    {
        $batch = $this->findOrFail($this->import->batch((int)$id), 'Import batch');

        if ($batch['status'] === 'Imported') {
            Flash::error('An imported batch cannot be cancelled.');
            $this->redirect('/import/schemes');
        }

        $this->import->cancel((int)$id);

        // The uploaded file is no longer needed once the batch is abandoned.
        $path = IMPORT_PATH . '/' . $batch['stored_path'];
        if (is_file($path)) {
            @unlink($path);
        }

        Audit::log('Import', 'Modified', [
            'entity_type'  => 'SchemeImportBatch',
            'entity_id'    => (int)$id,
            'entity_label' => $batch['original_name'],
            'description'  => 'Import batch cancelled before processing',
        ]);

        Flash::info('Import cancelled.');
        $this->redirect('/import/schemes');
    }

    public function errors(string $id): void
    {
        $batch = $this->findOrFail($this->import->batch((int)$id), 'Import batch');

        $this->view('imports/errors', [
            'pageTitle'   => 'Import Errors',
            'breadcrumbs' => [
                ['label' => 'Import Schemes', 'url' => '/import/schemes'],
                ['label' => $batch['batch_code'], 'url' => '/import/schemes/' . (int)$id . '/preview'],
                ['label' => 'Rejected Rows'],
            ],
            'batch'  => $batch,
            'rows'   => $this->import->batchRows((int)$id, 'Error', 2000),
            'counts' => $this->import->rowCounts((int)$id),
            'fields' => SchemeImport::TARGET_FIELDS,
        ]);
    }

    /** A blank workbook with the expected headings. */
    public function template(): void
    {
        $headers = array_values(SchemeImport::TARGET_FIELDS);

        $sample = [
            '1',
            '',
            'Pradhan Mantri Matru Vandana Yojana (PMMVY)',
            'PMMVY',
            'National',
            '',
            '',
            '',
            'Women',
            '18 - 59 years old, pregnant and lactating women.',
            'Health',
            '1. Proof of pregnancy. 2. Aadhaar card. 3. Bank account details.',
            "Cash incentive paid in instalments on meeting health conditions.",
            '1. Visit the nearest Anganwadi centre. 2. Register the pregnancy and complete the application.',
            'Referral to Concerned Department',
            'https://example.gov.in/scheme-page',
            'Ministry of Women and Child Development',
            '',
            'Active',
            'Yes',
            '',
            '',
        ];

        $writer = new XlsxWriter($headers, [$sample], 'Schemes');
        $writer->setMeta([
            'Jan Kavach - Scheme Import Template',
            'Fill one scheme per row. "Name of the Scheme" and "National / State-Specific" are required.',
            'For a State scheme put the State name in the National / State-Specific column, e.g. "West Bengal".',
            'The sample row below is illustrative - delete it before importing.',
        ]);

        Audit::log('Import', 'Exported', [
            'entity_type' => 'SchemeImportTemplate',
            'description' => 'Downloaded the scheme import template',
        ]);

        $writer->download('jan-kavach-scheme-import-template.xlsx');
    }
}
