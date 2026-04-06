# Tasks — Label CRUD

## Task 1: Backend — Label CRUD endpoints
**spec_ref**: label-crud/design.md
**files_likely_affected**: lib/Controller/LabelController.php (new), lib/Service/LabelService.php (new), appinfo/routes.php
**acceptance_criteria**:
- [ ] `GET /api/labels` lists all labels
- [ ] `POST /api/labels` creates a label (name required, color optional)
- [ ] `DELETE /api/labels/{id}` deletes a label
- [ ] Returns 400 if name is missing on create
