<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function complete(Request $request, Task $task)
    {
        // Ensure user is assigned to the task or the parent assignment
        $userId = $request->user()->id;
        $isAssigned = $task->assigned_to_user_id === $userId || 
                      $task->careAssignment->assigned_user_id === $userId;

        if (!$isAssigned) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->update([
            'status' => 'done',
            'completed_at' => now()
        ]);

        return response()->json($task);
    }
}
