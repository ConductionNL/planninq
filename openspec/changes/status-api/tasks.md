# Tasks — Status API

## Task 1: Backend — Health check endpoint
**spec_ref**: status-api/design.md
**files_likely_affected**: lib/Controller/HealthController.php (new), appinfo/routes.php
**acceptance_criteria**:
- [ ] `GET /api/health` returns JSON with `status`, `version`, and `openRegisterAvailable` fields
- [ ] Endpoint is public (no auth required) for load balancer use
- [ ] Returns HTTP 200 when healthy, 503 when OpenRegister unavailable
