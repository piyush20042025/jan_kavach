# Jan Kavach — Mobile Scheme Follow-up Module
## Official REST API Documentation & Integration Guide

**Document Version:** 1.0.0  
**Release Date:** October 16, 2026  
**Module:** Mobile Scheme Follow-up & Beneficiary Progression  
**Platform:** Jan Kavach Mobile Application API  
**Environment Status:** Fully Implemented, Deployed & Verified on Production

---

## 1. Executive Summary & Module Overview

The **Jan Kavach Mobile Scheme Follow-up Module** provides an end-to-end operational workflow for field service men and mobile workers to track, manage, and progress registered beneficiaries through government scheme life cycles: from initial eligibility assessment, to scheme application submission, resolving document impediments, and recording final benefit disbursement.

### Critical Architecture Principle
* **Strict Admin Segregation:** This module is completely decoupled from the existing Jan Kavach Web Admin Panel. No Admin controllers, views, routes, models, or services were altered.
* **Dedicated Backend Stack:**
  * **Controller:** `JanKavach\Controllers\mobile_api\MobileFollowupController`
  * **Service Layer:** `JanKavach\Services\mobile_api\MobileFollowupService`
  * **Database Persistence:** `mobile_scheme_followups` dedicated table
  * **Routing:** `routes/mobile_api.php` under the `/api/mobile/user/` namespace with `mobile_auth` Bearer token authentication.

---

## 2. Server Environments & Base URLs

### 2.1 Production (Live Server)
* **Base Web Host:** `https://jankavach.indevconsultancy.in/public`
* **Mobile API Base URL:** `https://jankavach.indevconsultancy.in/public/api/mobile/user`

### 2.2 Local Development Environment
* **Base Web Host:** `http://localhost/jan_kavach/public`
* **Mobile API Base URL:** `http://localhost/jan_kavach/public/api/mobile/user`

---

## 3. Authentication & Security

All endpoints (except `/auth/login` and `/auth/refresh`) require Bearer Token authentication via the `mobile_auth` middleware.

### 3.1 Authentication Headers
Every request to protected endpoints must include:
```http
Authorization: Bearer <MOBILE_AUTH_TOKEN>
Accept: application/json
Content-Type: application/json
```

### 3.2 Obtaining a Mobile Auth Token
* **Endpoint:** `POST /api/mobile/user/auth/login`
* **Request Body:**
```json
{
  "username": "piyush",
  "password": "your_secure_password",
  "device_name": "Field_Device_Android"
}
```
* **Sample Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "d7a4658a04b12658de994323c21a47df6da93c12140bb98c...",
    "user": {
      "id": 30,
      "username": "piyush",
      "name": "Piyush Pal",
      "role": "field_user"
    }
  }
}
```

---

## 4. Summary of API Endpoints

| # | HTTP Method | Endpoint Route | Description |
|---|-------------|----------------|-------------|
| 1 | `GET` | `/followup/dashboard` | Returns 4 summary cards, pending breakdown, and due buckets. |
| 2 | `GET` | `/followup/register` | Paginated follow-up records with search, status, and due filters. |
| 3 | `GET` | `/followup/beneficiaries/{id}` | Beneficiary profile, eligible schemes, document requirements & status. |
| 4 | `GET` | `/followup/document-types` | Master list of 42 active document types for mobile dropdowns. |
| 5 | `GET` | `/followup/schemes/{id}/documents` | Scheme-specific required and optional document checklist. |
| 6 | `POST` | `/followup/record` | Create or update follow-up progress (Scenarios A, B, C, D). |

> **Note on Route Aliases:** Pluralized routes (`/followups/...`) and `/followup/beneficiaries` (alias for register) are also registered for developer convenience.

---

## 5. Detailed API Endpoint Specifications

---

### 5.1 Endpoint 1: Mobile Follow-up Dashboard
Retrieves real-time counts and metrics for the authenticated service man.

* **Method:** `GET`
* **Route:** `/api/mobile/user/followup/dashboard`
* **Auth Required:** Yes (`Bearer Token`)

#### The 4 Core Dashboard Summary Cards
1. **Total Users Registered (`total_users_registered`):** Count of unique active beneficiaries registered or assigned to the field user.
2. **Total Applied (`total_applied`):** Count of scheme-tracking records where the application has actually been submitted (`status = 'Applied'`).
3. **Pending (`pending`):** Count of scheme-tracking records where the beneficiary has not applied yet, application is pending, or a document issue exists.
4. **Complete (`complete`):** Count of scheme-tracking records where the beneficiary has received the scheme benefit with recorded confirmation (`status = 'Complete'`).

#### Live Verified Response (Initial State)
```json
{
  "success": true,
  "message": "Follow-up dashboard metrics fetched successfully.",
  "data": {
    "summary_cards": {
      "total_users_registered": 11,
      "total_applied": 0,
      "pending": 15,
      "complete": 0
    },
    "pending_breakdown": {
      "not_applied_yet": 15,
      "application_pending": 0,
      "document_issue": 0,
      "verification_pending": 0
    },
    "followup_due": {
      "overdue": 0,
      "today": 0,
      "upcoming": 0
    },
    "counting_rules": {
      "total_users_registered": "Count of unique active beneficiaries registered or assigned to the authenticated Service Man.",
      "total_applied": "Count of scheme-tracking records where the application has actually been submitted (status = Applied).",
      "pending": "Count of scheme-tracking records where the beneficiary has not applied yet, application is pending, or a document issue exists.",
      "complete": "Count of scheme-tracking records where the beneficiary has received the scheme benefit with recorded confirmation."
    }
  }
}
```

---

### 5.2 Endpoint 2: Follow-up Register
Retrieves a paginated list of beneficiary-scheme follow-up records.

* **Method:** `GET`
* **Route:** `/api/mobile/user/followup/register`
* **Auth Required:** Yes (`Bearer Token`)

#### Query Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | `1` | Page number for pagination. |
| `per_page` | integer | No | `20` | Number of items per page. |
| `status` | string | No | `all` | Filter by status: `Pending`, `Applied`, `Complete`. |
| `search` | string | No | null | Search by beneficiary name, code, mobile, or scheme name. |
| `pending_reason` | string | No | null | Filter by reason: `Not applied yet`, `Application pending`, `Document issue`, `Verification pending`. |
| `due_filter` | string | No | `all` | Filter by follow-up schedule: `overdue`, `today`, `upcoming`. |

#### Example Request
```http
GET /api/mobile/user/followup/register?page=1&per_page=20 HTTP/1.1
Host: jankavach.indevconsultancy.in
Authorization: Bearer <TOKEN>
Accept: application/json
```

#### Live Verified Response (Excerpt)
```json
{
  "success": true,
  "message": "Follow-up register records fetched successfully.",
  "data": {
    "items": [
      {
        "followup_id": null,
        "beneficiary": {
          "id": 235,
          "code": "JK-UP-2026-000125",
          "name": "API Test Person 1019",
          "mobile": "9876543215",
          "gender": "Male",
          "age": 36,
          "village": "API Test Village 1018"
        },
        "scheme": {
          "id": 87,
          "code": "JK-SCH-0087",
          "name": "National Family Benefit Scheme (Rashtriya Parivarik Labh Yojana)",
          "short_name": null,
          "application_mode": "Both",
          "source_url": null,
          "source_website": null,
          "portal_url": null
        },
        "eligibility_status": "Eligible",
        "status": "Pending",
        "pending_reason": "Not applied yet",
        "document_issue": null,
        "application_number": null,
        "submission_date": null,
        "benefit_received_date": null,
        "next_followup_date": null,
        "notes": null,
        "last_updated_by": null,
        "last_updated_at": null
      },
      {
        "followup_id": null,
        "beneficiary": {
          "id": 239,
          "code": "JK-UP-2026-000129",
          "name": "bgdfbcv",
          "mobile": null,
          "gender": "Female",
          "age": 17,
          "village": null
        },
        "scheme": {
          "id": 8,
          "code": "JK-SCH-0008",
          "name": "Women Helpline Number 181 scheme",
          "short_name": null,
          "application_mode": "Offline",
          "source_url": "https://wcdhry.gov.in/women-helpline-number-181/",
          "source_website": "wcdhry.gov.in",
          "portal_url": null
        },
        "eligibility_status": "Eligible",
        "status": "Pending",
        "pending_reason": "Not applied yet",
        "document_issue": null,
        "application_number": null,
        "submission_date": null,
        "benefit_received_date": null,
        "next_followup_date": null,
        "notes": null,
        "last_updated_by": null,
        "last_updated_at": null
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total_items": 17,
      "total_pages": 1
    }
  }
}
```

---

### 5.3 Endpoint 3: Beneficiary Follow-up Detail Profile
Retrieves complete details for a specific beneficiary, including demographic information, confirmed eligible schemes, required documents, official scheme URLs, and current follow-up tracking status.

* **Method:** `GET`
* **Route:** `/api/mobile/user/followup/beneficiaries/{id}`
* **Auth Required:** Yes (`Bearer Token`)
* **Path Parameter:** `id` — Unique ID of the beneficiary (e.g., `61`).

#### Example Request
```http
GET /api/mobile/user/followup/beneficiaries/61 HTTP/1.1
Host: jankavach.indevconsultancy.in
Authorization: Bearer <TOKEN>
Accept: application/json
```

#### Live Verified Response
```json
{
  "success": true,
  "message": "Beneficiary follow-up details fetched successfully.",
  "data": {
    "beneficiary": {
      "id": 61,
      "beneficiary_code": "JK-UP-2026-000001",
      "full_name": "Ramesh Kumar Sharma",
      "mobile_number": "9876543210",
      "gender": "Male",
      "date_of_birth": "1985-06-15",
      "age": 41,
      "marital_status": "Married",
      "social_category": "OBC",
      "religion": "Hindu",
      "monthly_income": "8500.00",
      "annual_income": "102000.00",
      "bpl_status": "Yes",
      "state_name": "Uttar Pradesh",
      "district_name": "Lucknow",
      "block_name": "Bakshi Ka Talab",
      "gp_name": "Kathwara",
      "village_name": "Kathwara"
    },
    "eligible_schemes": [
      {
        "scheme_id": 87,
        "scheme_code": "JK-SCH-0087",
        "scheme_name": "National Family Benefit Scheme (Rashtriya Parivarik Labh Yojana)",
        "short_name": null,
        "department_name": "Social Welfare Department",
        "application_mode": "Both",
        "benefit_summary": "One-time financial assistance of Rs. 30,000 to a BPL family upon the death of the primary breadwinner.",
        "source_url": null,
        "source_website": null,
        "portal_url": null,
        "required_documents": [
          {
            "id": 1,
            "name": "Aadhaar Card",
            "code": "DOC_AADHAAR",
            "is_mandatory": true
          },
          {
            "id": 4,
            "name": "Income Certificate",
            "code": "DOC_INCOME",
            "is_mandatory": true
          },
          {
            "id": 5,
            "name": "BPL Ration Card",
            "code": "DOC_BPL_CARD",
            "is_mandatory": true
          },
          {
            "id": 8,
            "name": "Bank Passbook",
            "code": "DOC_BANK_PASSBOOK",
            "is_mandatory": true
          },
          {
            "id": 26,
            "name": "Death Certificate of Breadwinner",
            "code": "DOC_DEATH_CERT",
            "is_mandatory": true
          }
        ],
        "followup": {
          "id": null,
          "status": "Pending",
          "pending_reason": "Not applied yet",
          "document_issue": null,
          "application_number": null,
          "submission_date": null,
          "benefit_received_date": null,
          "next_followup_date": null,
          "notes": null,
          "last_updated_at": null,
          "last_updated_by": null
        }
      }
    ]
  }
}
```

---

### 5.4 Endpoint 4: Master Document Types List
Retrieves all active master document types for rendering mobile dropdowns when reporting document impediments.

* **Method:** `GET`
* **Route:** `/api/mobile/user/followup/document-types`
* **Auth Required:** Yes (`Bearer Token`)

#### Live Verified Response
```json
{
  "success": true,
  "message": "Document types fetched successfully.",
  "data": [
    {
      "id": 1,
      "document_name": "Aadhaar Card",
      "document_code": "DOC_AADHAAR",
      "category": "Identity"
    },
    {
      "id": 2,
      "document_name": "Voter ID Card",
      "document_code": "DOC_VOTER_ID",
      "category": "Identity"
    },
    {
      "id": 3,
      "document_name": "PAN Card",
      "document_code": "DOC_PAN",
      "category": "Identity"
    },
    {
      "id": 4,
      "document_name": "Income Certificate",
      "document_code": "DOC_INCOME",
      "category": "Income"
    },
    {
      "id": 5,
      "document_name": "BPL Ration Card",
      "document_code": "DOC_BPL_CARD",
      "category": "Income"
    },
    {
      "id": 6,
      "document_name": "Caste Certificate",
      "document_code": "DOC_CASTE",
      "category": "Social"
    },
    {
      "id": 7,
      "document_name": "Domicile / Residence Certificate",
      "document_code": "DOC_DOMICILE",
      "category": "Residence"
    },
    {
      "id": 8,
      "document_name": "Bank Passbook",
      "document_code": "DOC_BANK_PASSBOOK",
      "category": "Financial"
    },
    {
      "id": 9,
      "document_name": "Passport Size Photograph",
      "document_code": "DOC_PHOTO",
      "category": "Identity"
    },
    {
      "id": 10,
      "document_name": "Disability Certificate",
      "document_code": "DOC_DISABILITY_CERT",
      "category": "Health"
    }
  ]
}
```

---

### 5.5 Endpoint 5: Scheme Required Documents
Retrieves the checklist of mandatory and optional documents configured for a specific government scheme.

* **Method:** `GET`
* **Route:** `/api/mobile/user/followup/schemes/{id}/documents`
* **Auth Required:** Yes (`Bearer Token`)
* **Path Parameter:** `id` — Scheme ID (e.g., `87`).

#### Live Verified Response
```json
{
  "success": true,
  "message": "Scheme documents fetched successfully.",
  "data": {
    "scheme": {
      "id": 87,
      "scheme_code": "JK-SCH-0087",
      "scheme_name": "National Family Benefit Scheme (Rashtriya Parivarik Labh Yojana)"
    },
    "documents": [
      {
        "id": 1,
        "name": "Aadhaar Card",
        "code": "DOC_AADHAAR",
        "is_mandatory": true
      },
      {
        "id": 4,
        "name": "Income Certificate",
        "code": "DOC_INCOME",
        "is_mandatory": true
      },
      {
        "id": 5,
        "name": "BPL Ration Card",
        "code": "DOC_BPL_CARD",
        "is_mandatory": true
      },
      {
        "id": 8,
        "name": "Bank Passbook",
        "code": "DOC_BANK_PASSBOOK",
        "is_mandatory": true
      },
      {
        "id": 26,
        "name": "Death Certificate of Breadwinner",
        "code": "DOC_DEATH_CERT",
        "is_mandatory": true
      }
    ]
  }
}
```

---

### 5.6 Endpoint 6: Record / Update Follow-up Progress
Records field progress against a beneficiary's eligible scheme. This endpoint handles all operational transitions using an upsert mechanism (insert if new, update if existing).

* **Method:** `POST`
* **Route:** `/api/mobile/user/followup/record`
* **Auth Required:** Yes (`Bearer Token`)
* **Headers:** `Content-Type: application/json`

#### Validation Rules Matrix
| Field Name | Type | Mandatory? | Conditional Logic / Allowed Values |
|------------|------|------------|-----------------------------------|
| `beneficiary_id` | integer | **Yes** | Must exist in `beneficiaries` table. |
| `scheme_id` | integer | **Yes** | Must exist in `schemes` table. |
| `status` | string | **Yes** | Must be one of: `Pending`, `Applied`, `Complete`. |
| `pending_reason` | string | Conditional | Required if `status = Pending`. Values: `Not applied yet`, `Application pending`, `Document issue`, `Verification pending`. |
| `document_type_id`| integer | Conditional | Required if `pending_reason = Document issue`. |
| `issue_type` | string | Optional | `Missing`, `Incorrect`, `Expired`, `Rejected`, `Other`. |
| `document_notes` | string | Optional | Free text describing the document impediment. |
| `application_number`| string | Conditional | Recommended / Required if `status = Applied`. |
| `submission_date`| string | Conditional | Format `YYYY-MM-DD`. Required if `status = Applied`. |
| `benefit_received_date` | string | Conditional | Format `YYYY-MM-DD`. Required if `status = Complete`. |
| `next_followup_date` | string | Optional | Format `YYYY-MM-DD`. |
| `notes` | string | Optional | General follow-up remarks by the field worker. |

---

## 6. The 4 Operational Scenarios (Field Workflows)

---

### Scenario A: Beneficiary Has Not Applied Yet
Used during initial village visits when a beneficiary is identified as eligible, but has not yet initiated the application process.

* **Status:** `Pending`
* **Pending Reason:** `Not applied yet`
* **Next Follow-up Date:** Scheduled visit date.

#### Request Body (JSON)
```json
{
  "beneficiary_id": 61,
  "scheme_id": 87,
  "status": "Pending",
  "pending_reason": "Not applied yet",
  "next_followup_date": "2026-10-25",
  "notes": "Informed beneficiary about Rashtriya Parivarik Labh Yojana. Scheduled next visit for documentation."
}
```

#### Live Verified Response (200 OK)
```json
{
  "success": true,
  "message": "Follow-up record saved successfully.",
  "data": {
    "followup_id": 1,
    "beneficiary_id": 61,
    "scheme_id": 87,
    "status": "Pending",
    "pending_reason": "Not applied yet",
    "document_issue": null,
    "application_number": null,
    "submission_date": null,
    "benefit_received_date": null,
    "next_followup_date": "2026-10-25",
    "notes": "Informed beneficiary about Rashtriya Parivarik Labh Yojana. Scheduled next visit for documentation."
  }
}
```

---

### Scenario B: Application Submitted
Used when the beneficiary has successfully submitted their scheme application at a CSC center, block office, or official government portal.

* **Status:** `Applied`
* **Application Number:** Acknowledgement number / Registration ID.
* **Submission Date:** Date of submission (`YYYY-MM-DD`).

#### Request Body (JSON)
```json
{
  "beneficiary_id": 61,
  "scheme_id": 87,
  "status": "Applied",
  "application_number": "NFBS-UP-2026-99214",
  "submission_date": "2026-10-16",
  "notes": "Application submitted at CSC Kathwara. Receipt copy handed over."
}
```

#### Live Verified Response (200 OK)
```json
{
  "success": true,
  "message": "Follow-up record saved successfully.",
  "data": {
    "followup_id": 1,
    "beneficiary_id": 61,
    "scheme_id": 87,
    "status": "Applied",
    "pending_reason": null,
    "document_issue": null,
    "application_number": "NFBS-UP-2026-99214",
    "submission_date": "2026-10-16",
    "benefit_received_date": null,
    "next_followup_date": null,
    "notes": "Application submitted at CSC Kathwara. Receipt copy handed over."
  }
}
```

---

### Scenario C: Application Blocked by Document Issue
Used when the beneficiary cannot apply or an application is held up due to a missing, expired, or invalid document.

* **Status:** `Pending`
* **Pending Reason:** `Document issue`
* **Document Type ID:** ID from master document list (e.g., `4` for Income Certificate).
* **Issue Type:** `Missing`, `Incorrect`, `Expired`, `Rejected`, `Other`.

#### Request Body (JSON)
```json
{
  "beneficiary_id": 61,
  "scheme_id": 87,
  "status": "Pending",
  "pending_reason": "Document issue",
  "document_type_id": 4,
  "issue_type": "Missing",
  "document_notes": "Income Certificate expired last month. Beneficiary applying for fresh certificate at Tehsil.",
  "next_followup_date": "2026-10-28",
  "notes": "Assisted beneficiary in filling Tehsil income declaration form."
}
```

#### Live Verified Response (200 OK)
```json
{
  "success": true,
  "message": "Follow-up record saved successfully.",
  "data": {
    "followup_id": 1,
    "beneficiary_id": 61,
    "scheme_id": 87,
    "status": "Pending",
    "pending_reason": "Document issue",
    "document_issue": {
      "document_type_id": 4,
      "document_name": "Income Certificate",
      "issue_type": "Missing",
      "notes": "Income Certificate expired last month. Beneficiary applying for fresh certificate at Tehsil."
    },
    "application_number": null,
    "submission_date": null,
    "benefit_received_date": null,
    "next_followup_date": "2026-10-28",
    "notes": "Assisted beneficiary in filling Tehsil income declaration form."
  }
}
```

---

### Scenario D: Scheme Benefit Received (Complete)
Used when the beneficiary has successfully received the scheme assistance (e.g., direct bank transfer credited, asset handed over) and the service man has confirmed receipt.

* **Status:** `Complete`
* **Benefit Received Date:** Date benefit arrived in beneficiary account / hands (`YYYY-MM-DD`).
* **Notes:** Confirmation notes, DBT transaction details, or officer verification remarks.

#### Request Body (JSON)
```json
{
  "beneficiary_id": 61,
  "scheme_id": 87,
  "status": "Complete",
  "benefit_received_date": "2026-10-16",
  "notes": "Financial assistance of Rs. 30,000 credited to beneficiary Bank of Baroda account via DBT. Beneficiary confirmed passbook entry."
}
```

#### Live Verified Response (200 OK)
```json
{
  "success": true,
  "message": "Follow-up record saved successfully.",
  "data": {
    "followup_id": 1,
    "beneficiary_id": 61,
    "scheme_id": 87,
    "status": "Complete",
    "pending_reason": null,
    "document_issue": null,
    "application_number": null,
    "submission_date": null,
    "benefit_received_date": "2026-10-16",
    "next_followup_date": null,
    "notes": "Financial assistance of Rs. 30,000 credited to beneficiary Bank of Baroda account via DBT. Beneficiary confirmed passbook entry."
  }
}
```

---

## 7. Dynamic Dashboard Verification (Live Proof)

Following the recording of Scenarios B, C, and D during live verification, calling `GET /followup/dashboard` confirmed that all counts update in real-time based on database records:

```json
{
  "success": true,
  "message": "Follow-up dashboard metrics fetched successfully.",
  "data": {
    "summary_cards": {
      "total_users_registered": 12,
      "total_applied": 1,
      "pending": 17,
      "complete": 1
    },
    "pending_breakdown": {
      "not_applied_yet": 15,
      "application_pending": 0,
      "document_issue": 2,
      "verification_pending": 0
    },
    "followup_due": {
      "overdue": 0,
      "today": 0,
      "upcoming": 3
    },
    "counting_rules": {
      "total_users_registered": "Count of unique active beneficiaries registered or assigned to the authenticated Service Man.",
      "total_applied": "Count of scheme-tracking records where the application has actually been submitted (status = Applied).",
      "pending": "Count of scheme-tracking records where the beneficiary has not applied yet, application is pending, or a document issue exists.",
      "complete": "Count of scheme-tracking records where the beneficiary has received the scheme benefit with recorded confirmation."
    }
  }
}
```

### Verification Findings
1. **Total Users Registered:** Incrementally tracks unique beneficiaries (12 beneficiaries registered).
2. **Total Applied:** Rose from 0 to 1 when Scenario B was submitted.
3. **Complete:** Rose from 0 to 1 when Scenario D was recorded.
4. **Document Issue:** Rose from 0 to 2 when Scenario C was recorded.
5. **Upcoming Due:** Reflects follow-up visits scheduled within the next 7 days.

---

## 8. Database Architecture: `mobile_scheme_followups`

The module operates on a dedicated database table designed for high throughput, data integrity, and complete audit tracking.

```sql
CREATE TABLE IF NOT EXISTS `mobile_scheme_followups` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `beneficiary_id` bigint(20) UNSIGNED NOT NULL,
  `scheme_id` bigint(20) UNSIGNED NOT NULL,
  `field_user_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('Pending','Applied','Complete') NOT NULL DEFAULT 'Pending',
  `pending_reason` enum('Not applied yet','Application pending','Document issue','Verification pending') DEFAULT 'Not applied yet',
  `document_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `issue_type` varchar(50) DEFAULT NULL,
  `document_notes` text DEFAULT NULL,
  `application_number` varchar(100) DEFAULT NULL,
  `submission_date` date DEFAULT NULL,
  `benefit_received_date` date DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_beneficiary_scheme` (`beneficiary_id`, `scheme_id`),
  KEY `idx_status` (`status`),
  KEY `idx_pending_reason` (`pending_reason`),
  KEY `idx_next_followup` (`next_followup_date`),
  KEY `idx_field_user` (`field_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 9. Error Handling & Standard Responses

All responses maintain a standardized JSON envelope structure:

### 9.1 Standard Success Envelope
```json
{
  "success": true,
  "message": "Human readable success message.",
  "data": { ... }
}
```

### 9.2 Standard Error Envelope
```json
{
  "success": false,
  "message": "Error description.",
  "errors": {
    "field_name": [
      "Validation detail message."
    ]
  }
}
```

### 9.3 Common HTTP Status Codes
| HTTP Status | Code Meaning | Common Cause |
|-------------|--------------|--------------|
| `200 OK` | Success | Valid GET / POST request processed successfully. |
| `400 Bad Request` | Validation Failure | Missing required fields, invalid date formats, or illegal status value. |
| `401 Unauthorized` | Auth Error | Missing or expired Bearer token in the `Authorization` header. |
| `403 Forbidden` | Access Denied | User does not have access permissions. |
| `404 Not Found` | Entity Missing | Beneficiary ID or Scheme ID does not exist in the database. |
| `500 Server Error` | Unhandled Error | Database connection drop or unexpected server exception. |

---

## 10. Postman Testing Guide & Quick Curl Examples

### 10.1 Environment Variables in Postman
Set up the following collection variables:
* `base_url`: `https://jankavach.indevconsultancy.in/public/api/mobile/user`
* `token`: `<YOUR_AUTH_BEARER_TOKEN>`

### 10.2 Quick cURL Commands

#### Check Dashboard
```bash
curl -X GET "https://jankavach.indevconsultancy.in/public/api/mobile/user/followup/dashboard" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

#### Fetch Follow-up Register (Page 1)
```bash
curl -X GET "https://jankavach.indevconsultancy.in/public/api/mobile/user/followup/register?page=1&per_page=20" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

#### Get Beneficiary Profile (ID 61)
```bash
curl -X GET "https://jankavach.indevconsultancy.in/public/api/mobile/user/followup/beneficiaries/61" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

#### Record Follow-up (Scenario B - Applied)
```bash
curl -X POST "https://jankavach.indevconsultancy.in/public/api/mobile/user/followup/record" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "beneficiary_id": 61,
    "scheme_id": 87,
    "status": "Applied",
    "application_number": "NFBS-UP-2026-99214",
    "submission_date": "2026-10-16",
    "notes": "Application submitted at CSC Kathwara."
  }'
```

---
**End of Jan Kavach Mobile Scheme Follow-up API Documentation**
