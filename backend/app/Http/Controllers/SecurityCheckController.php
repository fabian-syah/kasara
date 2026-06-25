<?php

namespace App\Http\Controllers;

use App\Models\SecurityCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityCheckController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_id' => 'required|string',
            'security_name' => 'required|string',
            'inventory_user_id' => 'nullable|integer',
            'notes' => 'nullable|string',
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|boolean',
            'excess_items' => 'nullable|array',
            'excess_items.*.brand' => 'nullable|string',
            'excess_items.*.type' => 'nullable|string',
            'excess_items.*.storage' => 'nullable|string',
            'excess_items.*.excess_qty' => 'required|integer|min:1',
            'excess_items.*.notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $securityCheck = SecurityCheck::create([
                'receipt_id' => $validated['receipt_id'],
                'security_name' => $validated['security_name'],
                'inventory_user_id' => $validated['inventory_user_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if (!empty($validated['answers'])) {
                foreach ($validated['answers'] as $ans) {
                    $securityCheck->answers()->create([
                        'question_id' => $ans['question_id'],
                        'answer' => $ans['answer'],
                    ]);
                }
            }

            if (!empty($validated['excess_items'])) {
                foreach ($validated['excess_items'] as $item) {
                    $securityCheck->excessItems()->create([
                        'brand' => $item['brand'] ?? null,
                        'type' => $item['type'] ?? null,
                        'storage' => $item['storage'] ?? null,
                        'excess_qty' => $item['excess_qty'],
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Security check saved successfully.',
                'data' => $securityCheck->load(['answers', 'excessItems']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save security check: ' . $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $query = SecurityCheck::with([
            'stockOut.items.product',
            'stockOut.nonHpItems.product',
            'inventoryUser',
            'excessItems'
        ])->orderBy('created_at', 'desc');

        if ($request->has('q')) {
            $q = $request->q;
            $query->where('receipt_id', 'like', "%{$q}%")
                  ->orWhere('security_name', 'like', "%{$q}%");
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20)
        ]);
    }
}
