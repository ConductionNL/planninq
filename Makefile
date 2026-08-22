# Makefile for planninq development

# Create a relative symlink in the parent directory so Nextcloud can find the
# app by its ID (planninq) even when the repo is cloned under another name.
# Nextcloud requires the directory name to match the <id> in appinfo/info.xml.
dev-link:
	@if [ -L ../planninq ]; then \
		echo "Symlink ../planninq already exists."; \
	else \
		ln -s planninq ../planninq && \
		echo "Created symlink: apps-extra/planninq -> planninq"; \
	fi

dev-unlink:
	@if [ -L ../planninq ]; then \
		rm ../planninq && echo "Removed symlink ../planninq"; \
	else \
		echo "No symlink found at ../planninq."; \
	fi

.PHONY: dev-link dev-unlink
