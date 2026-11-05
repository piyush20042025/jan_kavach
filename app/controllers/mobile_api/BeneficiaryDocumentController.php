<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Database;
use JanKavach\Core\Response;
use JanKavach\Libraries\FileUploader;
use JanKavach\Middleware\mobile_api\AuthMiddleware;
use JanKavach\Models\BeneficiaryDocument;

/**
 * Mobile API Controller for Beneficiary Documents.
 *
 * Provides endpoints to:
 * 1. Retrieve all existing documents for a beneficiary (metadata, status, secure URLs).
 * 2. Securely stream document files (PDF / images) with permission checks.
 */
class BeneficiaryDocumentController extends Controller
{
    private BeneficiaryDocument $documents;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->documents = new BeneficiaryDocument();
    }

    /**
     * GET /api/mobile/user/beneficiaries/{beneficiary_id}/documents
     *
     * Retrieves all documents associated with a particular beneficiary.
     */
    public function index(string $beneficiaryId): void
    {
        $id = filter_var($beneficiaryId, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            Response::error('Invalid beneficiary ID.', null, 400);
            return;
        }

        $user = AuthMiddleware::user();
        if (!$user || empty($user['id'])) {
            Response::error('Unauthorised.', null, 401);
            return;
        }

        try {
            $beneficiary = $this->db->selectOne(
                'SELECT id, name, beneficiary_code, state_id, district_id, field_user_id, created_by, deleted_at
                   FROM beneficiaries
                  WHERE id = :id
                  LIMIT 1',
                ['id' => $id]
            );

            if (!$beneficiary || $beneficiary['deleted_at'] !== null) {
                Response::error('Beneficiary not found.', null, 404);
                return;
            }

            if (!$this->hasBeneficiaryAccess($user, $beneficiary)) {
                Response::error('You do not have permission to access this beneficiary\'s documents.', null, 403);
                return;
            }

            $rawDocs = $this->documents->heldBy($id);

            if (empty($rawDocs)) {
                Response::success([
                    'beneficiary_id' => $id,
                    'documents'      => []
                ], 'No documents found for this beneficiary.');
                return;
            }

            $docList = [];
            foreach ($rawDocs as $doc) {
                $docId        = (int) $doc['id'];
                $originalName = $doc['original_name'] ?: ($doc['stored_name'] ?: basename((string) ($doc['file_path'] ?? '')));
                $filePath     = $doc['file_path'] ?? null;
                $hasFile      = FileUploader::resolve($filePath) !== null;

                $fileUrl = url('/api/mobile/user/beneficiaries/' . $id . '/documents/' . $docId . '/file');

                $docList[] = [
                    'id'                  => $docId,
                    'beneficiary_id'      => $id,
                    'document_type_id'    => (int) $doc['document_type_id'],
                    'document_type'       => $doc['document_name'],
                    'document_name'       => $doc['document_name'],
                    'document_code'       => $doc['document_code'] ?? null,
                    'category'            => $doc['category'] ?? null,
                    'document_number'     => mask_document_number($doc['document_number'] ?? null, (bool) ($doc['is_sensitive'] ?? false)),
                    'file_name'           => $originalName,
                    'original_name'       => $originalName,
                    'file_url'            => $fileUrl,
                    'mime_type'           => $doc['mime_type'] ?: 'application/octet-stream',
                    'file_size'           => $doc['file_size'] !== null ? (int) $doc['file_size'] : null,
                    'file_size_human'     => $this->humanFileSize($doc['file_size'] ?? null),
                    'file_exists'         => $hasFile,
                    'status'              => $doc['verification_status'] ?? 'Pending',
                    'verification_status' => $doc['verification_status'] ?? 'Pending',
                    'issue_date'          => $doc['issue_date'] ?? null,
                    'expiry_date'         => $doc['expiry_date'] ?? null,
                    'uploaded_at'         => $doc['created_at'] ?? null,
                    'uploaded_by'         => $doc['uploaded_by_name'] ?? null,
                    'remarks'             => $doc['remarks'] ?? null,
                    'rejection_reason'    => $doc['rejection_reason'] ?? null,
                ];
            }

            Response::success([
                'beneficiary_id' => $id,
                'documents'      => $docList
            ], 'Documents fetched successfully');
        } catch (\Throwable $e) {
            error_log('[Mobile Beneficiary Documents] ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            Response::error('Failed to fetch beneficiary documents.', null, 500);
        }
    }

    /**
     * GET /api/mobile/user/beneficiaries/{beneficiary_id}/documents/{document_id}/file
     * GET /api/mobile/user/documents/{document_id}/file
     *
     * Securely streams the document file (PDF or image) with permission checks.
     */
    public function file(string $param1, ?string $param2 = null): void
    {
        // Support both routes:
        // /beneficiaries/{beneficiary_id}/documents/{document_id}/file  => param1 = benId, param2 = docId
        // /documents/{document_id}/file                                 => param1 = docId, param2 = null
        if ($param2 === null) {
            $beneficiaryId = null;
            $documentId    = filter_var($param1, FILTER_VALIDATE_INT);
        } else {
            $beneficiaryId = filter_var($param1, FILTER_VALIDATE_INT);
            $documentId    = filter_var($param2, FILTER_VALIDATE_INT);
        }

        if (!$documentId || $documentId <= 0) {
            Response::error('Invalid document ID.', null, 400);
            return;
        }

        $user = AuthMiddleware::user();
        if (!$user || empty($user['id'])) {
            Response::error('Unauthorised.', null, 401);
            return;
        }

        try {
            $doc = $this->db->selectOne(
                'SELECT bd.*, dt.name AS document_name
                   FROM beneficiary_documents bd
                   JOIN document_types dt ON dt.id = bd.document_type_id
                  WHERE bd.id = :id
                  LIMIT 1',
                ['id' => $documentId]
            );

            if (!$doc) {
                Response::error('Document not found.', null, 404);
                return;
            }

            $actualBeneficiaryId = (int) $doc['beneficiary_id'];

            if ($beneficiaryId && $beneficiaryId > 0 && $actualBeneficiaryId !== $beneficiaryId) {
                Response::error('Document does not belong to the specified beneficiary.', null, 404);
                return;
            }

            $beneficiary = $this->db->selectOne(
                'SELECT id, name, beneficiary_code, state_id, district_id, field_user_id, created_by, deleted_at
                   FROM beneficiaries
                  WHERE id = :id
                  LIMIT 1',
                ['id' => $actualBeneficiaryId]
            );

            if (!$beneficiary || $beneficiary['deleted_at'] !== null) {
                Response::error('Beneficiary not found.', null, 404);
                return;
            }

            if (!$this->hasBeneficiaryAccess($user, $beneficiary)) {
                Response::error('You do not have permission to access this document.', null, 403);
                return;
            }

            $path = FileUploader::resolve($doc['file_path'] ?? null);
            if (!$path || !is_file($path)) {
                Response::error('Document file not found on disk.', null, 404);
                return;
            }

            $filename = $doc['original_name'] ?: basename($path);
            $mime     = $doc['mime_type'] ?: (mime_content_type($path) ?: 'application/octet-stream');

            $download = $this->request ? $this->request->bool('download') : false;
            if ($download) {
                Response::download($path, $filename, $mime);
            } else {
                Response::inline($path, $filename, $mime);
            }
        } catch (\Throwable $e) {
            error_log('[Mobile Beneficiary Document File] ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            Response::error('Failed to retrieve document file.', null, 500);
        }
    }

    /**
     * Checks if the authenticated user has permission to access this beneficiary.
     */
    private function hasBeneficiaryAccess(array $user, array $beneficiary): bool
    {
        $roleCode  = strtoupper(trim((string) ($user['role_code'] ?? '')));
        $dataScope = strtolower(trim((string) ($user['data_scope'] ?? '')));
        $userId    = (int) ($user['id'] ?? 0);

        // Super administrators see everything
        if ($roleCode === 'SUPER_ADMIN' || $dataScope === 'all') {
            return true;
        }

        // State administrators see records within their assigned state
        if ($roleCode === 'STATE_ADMIN' || $dataScope === 'state') {
            $stateId = (int) ($user['state_id'] ?? 0);
            return $stateId > 0 && (int) ($beneficiary['state_id'] ?? 0) === $stateId;
        }

        // District coordinators see records within their assigned district
        if ($roleCode === 'DISTRICT_USER' || $dataScope === 'district') {
            $districtId = (int) ($user['district_id'] ?? 0);
            return $districtId > 0 && (int) ($beneficiary['district_id'] ?? 0) === $districtId;
        }

        // Field users see records they collected (field_user_id) or registered (created_by)
        return ((int) ($beneficiary['field_user_id'] ?? 0) === $userId
            || (int) ($beneficiary['created_by'] ?? 0) === $userId);
    }

    /**
     * Helper to format file size in human-readable notation.
     */
    private function humanFileSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
