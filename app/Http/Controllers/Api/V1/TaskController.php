<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * GET /api/v1/tasks
     * List all tasks for the authenticated user.
     */
    public function index(Request $request)
    {
        $query = Task::where('user_id', Auth::id())
            ->orderByRaw("FIELD(priority, 'high', 'normal')")
            ->orderBy('created_at', 'desc');

        // Optional status filter
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * POST /api/v1/tasks
     * Create a new task.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'status'          => 'in:todo,in_progress,done',
            'priority'        => 'in:high,normal',
            'cover_image'     => 'nullable|string|max:500',
            'assignee_name'   => 'nullable|string|max:100',
            'assignee_avatar' => 'nullable|string|max:500',
        ]);

        $task = Task::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'data' => $task,
            'message' => 'Task created successfully.',
        ], 201);
    }

    /**
     * GET /api/v1/tasks/{task}
     * Get a single task.
     */
    public function show(Task $task)
    {
        $this->authorizeTask($task);

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    /**
     * PUT /api/v1/tasks/{task}
     * Update a task.
     */
    public function update(Request $request, Task $task)
    {
        $this->authorizeTask($task);

        $validated = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'status'          => 'sometimes|in:todo,in_progress,done',
            'priority'        => 'sometimes|in:high,normal',
            'cover_image'     => 'nullable|string|max:500',
            'assignee_name'   => 'nullable|string|max:100',
            'assignee_avatar' => 'nullable|string|max:500',
        ]);

        $task->update($validated);

        return response()->json([
            'success' => true,
            'data' => $task->fresh(),
            'message' => 'Task updated successfully.',
        ]);
    }

    /**
     * PATCH /api/v1/tasks/{task}/status
     * Update only the status of a task (Kanban drag-and-drop).
     */
    public function updateStatus(Request $request, Task $task)
    {
        $this->authorizeTask($task);

        $validated = $request->validate([
            'status' => 'required|in:todo,in_progress,done',
        ]);

        $task->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $task->fresh(),
            'message' => 'Task status updated.',
        ]);
    }

    /**
     * PATCH /api/v1/tasks/{task}/log-time
     * Add time spent to a task (timer integration).
     */
    public function logTime(Request $request, Task $task)
    {
        $this->authorizeTask($task);

        $validated = $request->validate([
            'seconds' => 'required|integer|min:1',
        ]);

        $task->increment('time_spent', $validated['seconds']);

        return response()->json([
            'success' => true,
            'data' => $task->fresh(),
            'message' => 'Time logged successfully.',
        ]);
    }

    /**
     * DELETE /api/v1/tasks/{task}
     * Delete a task.
     */
    public function destroy(Task $task)
    {
        $this->authorizeTask($task);

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }

    /**
     * Ensure the authenticated user owns the task.
     */
    private function authorizeTask(Task $task): void
    {
        if ($task->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this task.');
        }
    }
}
