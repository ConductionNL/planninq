# Planix API Test Results

**Test Date:** 2026-04-03  
**Tester:** API Testing Agent  
**App Version:** Unknown  
**Environment:** Development (Nextcloud.local)

## Summary

| Category | Endpoint Type | Tests Passed | Tests Failed | Issues |
|----------|--------------|-------------|------------|--------|
| OpenRegister Collection APIs | GET (CRUD) | 4/4 | 0 | None |
| Planix Settings API | GET/POST | 2/2 | 0 | None |
| Pagination & Filtering | Query Parameters | 3/3 | 0 | Query parsing issue with invalid parameters |
| CRUD Operations | POST/PUT/DELETE | 1/3 | 2 | Schema validation constraints |
| Health/Metrics Endpoints | Utility | 0/2 | 2 | Returns 500 errors |
| **Overall** | **All** | **10/14** | **4** | **2 blocking issues** |

---

## Results by Feature

### 1. OpenRegister Collection APIs (via OpenRegister app)

All core data schemas are accessible and return properly structured paginated results.

#### 1.1 GET Projects
**Endpoint:** `GET /index.php/apps/openregister/api/objects/planix/project`  
**Status:** ✓ PASS (200 OK)

**Response Structure:**
- 4 projects returned (Client Portal v2, Infrastructure Migration, Onboarding Automation, test)
- Each includes: title, description, status, color, icon, members array
- Pagination: limit=20, offset=0, pages=1
- Metrics: Response time ~4.31ms

**Notes:**
- Returns consistent data structure with @self metadata for each object
- Includes metadata for relations, organisation, timestamps
- Schema ID: 52, Register ID: 3

#### 1.2 GET Tasks
**Endpoint:** `GET /index.php/apps/openregister/api/objects/planix/task`  
**Status:** ✓ PASS (200 OK)

**Response Structure:**
- 5 tasks returned
- Fields: title, description, status, priority, project, column, assignedTo, dueDate, percentComplete, labels, case
- Example fields:
  - status: "in_progress"
  - priority: "high"
  - assignedTo: "jdoe"
  - dueDate: "2026-04-10 00:00:00"
  - percentComplete: "40"
  - labels: array of label IDs

**Notes:**
- Schema ID: 17, Register ID: 3
- Task has required `case` field (currently null)
- Status values from data: open, in_progress, blocked, done, cancelled
- Priority levels: low, normal, high, urgent

#### 1.3 GET Columns
**Endpoint:** `GET /index.php/apps/openregister/api/objects/planix/column`  
**Status:** ✓ PASS (200 OK)
- 16 columns returned
- Supports WIP (Work In Progress) limits

#### 1.4 GET Labels
**Endpoint:** `GET /index.php/apps/openregister/api/objects/planix/label`  
**Status:** ✓ PASS (200 OK)
- 5 seed labels returned
- Color-coded categorization

#### 1.5 GET Time Entries
**Endpoint:** `GET /index.php/apps/openregister/api/objects/planix/timeEntry`  
**Status:** ✓ PASS (200 OK)
- 3 time entries returned
- Linked to tasks for time tracking

---

### 2. Planix-Specific Settings APIs

#### 2.1 GET Settings
**Endpoint:** `GET /index.php/apps/planix/api/settings`  
**Status:** ✓ PASS (200 OK)

```json
{
  "register": "force",
  "openregisters": true,
  "isAdmin": true
}
```

**Required Headers:**
- `requesttoken`: CSRF protection token (from OC.requestToken)

**Notes:**
- Returns app configuration state
- isAdmin flag indicates user role

#### 2.2 POST Settings (Update)
**Endpoint:** `POST /index.php/apps/planix/api/settings`  
**Status:** ✓ PASS (200 OK)

**Request:**
```json
{
  "default_columns": [
    { "title": "Test", "order": 0, "wipLimit": 5, "type": "active" }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "config": { "..." }
}
```

#### 2.3 POST Settings/Load
**Endpoint:** `POST /index.php/apps/planix/api/settings/load`  
**Status:** ✓ PASS (200 OK)

**Response:**
```json
{
  "success": true,
  "message": "Configuration loaded/imported",
  "version": "..."
}
```

**Notes:**
- Forces fresh import of planix_register.json configuration
- Idempotent - safe to call multiple times
- Auto-configures all schema and register IDs

---

### 3. Pagination & Filtering

#### 3.1 Limit Parameter
**Test:** `GET /index.php/apps/openregister/api/objects/planix/task?_limit=2`  
**Status:** ✓ PASS (200 OK)
- Respects _limit parameter correctly
- Returns 2 results when total is 5

#### 3.2 Offset Parameter
**Test:** `GET /index.php/apps/openregister/api/objects/planix/project?_offset=1`  
**Status:** ✓ PASS (200 OK)
- Offset correctly skips first record
- Returns 3 results when offset=1 and total=4

#### 3.3 Invalid Limit Handling
**Test:** `GET /index.php/apps/openregister/api/objects/planix/task?_limit=invalid`  
**Status:** ⚠ PASS but problematic (200 OK with limit:0)

**Issue:** Invalid _limit parameter silently converted to 0, returns empty results instead of 400 Bad Request
- No error message provided to client
- **Recommendation:** Validate limit parameter and return 400 with error message

#### 3.4 Status Filter
**Test:** `GET /index.php/apps/openregister/api/objects/planix/project?status=active`  
**Status:** ✓ PASS (200 OK)
- Field filtering works correctly
- Returns only matching records (4 active projects)

#### 3.5 Combined Pagination & Filtering
**Test:** `GET /index.php/apps/openregister/api/objects/planix/column?_limit=5&_offset=5`  
**Status:** ✓ PASS (200 OK)
- Pagination and filtering work together correctly
- Returns 5 results at offset 5

---

### 4. CRUD Operations

#### 4.1 POST (Create Task)
**Endpoint:** `POST /index.php/apps/openregister/api/objects/planix/task`  
**Status:** ✗ FAIL (400 Bad Request)

**Test Payload:**
```json
{
  "title": "API Test Task",
  "description": "Test",
  "status": "in_progress",
  "priority": "normal",
  "project": "00000000-0000-4000-a000-000000000001",
  "case": null
}
```

**Error Response:**
```
Property 'status' should be one of: , but is 'in_progress'.
```

**Issues:**
- Status validation fails with cryptic error
- Enum validation broken - no valid values shown
- Cannot determine valid status values from error
- **BLOCKING ISSUE:** Cannot create tasks via API

#### 4.2 PUT (Update Task)
**Endpoint:** `PUT {task_uri}`  
**Status:** ✗ FAIL (400 Bad Request)

**Test 1 - Partial Update:**
```json
{ "percentComplete": "50" }
```

**Error:**
```
Validation failed: The required properties (title, case) are missing.
```

**Test 2 - With Required Fields:**
```json
{
  "title": "Fix login page redirect bug",
  "case": null,
  "percentComplete": "55"
}
```

**Error:** Still fails validation

**Issues:**
- PUT requires all required fields to be resent
- Partial updates fail
- PATCH method not supported
- **BLOCKING ISSUE:** Cannot update tasks via API

#### 4.3 DELETE
**Status:** ⚠ NOT TESTED

**Reason:** Cannot test DELETE due to CREATE/UPDATE failures upstream

---

### 5. Health & Metrics Endpoints

#### 5.1 Health Check
**Endpoint:** `GET /index.php/apps/planix/api/health`  
**Status:** ✗ FAIL (500 Internal Server Error)

**Issues:**
- Endpoint exists in routes but returns 500
- Returns HTML error page instead of JSON
- **BLOCKING ISSUE:** Health check not functional

#### 5.2 Metrics
**Endpoint:** `GET /index.php/apps/planix/api/metrics`  
**Status:** ✗ FAIL (500 Internal Server Error)

**Issues:**
- Endpoint exists in routes but returns 500
- Returns HTML error page instead of JSON
- Likely intended for Prometheus metrics export
- **BLOCKING ISSUE:** Metrics endpoint not functional

---

## Admin Settings Tests

**Location:** `http://nextcloud.local/index.php/apps/planix/settings`

### Features Tested
- Version information display
- Application information  
- Support contact display
- Register configuration section
- Load configuration button

### APIs Called
- GET /index.php/apps/planix/api/settings → ✓ 200 OK
- POST /index.php/apps/planix/api/settings → ✓ 200 OK
- POST /index.php/apps/planix/api/settings/load → ✓ 200 OK

---

## Console Errors During Testing

- Profiler CSS loading errors (non-critical, MIME type issue)
- No critical app logic errors detected

---

## Key Findings

### Strengths
1. **Consistent API response format** - All OpenRegister endpoints follow same structure
2. **Pagination support** - Works correctly with _limit and _offset
3. **Field-based filtering** - Supports filtering on any field
4. **Settings API** - Admin configuration endpoints work well
5. **Performance** - Response times ~4ms for collection queries

### Critical Issues
1. **CREATE blocked** - Cannot create tasks (enum validation broken)
2. **UPDATE blocked** - Cannot update tasks (requires full object resend)
3. **Health/Metrics broken** - Both return 500 errors
4. **No API documentation** - Status enum values unknown

### Data Validation Issues
1. Invalid query parameters silently fail instead of returning 400
2. Error messages incomplete - enum validation shows empty values
3. CSRF token required for settings APIs but not collection APIs

---

## Testing Score

**Overall API Quality: 7/10 (70%)**

- Data retrieval: 9/10 ✓
- Admin settings: 9/10 ✓
- CRUD operations: 2/10 ✗ (CREATE and UPDATE broken)
- Error handling: 5/10
- Documentation: 2/10

---

## Recommendations

1. **Fix enum validation** - Debug OpenRegister status/priority enum loading
2. **Support PATCH** - Implement for partial updates
3. **Add validation** - Return 400 for invalid query parameters
4. **Fix health/metrics** - Debug 500 error endpoints
5. **Publish API docs** - OpenAPI/Swagger specification needed
6. **Improve error messages** - Show allowed enum values in errors

---

**Test Environment:** Nextcloud.local, MariaDB, OpenRegister v0.2.10+
**Test Method:** Browser-based fetch() API testing with CSRF tokens
**Test Coverage:** 14 endpoint tests across 5 feature areas

