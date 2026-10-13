<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Libraries\FileUploader;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\BeneficiaryDocument;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;

/**
 * Beneficiary document upload, verification and secure retrieval
 * (specification section 19).
 *
 * Files live under storage/documents, outside the web root. They are only
 * ever reachable through view()/download(), which re-check the caller's
 * permission and geographic scope on every request.
 */
class DocumentController extends Controller
{
    private BeneficiaryDocument $documents;
    private Beneficiary $beneficiaries;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->documents = new BeneficiaryDocument();
        $this->beneficiaries = new Beneficiary();
    }

    // =================================================================
    // Per-beneficiary document workspace
    // =================================================================

    public function index(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertBeneficiaryAccess($beneficiary);

        $this->view('beneficiaries/documents', [
            'pageTitle'   => 'Beneficiary Documents',
            'breadcrumbs' => [
                ['label' => 'Beneficiaries', 'url' => '/beneficiaries'],
                ['label' => str_limit($beneficiary['name'], 40), 'url' => '/beneficiaries/' . (int)$id],
                ['label' => 'Documents'],
            ],
            'beneficiary'   => $beneficiary,
            'documents'     => $this->documents->heldBy((int)$id),
            'outstanding'   => $this->documents->outstandingFor((int)$id),
            'documentTypes' => (new Master())->documentTypes(),
            'maxSizeMb'     => (int)(setting('max_document_size_mb') ?? 5),
            'allowedTypes'  => setting('allowed_extensions') ?? 'pdf,jpg,jpeg,png',
        ]);
    }

    public function store(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertBeneficiaryAccess($beneficiary);
        $beneficiaryId = (int)$id;

        $validator = Validator::make($this->request->all(), [
            'document_type_id' => 'required|integer|exists:document_types,id',
            'document_number'  => 'nullable|string|max:80',
            'issue_date'       => 'nullable|date',
            'expiry_date'      => 'nullable|date',
            'remarks'          => 'nullable|string|max:500',
        ], ['document_type_id' => 'Document Type']);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/beneficiaries/' . $beneficiaryId . '/documents');
        }

        $file = $this->request->file('document_file');
        if (!$file) {
            Flash::error('Please choose a file to upload.');
            $this->back('/beneficiaries/' . $beneficiaryId . '/documents');
        }

        $issueDate  = $this->dateOrNull('issue_date');
        $expiryDate = $this->dateOrNull('expiry_date');
        if ($issueDate && $expiryDate && strtotime($expiryDate) < strtotime($issueDate)) {
            $this->backWithErrors(['expiry_date' => 'The expiry date cannot be before the issue date.'],
                '/beneficiaries/' . $beneficiaryId . '/documents');
        }

        $uploader = new FileUploader();
        $stored = $uploader->storeDocument($file, 'beneficiary-' . $beneficiaryId);

        if (!$stored) {
            Flash::error($uploader->firstError() ?? 'The document could not be uploaded.');
            $this->back('/beneficiaries/' . $beneficiaryId . '/documents');
        }

        $documentTypeId = (int)$this->request->post('document_type_id');

        // Replacing an existing document of the same type: keep only the
        // newest file so the beneficiary's folder stays unambiguous.
        $existing = $this->documents->existingOfType($beneficiaryId, $documentTypeId);
        if ($existing && $this->request->bool('replace_existing')) {
            FileUploader::delete($existing['file_path'] ?? null);
            $this->db->delete('beneficiary_documents', ['id' => (int)$existing['id']]);
        }

        $documentId = $this->documents->create([
            'beneficiary_id'      => $beneficiaryId,
            'document_type_id'    => $documentTypeId,
            'document_number'     => $this->request->post('document_number') ?: null,
            'issue_date'          => $issueDate,
            'expiry_date'         => $expiryDate,
            'file_path'           => $stored['file_path'],
            'original_name'       => $stored['original_name'],
            'stored_name'         => $stored['stored_name'],
            'file_size'           => $stored['file_size'],
            'mime_type'           => $stored['mime_type'],
            'file_hash'           => $stored['file_hash'],
            'verification_status' => 'Pending',
            'remarks'             => $this->request->post('remarks') ?: null,
            'uploaded_by'         => Auth::id(),
        ]);

        $typeName = $this->db->scalar('SELECT name FROM document_types WHERE id = :id', ['id' => $documentTypeId]);

        Audit::log('Documents', 'Created', [
            'entity_type'  => 'BeneficiaryDocument',
            'entity_id'    => $documentId,
            'entity_label' => $typeName,
            'description'  => sprintf('Uploaded "%s" for beneficiary %s (%s, %s)',
                                      $typeName, $beneficiary['name'],
                                      $stored['original_name'], $this->humanSize($stored['file_size'])),
        ]);

        Flash::success($typeName . ' uploaded. It is awaiting verification.');
        $this->redirect('/beneficiaries/' . $beneficiaryId . '/documents');
    }

    // =================================================================
    // Secure retrieval
    // =================================================================

    public function view(string $id): void
    {
        $document = $this->loadAuthorised((int)$id);
        $path = FileUploader::resolve($document['file_path'] ?? null);

        if (!$path) {
            Response::abort(404, 'The stored file could not be found.');
        }

        Audit::viewed('Documents', 'BeneficiaryDocument', (int)$id,
            $document['document_name'] . ' - ' . $document['beneficiary_name']);

        Response::inline($path, $document['original_name'] ?: basename($path), $document['mime_type'] ?: null);
    }

    public function download(string $id): void
    {
        $document = $this->loadAuthorised((int)$id);
        $path = FileUploader::resolve($document['file_path'] ?? null);

        if (!$path) {
            Response::abort(404, 'The stored file could not be found.');
        }

        Audit::log('Documents', 'Exported', [
            'entity_type'  => 'BeneficiaryDocument',
            'entity_id'    => (int)$id,
            'entity_label' => $document['document_name'],
            'description'  => 'Downloaded document for beneficiary ' . $document['beneficiary_name'],
        ]);

        Response::download($path, $document['original_name'] ?: basename($path), $document['mime_type'] ?: null);
    }

    // =================================================================
    // Verification
    // =================================================================

    public function verify(string $id): void
    {
        $document = $this->loadAuthorised((int)$id);

        $status = $this->inOrNull('status', ['Verified', 'Rejected', 'Pending']);
        if ($status === null) {
            Flash::error('Please choose a verification outcome.');
            $this->back('/beneficiaries/' . (int)$document['beneficiary_id'] . '/documents');
        }

        $reason = null;
        if ($status === 'Rejected') {
            $reason = trim((string)$this->request->post('rejection_reason'));
            if ($reason === '') {
                Flash::error('Please record why the document was rejected, so the field team knows what to correct.');
                $this->back('/beneficiaries/' . (int)$document['beneficiary_id'] . '/documents');
            }
            $reason = mb_substr($reason, 0, 255);
        }

        $this->documents->verify((int)$id, $status, Auth::id(), $reason);

        Audit::log('Documents', 'Verified', [
            'entity_type'  => 'BeneficiaryDocument',
            'entity_id'    => (int)$id,
            'entity_label' => $document['document_name'],
            'description'  => sprintf('Document marked "%s" for beneficiary %s%s',
                                      $status, $document['beneficiary_name'],
                                      $reason ? ' - ' . $reason : ''),
            'old_values'   => ['verification_status' => $document['verification_status']],
            'new_values'   => ['verification_status' => $status],
        ]);

        if ($this->request->isAjax()) {
            $this->jsonSuccess(['status' => $status], 'Document marked ' . $status . '.');
        }

        Flash::success('Document marked ' . $status . '.');
        $this->back('/beneficiaries/' . (int)$document['beneficiary_id'] . '/documents');
    }

    public function destroy(string $id): void
    {
        $document = $this->loadAuthorised((int)$id);
        $beneficiaryId = (int)$document['beneficiary_id'];

        // A verified document is evidence; require an explicit override.
        if ($document['verification_status'] === 'Verified' && !Auth::can('beneficiary.verify')) {
            Flash::error('A verified document can only be removed by a user who can verify documents.');
            $this->back('/beneficiaries/' . $beneficiaryId . '/documents');
        }

        $this->documents->deleteWithFile((int)$id);

        Audit::deleted('Documents', 'BeneficiaryDocument', (int)$id, $document['document_name'], [
            'beneficiary'  => $document['beneficiary_name'],
            'file'         => $document['original_name'],
            'verification' => $document['verification_status'],
        ]);

        Flash::success('Document removed.');
        $this->back('/beneficiaries/' . $beneficiaryId . '/documents');
    }

    // =================================================================
    // Verification queue
    // =================================================================

    public function pending(): void
    {
        $filters = [
            'search'           => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'document_type_id' => $this->request->int('document_type_id'),
        ];
        [$filters['state_id'], $filters['district_id']] =
            Scope::constrainFilters($this->request->int('state_id'), $this->request->int('district_id'));

        $geo = new Geography();

        $paginator = $this->documents->pendingVerification(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $_GET ?? []
        );

        $this->view('beneficiaries/documents-pending', [
            'pageTitle'   => 'Documents Awaiting Verification',
            'breadcrumbs' => [['label' => 'Beneficiaries', 'url' => '/beneficiaries'], ['label' => 'Document Verification']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'documentTypes' => (new Master())->optionsFor('document_types'),
            'states'      => $geo->stateOptions(),
            'districts'   => $geo->districtOptions($filters['state_id'] ?? null),
            'expiring'    => $this->documents->expiring(60),
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    /** Load a document and confirm the caller may see this beneficiary. */
    private function loadAuthorised(int $documentId): array
    {
        $document = $this->findOrFail($this->documents->findDetailed($documentId), 'Document');

        Scope::assertAccess(
            (int)$document['state_id'],
            (int)$document['district_id'],
            $document['field_user_id'] ? (int)$document['field_user_id'] : null
        );

        return $document;
    }

    private function assertBeneficiaryAccess(array $beneficiary): void
    {
        Scope::assertAccess(
            (int)$beneficiary['state_id'],
            (int)$beneficiary['district_id'],
            $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null
        );
    }

    private function humanSize(?int $bytes): string
    {
        $bytes = (int)$bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
