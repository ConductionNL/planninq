# task-editing delta for task-description-images

## ADDED Requirements

### Requirement: An image pasted into a task description is stored with the task

A project member MUST be able to paste or drop an image into the task description. The image
MUST be stored as a file of the task and MUST show in the description.

#### Scenario: Paste a screenshot

- **GIVEN** a project member editing the description of "Bewonersavond voorbereiden"
- **WHEN** they paste a screenshot and save
- **THEN** the description shows the image
- **AND** the image is listed on the Files tab of the task
