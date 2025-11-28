<?php
// app/Http/Controllers/TodoController.php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TodoController extends Controller
{
    // Remove the constructor with middleware - we'll use route middleware instead
    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum');
    // }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Todo::where('user_id', Auth::id());
            
            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Validate sort parameters
            $allowedSortColumns = ['created_at', 'due_date', 'title', 'completed', 'category'];
            $allowedSortOrders = ['asc', 'desc'];
            
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'created_at';
            }
            
            if (!in_array($sortOrder, $allowedSortOrders)) {
                $sortOrder = 'desc';
            }
            
            // Special handling for completed status (boolean first)
            if ($sortBy === 'completed') {
                $query->orderBy('completed', $sortOrder === 'asc' ? 'asc' : 'desc')
                      ->orderBy('due_date', 'asc')
                      ->orderBy('created_at', 'desc');
            } 
            // Special handling for due date (null values last)
            elseif ($sortBy === 'due_date') {
                if ($sortOrder === 'asc') {
                    $query->orderByRaw('due_date IS NULL, due_date ASC')
                          ->orderBy('created_at', 'desc');
                } else {
                    $query->orderByRaw('due_date IS NOT NULL, due_date DESC')
                          ->orderBy('created_at', 'desc');
                }
            }
            else {
                $query->orderBy($sortBy, $sortOrder);
                
                // Add secondary sort for consistent ordering
                if ($sortBy !== 'created_at') {
                    $query->orderBy('created_at', 'desc');
                }
            }
            
            $todos = $query->get();

            return response()->json([
                'success' => true,
                'data' => $todos,
                'sort' => [
                    'by' => $sortBy,
                    'order' => $sortOrder
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch todos'
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date|after:today',
            'category' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $todo = Todo::create([
                ...$request->all(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $todo,
                'message' => 'Todo created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create todo'
            ], 500);
        }
    }

    public function show(Todo $todo): JsonResponse
    {
        // Check if the todo belongs to the authenticated user
        if ($todo->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $todo
        ]);
    }

    public function update(Request $request, Todo $todo): JsonResponse
    {
        // Check if the todo belongs to the authenticated user
        if ($todo->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'completed' => 'boolean',
            'due_date' => 'nullable|date',
            'category' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $todo->update($request->all());
            return response()->json([
                'success' => true,
                'data' => $todo,
                'message' => 'Todo updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update todo'
            ], 500);
        }
    }

    public function destroy(Todo $todo): JsonResponse
    {
        // Check if the todo belongs to the authenticated user
        if ($todo->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $todo->delete();
            return response()->json([
                'success' => true,
                'message' => 'Todo deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete todo'
            ], 500);
        }
    }

    public function search($term): JsonResponse
    {
        try {
            $todos = Todo::where('user_id', Auth::id())
                        ->where(function($query) use ($term) {
                            $query->where('title', 'like', "%{$term}%")
                                  ->orWhere('description', 'like', "%{$term}%")
                                  ->orWhere('category', 'like', "%{$term}%");
                        })
                        ->get();
            
            return response()->json([
                'success' => true,
                'data' => $todos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed'
            ], 500);
        }
    }

    public function filterByStatus($filter): JsonResponse
    {
        try {
            $query = Todo::where('user_id', Auth::id());
            
            $todos = match($filter) {
                'completed' => $query->where('completed', true)->get(),
                'pending' => $query->where('completed', false)->get(),
                default => $query->get()
            };

            return response()->json([
                'success' => true,
                'data' => $todos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Filter failed'
            ], 500);
        }
    }

    public function getUpcoming(): JsonResponse
    {
        try {
            $upcomingTodos = Todo::where('user_id', Auth::id())
                                ->where('completed', false)
                                ->whereNotNull('due_date')
                                ->where('due_date', '>=', now()->toDateString())
                                ->orderBy('due_date', 'asc')
                                ->orderBy('created_at', 'desc')
                                ->limit(10)
                                ->get();

            $overdueTodos = Todo::where('user_id', Auth::id())
                               ->where('completed', false)
                               ->whereNotNull('due_date')
                               ->where('due_date', '<', now()->toDateString())
                               ->orderBy('due_date', 'asc')
                               ->orderBy('created_at', 'desc')
                               ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'upcoming' => $upcomingTodos,
                    'overdue' => $overdueTodos
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming todos'
            ], 500);
        }
    }
}