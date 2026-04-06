# Tasks — Label CRUD

## Task 1: Backend — Label CRUD endpoints
**spec_ref**: label-crud/design.md
**files_likely_affected**: lib/Controller/LabelController.php (new), lib/Service/LabelService.php (new), appinfo/routes.php
**acceptance_criteria**:
- [x] `GET /api/labels` lists all labels (read access for all authenticated users)
- [x] `POST /api/labels` creates a label (`title` and `color` both required; admin only)
- [x] `DELETE /api/labels/{id}` deletes a label by UUID (admin only; returns 404 for unknown IDs; returns 400 for invalid UUID format)
- [x] Returns 400 if `title` is missing on create
- [x] Returns 400 if `color` is missing or not a valid hex color code (e.g. `#RRGGBB`) on create
- [x] Returns 403 for non-admin users attempting to create or delete labels
