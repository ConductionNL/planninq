# Makefile for planix development

# Create a relative symlink in the parent directory so Nextcloud can find the
# app by its ID (planix) even though the repo is cloned as planix.
# Nextcloud requires the directory name to match the <id> in appinfo/info.xml.
dev-link:
	@if [ -L ../planix ]; then \
		echo "Symlink ../planix already exists."; \
	else \
		ln -s planix ../planix && \
		echo "Created symlink: apps-extra/planix -> planix"; \
	fi

dev-unlink:
	@if [ -L ../planix ]; then \
		rm ../planix && echo "Removed symlink ../planix"; \
	else \
		echo "No symlink found at ../planix."; \
	fi

.PHONY: dev-link dev-unlink
