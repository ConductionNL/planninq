/**
 * Compute the due date status of a task.
 * @param {object} task - Task object with optional dueDate property
 * @return {null|string} Due date status based on current date ('approaching' or 'overdue')
 */
// openspec/changes/task-due-date-warning/tasks.md#task-1
export function dueDateStatus(task) {
	if (!task?.dueDate) {
		return null
	}

	const today = new Date()
	today.setHours(0, 0, 0, 0)

	const dueDate = new Date(task.dueDate)
	dueDate.setHours(0, 0, 0, 0)

	const diffTime = dueDate - today
	const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

	if (diffDays < 0) {
		return 'overdue'
	}

	if (diffDays <= 2) {
		return 'approaching'
	}

	return null
}
