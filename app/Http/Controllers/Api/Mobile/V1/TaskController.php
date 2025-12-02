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
        
        // Check direct task assignment
        $isDirectlyAssigned = $task->assigned_to_user_id === $userId;
        
        // Check care assignment (with null safety)
        $isAssignedViaCareAssignment = $task->careAssignment 
            && $task->careAssignment->assigned_user_id === $userId;

        if (!$isDirectlyAssigned && !$isAssignedViaCareAssignment) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->update([
            'status' => 'done',
            'completed_at' => now()
        ]);

        return response()->json($task);
    }
}
