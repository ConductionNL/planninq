---
kind: code
---

# Paste an image into a task description

## Why

The parity row asks to paste or drop an image into a task description or a comment. The
comment half is specified in nextcloud-vue (`notes-replies-group-mentions-and-images`). The
description half has no requirement in planninq: `openspec/specs/task-editing` covers Markdown
text only.

Parity row: `col-comment-images` in `openspec/parity/capabilities.json`.

## What changes

- A member pastes or drops an image into the task description. It is stored as a file of the
  task and shows in the description.
