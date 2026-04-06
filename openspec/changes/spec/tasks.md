# Tasks — Label CRUD

## Task 1: Backend — Label CRUD endpoints
**spec_ref**: label-crud/design.md
**files_likely_affected**: lib/Controller/LabelController.php (new), lib/Service/LabelService.php (new), appinfo/routes.php
**acceptance_criteria**:
- [x] `GET /api/labels` lists all labels
- [x] `POST /api/labels` creates a label (name required, color optional)
- [x] `DELETE /api/labels/{id}` deletes a label
- [x] Returns 400 if name is missing on create
